<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\{ContentRevision, User};
use App\Enum\{ContentType, ModerationStatus, NewsStatus};
use App\Moderation\ContentModerationEvent;
use App\Repository\{CompanyRepository, ContentRevisionRepository, EventRepository, JobPostingRepository, NewsArticleRepository, OfferRepository};
use App\Service\{ContentDraftMapper, ContentModerationService};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ContentModerationServiceTest extends MemberDatabaseTestCase
{
    public function testSubmitAndEveryReviewDecisionPersistAuditFields(): void
    {
        $service = $this->service();
        $member = $this->user('membera');
        $editor = $this->user('editor');

        $draft = $this->revision(ContentType::Offer, ModerationStatus::Draft);
        $draft = $service->submit($draft->getId(), $member->getId(), $draft->getVersion());
        self::assertSame(ModerationStatus::PendingReview, $draft->getModerationStatus());
        self::assertSame($member->getId(), $draft->getSubmittedBy()?->getId());
        self::assertEquals($this->clock->now(), $draft->getSubmittedAt());

        $draft = $service->review($draft->getId(), $editor->getId(), $draft->getVersion(), ModerationStatus::Approved, 'Geprüft');
        self::assertSame(ModerationStatus::Approved, $draft->getModerationStatus());
        self::assertSame($editor->getId(), $draft->getReviewedBy()?->getId());
        self::assertSame('Geprüft', $draft->getReviewNote());
        self::assertEquals($this->clock->now(), $draft->getReviewedAt());
        self::assertSame(ModerationStatus::Approved, $draft->getTarget()->getModerationStatus());

        foreach ([[ContentType::Event, ModerationStatus::ChangesRequested], [ContentType::News, ModerationStatus::Rejected]] as [$type, $decision]) {
            $pending = $this->revision($type, ModerationStatus::PendingReview);
            $reviewed = $service->review($pending->getId(), $editor->getId(), $pending->getVersion(), $decision, 'Bitte überarbeiten');
            self::assertSame($decision, $reviewed->getModerationStatus());
            self::assertSame('Bitte überarbeiten', $reviewed->getReviewNote());
        }
    }

    public function testChangesRequestedCanBeEditedAndResubmittedWithoutChangingLiveContent(): void
    {
        $service = $this->service();
        $mapper = static::getContainer()->get(ContentDraftMapper::class);
        $member = $this->user('membera');
        $revision = $this->revision(ContentType::Offer, ModerationStatus::ChangesRequested);
        $target = $revision->getTarget();
        $liveTitle = $target->getTitle();
        $draft = $mapper->materialize($target, $revision->getPayload());
        $draft->setTitle('TEST überarbeiteter Titel');

        $saved = $service->save($target, $draft, $member->getId(), $revision->getVersion());
        self::assertSame($liveTitle, $target->getTitle());
        self::assertSame('TEST überarbeiteter Titel', $saved->getPayload()['title']);

        $submitted = $service->submit($saved->getId(), $member->getId(), $saved->getVersion());
        self::assertSame(ModerationStatus::PendingReview, $submitted->getModerationStatus());
        self::assertSame('TEST Prüfkommentar', $submitted->getReviewNote());
    }

    public function testStaleReviewAndChangedLiveVersionAreRejected(): void
    {
        $service = $this->service();
        $editor = $this->user('editor');
        $revision = $this->revision(ContentType::Offer, ModerationStatus::PendingReview);
        $oldVersion = $revision->getVersion();
        $service->review($revision->getId(), $editor->getId(), $oldVersion, ModerationStatus::ChangesRequested, 'Neu laden');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('inzwischen geändert');
        $service->review($revision->getId(), $editor->getId(), $oldVersion, ModerationStatus::Rejected, 'Zu spät');
    }

    public function testApprovalDetectsAnAdminChangeToTheLiveVersion(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $revision = $this->revision(ContentType::Offer, ModerationStatus::PendingReview);
        $revision->getTarget()->setDescription('TEST parallel durch Admin geändert');
        $em->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('freigegebene Stand wurde inzwischen geändert');
        $this->service()->review(
            $revision->getId(),
            $this->user('editor')->getId(),
            $revision->getVersion(),
            ModerationStatus::Approved,
            null,
        );
    }

    public function testDraftAndPendingContentStayPrivateUntilApprovalForEveryType(): void
    {
        $service = $this->service();
        $editor = $this->user('editor');

        foreach ([ContentType::Offer, ContentType::Event, ContentType::News, ContentType::Job] as $type) {
            $revision = $this->revision($type, ModerationStatus::PendingReview);
            $target = $revision->getTarget();
            self::assertFalse($this->isPublic($type, $target->getSlug()), $type->value.' was public before approval');

            $service->review($revision->getId(), $editor->getId(), $revision->getVersion(), ModerationStatus::Approved, null);
            self::assertTrue($this->isPublic($type, $target->getSlug()), $type->value.' was hidden after approval');
            self::assertSame(ModerationStatus::Approved, $target->getModerationStatus());
            if (in_array($type, [ContentType::News, ContentType::Job], true)) {
                self::assertSame(NewsStatus::Published, $target->getStatus());
            }
        }
    }

    public function testCompanyRevisionKeepsCurrentPublicVersionUntilApproval(): void
    {
        $revision = $this->revision(ContentType::Company, ModerationStatus::PendingReview);
        $company = $revision->getTarget();
        $repository = static::getContainer()->get(CompanyRepository::class);
        self::assertSame(
            $revision->getBaseFingerprint(),
            static::getContainer()->get(ContentDraftMapper::class)->fingerprint($company),
            'The company fixture revision must start from its current live version.',
        );

        self::assertNotSame('TEST eingereichte Profilbeschreibung', $repository->findPublicBySlug($company->getSlug())?->getDescription());
        $this->service()->review(
            $revision->getId(),
            $this->user('editor')->getId(),
            $revision->getVersion(),
            ModerationStatus::Approved,
            null,
        );
        self::assertSame('TEST eingereichte Profilbeschreibung', $repository->findPublicBySlug($company->getSlug())?->getDescription());
    }

    public function testPendingAndApprovedRevisionsCannotBeDiscardedByMembers(): void
    {
        $member = $this->user('membera');
        foreach ([ModerationStatus::PendingReview, ModerationStatus::Approved] as $status) {
            $revision = $this->revision(ContentType::Offer, $status);
            try {
                $this->service()->discard($revision->getId(), $member->getId(), $revision->getVersion());
                self::fail('A non-editable revision was discarded.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('Nur bearbeitbare Entwürfe', $exception->getMessage());
            }
        }
    }

    public function testDeletingUsersRetainsTheRevisionAuditWithNullableReferences(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $revision = $this->revision(ContentType::Offer, ModerationStatus::PendingReview);
        $revisionId = $revision->getId();
        $submitter = $revision->getSubmittedBy();
        self::assertNotNull($submitter);

        $em->remove($submitter);
        $em->flush();
        $em->clear();

        $retained = static::getContainer()->get(ContentRevisionRepository::class)->find($revisionId);
        self::assertInstanceOf(ContentRevision::class, $retained);
        self::assertNull($retained->getSubmittedBy());
        self::assertNotNull($retained->getSubmittedAt());
    }

    public function testSuccessfulTransitionsDispatchTheNotificationFoundationAfterCommit(): void
    {
        $dispatched = [];
        static::getContainer()->get(EventDispatcherInterface::class)->addListener(
            ContentModerationEvent::class,
            static function (ContentModerationEvent $event) use (&$dispatched): void { $dispatched[] = $event; },
        );
        $revision = $this->revision(ContentType::Job, ModerationStatus::Draft);
        $member = $this->user('membera');

        $this->service()->submit($revision->getId(), $member->getId(), $revision->getVersion());

        self::assertCount(1, $dispatched);
        self::assertSame($revision->getId(), $dispatched[0]->revisionId);
        self::assertSame(ContentType::Job, $dispatched[0]->type);
        self::assertSame(ModerationStatus::PendingReview, $dispatched[0]->status);
        self::assertSame($member->getId(), $dispatched[0]->actorId);
    }

    private function revision(ContentType $type, ModerationStatus $status): ContentRevision
    {
        $rows = static::getContainer()->get(ContentRevisionRepository::class)->findBy([
            'type' => $type,
            'moderationStatus' => $status,
        ]);
        foreach ($rows as $row) {
            return $row;
        }
        self::fail(sprintf('No %s revision in state %s', $type->value, $status->value));
    }

    private function user(string $name): User
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => $name.'@example.local']);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function service(): ContentModerationService
    {
        return static::getContainer()->get(ContentModerationService::class);
    }

    private function isPublic(ContentType $type, string $slug): bool
    {
        return match ($type) {
            ContentType::Offer => static::getContainer()->get(OfferRepository::class)->findPublicBySlug($slug) !== null,
            ContentType::Event => static::getContainer()->get(EventRepository::class)->findPublicBySlug($slug) !== null,
            ContentType::News => static::getContainer()->get(NewsArticleRepository::class)->findPublicBySlug($slug) !== null,
            ContentType::Job => static::getContainer()->get(JobPostingRepository::class)->findPublicBySlug($slug) !== null,
            ContentType::Company => throw new \LogicException(),
        };
    }
}
