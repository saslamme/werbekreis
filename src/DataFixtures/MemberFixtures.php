<?php

declare(strict_types=1);
namespace App\DataFixtures;
use App\Entity\{Company, ContentRevision, Event, JobPosting, NewsArticle, Offer, User};
use App\Enum\{ContentType, ModerationStatus, NewsStatus};
use App\Service\{ContentDraftMapper, DirectorySlugger, EventSchedule};
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Fictional member ownership and all review states; never load into production. */
final class MemberFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly ContentDraftMapper $mapper, private readonly DirectorySlugger $slugs, private readonly EventSchedule $schedule, private readonly ClockInterface $clock, private readonly UserPasswordHasherInterface $hasher) {}
    public function getDependencies(): array { return [DirectoryFixtures::class, UserFixtures::class]; }
    public function load(ObjectManager $em): void
    {
        $shop=$em->getRepository(Company::class)->findOneBy(['name'=>'Musterladen Hasebogen']); $cafe=$em->getRepository(Company::class)->findOneBy(['name'=>'Beispielcafé Uferpause']);
        $members=[];
        foreach (['membera'=>[$shop], 'memberb'=>[$cafe], 'membermulti'=>[$shop,$cafe]] as $name=>$companies) {
            $user=(new User())->setEmail($name.'@example.local')->setFirstName('TEST')->setLastName($name)->setRoles(['ROLE_MEMBER']); $user->setPassword($this->hasher->hashPassword($user,'member123'));
            foreach ($companies as $company) { $user->addCompany($company); } $em->persist($user); $members[$name]=$user;
        }
        $em->flush(); $editor=$em->getRepository(User::class)->findOneBy(['email'=>'editor@example.local']); $now=$this->clock->now();
        foreach ([ContentType::Offer,ContentType::Event,ContentType::News,ContentType::Job] as $type) {
            foreach ([ModerationStatus::Draft,ModerationStatus::PendingReview,ModerationStatus::ChangesRequested,ModerationStatus::Approved,ModerationStatus::Rejected] as $state) {
                $target=$this->content($type,$shop,'TEST '.$type->value.' '.$state->value); if ($state!==ModerationStatus::Approved) { $target->beginMemberDraft(); }
                $this->slugs->assign($target); if ($target instanceof Event) { $this->schedule->synchronize($target); } $em->persist($target); $em->flush();
                $payload=$this->mapper->capture($target); $revision=new ContentRevision($target,$payload,$this->mapper->imageNames($payload),$this->mapper->fingerprint($target),$now);
                if ($state!==ModerationStatus::Draft) { $revision->submit($members['membera'],$now->modify('-1 hour')); }
                if (in_array($state,[ModerationStatus::ChangesRequested,ModerationStatus::Approved,ModerationStatus::Rejected],true)) { $revision->review($state,$editor,$now,'TEST Prüfkommentar'); }
                $em->persist($revision); $em->flush();
            }
            $foreign=$this->content($type,$cafe,'TEST fremd '.$type->value); $this->slugs->assign($foreign); if ($foreign instanceof Event) { $this->schedule->synchronize($foreign); } $em->persist($foreign); $em->flush();
        }
        $payload=$this->mapper->capture($shop); $payload['description']='TEST eingereichte Profilbeschreibung'; $revision=new ContentRevision($shop,$payload,$this->mapper->imageNames($payload),$this->mapper->fingerprint($shop),$now); $revision->submit($members['membera'],$now); $em->persist($revision); $em->flush();
    }
    private function content(ContentType $type, Company $company, string $title): object
    {
        $target=match ($type) {
            ContentType::Offer => (new Offer())->setTitle($title)->setDescription('TEST Angebotsbeschreibung'),
            ContentType::Event => (new Event())->setTitle($title)->setDescription('TEST Eventbeschreibung')->setStartsAt($this->clock->now()->modify('+1 day'))->setEndsAt($this->clock->now()->modify('+1 day +2 hours'))->setCity('Haselünne'),
            ContentType::News => (new NewsArticle())->setTitle($title)->setContent('TEST Newsinhalt')->setStatus(str_contains($title,'approved') || str_contains($title,'fremd') ? NewsStatus::Published : NewsStatus::Draft),
            ContentType::Job => (new JobPosting())->setTitle($title)->setShortDescription('TEST Kurzbeschreibung')->setDescription('TEST Jobbeschreibung')->setCity('Haselünne')->setApplicationEmail('bewerbung@example.local')->setStatus(str_contains($title,'approved') || str_contains($title,'fremd') ? NewsStatus::Published : NewsStatus::Draft),
            default => throw new \LogicException(),
        };
        $target->setCompany($company); return $target;
    }
}
