<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\{Event, JobPosting, NewsArticle, Offer};
use App\Enum\{EventRecurrence, NewsStatus};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/** Exercise actual MariaDB hydration, rather than only freshly constructed UTC objects. */
final class AdminDateTimezoneTest extends PublicDirectoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\\Entity\\Event event')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\NewsArticle article')->execute();
        $em->clear();
    }

    public static function dates(): iterable
    {
        foreach (['2026-10-14', '2026-07-14', '2026-12-14', '2026-03-29', '2026-10-25'] as $day) {
            foreach (['offer', 'event', 'news_article', 'job_posting'] as $type) {
                yield $type.' '.$day => [$type, $day];
            }
        }
        yield 'all-day spring DST' => ['event', '2026-03-29', true];
        yield 'all-day autumn DST' => ['event', '2026-10-25', true];
    }

    #[DataProvider('dates')]
    public function testPersistedDatesDisplayAndRoundTripWithoutChangingWallClockTime(string $type, string $day, bool $allDay = false): void
    {
        self::assertSame('Europe/Berlin', date_default_timezone_get());
        $zone = new \DateTimeZone('Europe/Berlin');
        $start = new \DateTimeImmutable($day.' 08:45:00', $zone);
        $end = new \DateTimeImmutable($day.' 18:00:00', $zone);
        if ($allDay) {
            $start = $start->setTime(0, 0);
            $end = $end->setTime(23, 59, 59);
        }
        $published = new \DateTimeImmutable($day.' 08:45:37', $zone);
        $until = $start->modify('+14 days');
        $entity = match ($type) {
            'offer' => (new Offer())->setCompany($this->firstCompany())->setStartsAt($start)->setEndsAt($end),
            'event' => (new Event())->setAllDay($allDay)->setStartsAt($start)->setEndsAt($end)
                ->setRecurrenceType(EventRecurrence::Weekly)->setRecurrenceUntil($until),
            'news_article' => (new NewsArticle())->setStatus(NewsStatus::Scheduled)->setContent('Timezone regression')->setPublishedAt($published),
            'job_posting' => (new JobPosting())->setStatus(NewsStatus::Scheduled)->setCompany($this->firstCompany())
                ->setShortDescription('Timezone regression')->setDescription('Timezone regression')
                ->setCity('Haselünne')->setApplicationEmail('jobs@example.org')
                ->setPublishedAt($published)->setValidThrough($end->setTime($day === '2026-07-14' ? 21 : 23, 59, 59))
                ->setStartsAt($start->setTime($day === '2026-07-14' ? 14 : 0, 0)),
        };
        $entity->setTitle('Timezone regression')->setSlug('timezone-regression-'.str_replace('_', '-', $type));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        if ($entity instanceof Event) {
            static::getContainer()->get(\App\Service\EventSchedule::class)->synchronize($entity);
        }
        $em->persist($entity);
        $em->flush();
        $id = $entity->getId();
        $class = $entity::class;
        $em->clear();
        $reloaded = $em->find($class, $id);
        self::assertNotSame($entity, $reloaded);
        $fields = match ($type) {
            'offer' => ['startsAt', 'endsAt'],
            'event' => ['startsAt', 'endsAt', 'recurrenceUntil'],
            'news_article' => ['publishedAt'],
            'job_posting' => ['publishedAt', 'validThrough', 'startsAt'],
        };
        $before = $this->dateValues($reloaded, $fields);
        if ($reloaded instanceof Event) {
            self::assertSame($start->format('Y-m-d H:i:s'), $reloaded->getOccurrences()->first()->getStartsAt()->setTimezone($zone)->format('Y-m-d H:i:s'));
        }
        $timestamp = in_array($type, ['offer', 'event'], true) ? $reloaded->getStartsAt() : $reloaded->getPublishedAt();
        self::assertSame('Europe/Berlin', $timestamp->getTimezone()->getName());
        $route = match ($type) {
            'offer' => 'offers', 'event' => 'events', 'news_article' => 'news', 'job_posting' => 'jobs',
        };
        $this->login('editor');
        $this->client->request('GET', '/admin/'.$route.'/'.$id.'/edit');
        self::assertResponseStatusCodeSame(200);
        $expected = match ($type) {
            'offer', 'event' => ['startsAt' => $start->format('Y-m-d\\TH:i'), 'endsAt' => $end->format('Y-m-d\\TH:i')],
            'news_article' => ['publishedAt' => $day.'T08:45'],
            'job_posting' => ['publishedAt' => $day.'T08:45', 'validThrough' => $day, 'startsAt' => $day],
        };
        if ($type === 'event') {
            $expected['recurrenceUntil'] = $until->format('Y-m-d');
        }
        foreach ($expected as $field => $value) {
            self::assertSelectorExists('input[name="'.$type.'['.$field.']"][value="'.$value.'"]');
        }
        $this->client->submitForm('Speichern');
        self::assertResponseStatusCodeSame(303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame($before, $this->dateValues($em->find($class, $id), $fields));
        // A second edit also covers transformers whose output would drift on every save.
        $this->client->request('GET', '/admin/'.$route.'/'.$id.'/edit');
        self::assertResponseStatusCodeSame(200);
        $this->client->submitForm('Speichern');
        self::assertResponseStatusCodeSame(303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame($before, $this->dateValues($em->find($class, $id), $fields));
    }

    public function testOfferWithNullDatesRemainsEditable(): void
    {
        $offer = (new Offer())->setTitle('No dates')->setSlug('no-dates')->setCompany($this->firstCompany());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($offer);
        $em->flush();
        $id = $offer->getId();
        $em->clear();
        self::assertNull($em->find(Offer::class, $id)->getStartsAt());
        $this->login('editor');
        $this->client->request('GET', '/admin/offers/'.$id.'/edit');
        self::assertResponseStatusCodeSame(200);
        self::assertSame('', $this->client->getCrawler()->filter('input[name="offer[startsAt]"]')->attr('value', ''));
        self::assertSame('', $this->client->getCrawler()->filter('input[name="offer[endsAt]"]')->attr('value', ''));
        $this->client->submitForm('Speichern');
        self::assertResponseStatusCodeSame(303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertNull($em->find(Offer::class, $id)->getStartsAt());
        self::assertNull($em->find(Offer::class, $id)->getEndsAt());
    }

    private function dateValues(object $entity, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $entity->{'get'.ucfirst($field)}()?->format('Y-m-d H:i:s');
        }

        if ($entity instanceof Event) {
            $values['occurrences'] = $entity->getOccurrences()->map(static fn ($occurrence): array => [
                $occurrence->getId(), $occurrence->getStartsAt()->format('c'), $occurrence->getEndsAt()->format('c'),
            ])->getValues();
        }

        return $values;
    }
}
