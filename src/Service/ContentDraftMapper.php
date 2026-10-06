<?php

declare(strict_types=1);
namespace App\Service;

use App\Entity\{Company, CompanyImage, ContactPerson, OpeningHour};
use App\Enum\ContentType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/** Explicit editable schema. Detached validation objects are never flushed into the live identity map. */
final readonly class ContentDraftMapper
{
    private const FIELDS = [
        'companies' => ['name','shortDescription','description','street','houseNumber','postalCode','city','phone','email','website','facebookUrl','instagramUrl','latitude','longitude'],
        'offers' => ['title','type','shortDescription','description','startsAt','endsAt','regularPrice','offerPrice','discountText','imagePath','imageAlt','externalUrl','terms'],
        'events' => ['title','shortDescription','description','allDay','freeAdmission','cancelled','startsAt','endsAt','recurrenceType','recurrenceUntil','organizerName','organizerEmail','organizerPhone','organizerWebsite','locationName','street','houseNumber','postalCode','city','latitude','longitude','imagePath','imageAlt','admissionText','externalUrl','ticketUrl','cancellationNotice'],
        'news' => ['title','teaser','content','imagePath','imageAltText','authorName','externalUrl'],
        'jobs' => ['title','shortDescription','description','requirements','benefits','employmentType','workModel','salaryPeriod','locationName','street','houseNumber','postalCode','city','latitude','longitude','addressCountry','applicationEmail','applicationUrl','contactName','contactPhone','referenceNumber','validThrough','startsAt','salaryMin','salaryMax'],
    ];
    private const CHILDREN = [
        'openingHours' => [OpeningHour::class, ['dayOfWeek','opensAt','closesAt','closed','position'], 'OpeningHour'],
        'contactPersons' => [ContactPerson::class, ['firstName','lastName','position','email','phone','mobile','primaryContact','sortOrder','image'], 'ContactPerson'],
        'images' => [CompanyImage::class, ['fileName','altText','title','type','position'], 'Image'],
    ];
    public function __construct(private EntityManagerInterface $em, private PropertyAccessorInterface $accessor) {}
    public function capture(object $entity): array
    {
        $type = ContentType::forEntity($entity); $data = [];
        foreach (self::FIELDS[$type->value] as $field) { $data[$field] = $this->encode($this->accessor->getValue($entity, $field)); }
        if (method_exists($entity, 'getCategories')) { $data['categories'] = array_map(static fn ($c) => $c->getId(), $entity->getCategories()->toArray()); sort($data['categories']); }
        if ($entity instanceof Company) {
            foreach (self::CHILDREN as $key => [$class, $fields]) {
                $data[$key] = [];
                foreach ($this->accessor->getValue($entity, $key) as $child) {
                    $row = ['id' => $child->getId()];
                    foreach ($fields as $field) {
                        $value = $this->accessor->getValue($child, $field);
                        $row[$field] = $child instanceof OpeningHour && in_array($field, ['opensAt', 'closesAt'], true) && $value instanceof \DateTimeImmutable
                            ? $value->format('H:i:s')
                            : $this->encode($value);
                    }
                    // Keep server-owned per-contact active state; member form has no active field.
                    if ($child instanceof ContactPerson) { $row['active'] = $child->isActive(); }
                    $data[$key][] = $row;
                }
            }
        }
        return $data;
    }
    public function fingerprint(object $entity): string
    {
        $data = $this->capture($entity); $data['server:version'] = $entity->getModerationVersion();
        foreach (['slug','active','featured','status','publishedAt'] as $field) { if ($this->accessor->isReadable($entity, $field)) { $data['server:'.$field] = $this->encode($this->accessor->getValue($entity, $field)); } }
        if (!$entity instanceof Company) { $data['server:company'] = $entity->getCompany()?->getId(); }
        foreach (array_keys(self::CHILDREN) as $children) {
            if (isset($data[$children])) {
                usort($data[$children], static fn (array $left, array $right): int => json_encode($left, JSON_THROW_ON_ERROR) <=> json_encode($right, JSON_THROW_ON_ERROR));
            }
        }

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
    public function materialize(object $target, array $payload): object
    {
        $class = ContentType::forEntity($target)->entityClass(); $draft = new $class(); $draft->markRevisionShadow(); $metadata = $this->em->getClassMetadata($class);
        // Retain identifier only for UniqueEntity validation against the original, never persist this copy.
        foreach ($metadata->getFieldNames() as $field) { $metadata->setFieldValue($draft, $field, $metadata->getFieldValue($target, $field)); }
        if (!$target instanceof Company) { $metadata->setFieldValue($draft, 'company', $target->getCompany()); }
        $this->scalars($draft, $payload);
        if (isset($payload['categories'])) { $this->categories($draft, $payload['categories'], false); }
        if ($draft instanceof Company) {
            foreach (self::CHILDREN as $key => [$class, $fields, $suffix]) {
                foreach ($payload[$key] ?? [] as $row) {
                    $child = new $class(); $childMetadata = $this->em->getClassMetadata($class); $childMetadata->setFieldValue($child, 'id', $row['id'] ?? null);
                    foreach ($fields as $field) { $this->value($child, $field, $row[$field] ?? null); }
                    if ($child instanceof ContactPerson) { $child->setActive($row['active'] ?? true); }
                    $draft->{'add'.$suffix}($child);
                }
            }
        }
        return $draft;
    }
    public function apply(object $target, array $payload): void
    {
        $this->scalars($target, $payload);
        if (isset($payload['categories'])) { $this->categories($target, $payload['categories'], true); }
        if ($target instanceof Company) {
            // Images first so contacts can keep references to retained images.
            foreach (['images','openingHours','contactPersons'] as $key) {
                [$class, $fields, $suffix] = self::CHILDREN[$key]; $existing = [];
                foreach ($this->accessor->getValue($target, $key) as $child) { $existing[$child->getId()] = $child; }
                $kept = [];
                foreach ($payload[$key] ?? [] as $row) {
                    $child = isset($row['id']) ? ($existing[$row['id']] ?? throw new \DomainException('Ein verknüpfter Datensatz wurde inzwischen geändert.')) : new $class();
                    foreach ($fields as $field) { $this->value($child, $field, $row[$field] ?? null); }
                    // Active remains a reviewer/admin decision and is preserved on existing contacts.
                    $target->{'add'.$suffix}($child); $kept[] = $child;
                }
                foreach ($existing as $child) { if (!in_array($child, $kept, true)) { $target->{'remove'.$suffix}($child); } }
            }
        }
    }
    public function imageNames(array $payload): array
    {
        $names = []; if (($payload['imagePath'] ?? null) !== null) { $names[] = $payload['imagePath']; }
        foreach ($payload['images'] ?? [] as $image) { if (($image['fileName'] ?? '') !== '') { $names[] = $image['fileName']; } }
        return array_values(array_unique($names));
    }
    private function scalars(object $entity, array $data): void
    {
        foreach (self::FIELDS[ContentType::forEntity($entity)->value] as $field) { if (array_key_exists($field, $data)) { $this->value($entity, $field, $data[$field]); } }
    }
    private function categories(object $entity, array $ids, bool $apply): void
    {
        $metadata = $this->em->getClassMetadata($entity::class); $class = $metadata->getAssociationTargetClass('categories'); $values = [];
        foreach ($ids as $id) { $values[] = $this->em->find($class, $id) ?? throw new \DomainException('Eine Kategorie ist nicht mehr verfügbar.'); }
        if (!$apply) { $metadata->setFieldValue($entity, 'categories', new ArrayCollection($values)); return; }
        foreach ($entity->getCategories()->toArray() as $category) { $entity->removeCategory($category); }
        foreach ($values as $category) { $entity->addCategory($category); }
    }
    private function value(object $entity, string $field, mixed $value): void
    {
        $name = (new \ReflectionProperty($entity, $field))->getType()->getName();
        if ($value !== null && $name === \DateTimeImmutable::class) { $value = new \DateTimeImmutable($value); }
        elseif ($value !== null && enum_exists($name)) { $value = $name::from($value); }
        elseif ($value !== null && $name === CompanyImage::class) { $value = $this->em->find(CompanyImage::class, $value); }
        $entity->{'set'.ucfirst($field)}($value);
    }
    private function encode(mixed $value): mixed
    {
        return match (true) { $value instanceof \BackedEnum => $value->value, $value instanceof \DateTimeImmutable => $value->format('c'), is_object($value) => $value->getId(), default => $value };
    }
}
