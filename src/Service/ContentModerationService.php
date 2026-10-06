<?php

declare(strict_types=1);
namespace App\Service;
use App\Entity\{Company, ContentRevision, Event, JobPosting, NewsArticle, User};
use App\Enum\{ModerationStatus, NewsStatus};
use App\Repository\ContentRevisionRepository;
use App\Security\{ContentAccess, ContentOwnershipVoter};
use App\Moderation\ContentModerationEvent;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ContentModerationService
{
    public function __construct(private EntityManagerInterface $em, private ContentDraftMapper $mapper, private ContentRevisionRepository $revisions, private ContentAccess $access, private ClockInterface $clock, private ValidatorInterface $validator, private EventSchedule $schedule, private NewsPublishing $publishing, private EventDispatcherInterface $events) {}
    public function save(object $target, object $draft, int $actorId, ?int $expectedVersion): ContentRevision
    {
        return $this->transaction(function () use ($target,$draft,$actorId,$expectedVersion): ContentRevision {
            $actor = $this->actor($actorId);
            if ($target->getId() !== null) { $this->em->refresh($target, LockMode::PESSIMISTIC_WRITE); }
            $this->authorize($actor,$target,ContentOwnershipVoter::EDIT);
            $payload = $this->mapper->capture($draft);
            if ($target->getId() === null) { $target->setSlug($draft->getSlug()); }
            $validated = $this->mapper->materialize($target,$payload);
            $this->validate($validated);
            $revision = $target->getId() === null ? null : $this->revisions->forTarget($target);
            if ($revision !== null) { $this->em->refresh($revision,LockMode::PESSIMISTIC_WRITE); $this->version($revision,$expectedVersion); }
            elseif ($expectedVersion !== null) { throw new \DomainException('Der Entwurf wurde inzwischen entfernt. Bitte neu laden.'); }
            if ($revision?->getModerationStatus() === ModerationStatus::Approved) { $this->em->remove($revision); $this->em->flush(); $revision = null; }
            if ($target->getId() === null) {
                $target->beginMemberDraft(); $this->mapper->apply($target,$payload); $this->em->persist($target); $this->em->flush();
            }
            if ($revision === null) { $revision = new ContentRevision($target,$payload,$this->mapper->imageNames($payload),$this->mapper->fingerprint($target),$this->clock->now()); $this->em->persist($revision); }
            else { $revision->saveDraft($payload,$this->mapper->imageNames($payload),$this->clock->now()); }
            $this->em->flush(); return $revision;
        });
    }
    public function submit(int $id, int $actorId, int $expectedVersion): ContentRevision
    {
        $revision = $this->transaction(function () use ($id,$actorId,$expectedVersion): ContentRevision {
            [$revision,$target,$actor] = $this->locked($id,$actorId); $this->version($revision,$expectedVersion); $this->authorize($actor,$target,ContentOwnershipVoter::SUBMIT);
            $this->validate($this->mapper->materialize($target,$revision->getPayload()));
            $revision->submit($actor,$this->clock->now()); $this->em->flush(); return $revision;
        });
        $this->notify($revision,$actorId); return $revision;
    }
    public function review(int $id, int $actorId, int $expectedVersion, ModerationStatus $result, ?string $note): ContentRevision
    {
        if (!in_array($result,[ModerationStatus::Approved,ModerationStatus::ChangesRequested,ModerationStatus::Rejected],true)) { throw new \DomainException('Ungültige Freigabeaktion.'); }
        $revision = $this->transaction(function () use ($id,$actorId,$expectedVersion,$result,$note): ContentRevision {
            [$revision,$target,$actor] = $this->locked($id,$actorId); $this->version($revision,$expectedVersion); $this->authorize($actor,$target,ContentOwnershipVoter::REVIEW);
            if ($result === ModerationStatus::Approved) {
                if (!hash_equals($revision->getBaseFingerprint(),$this->mapper->fingerprint($target))) { throw new \DomainException('Der freigegebene Stand wurde inzwischen geändert. Bitte Änderungen anfordern und einen neuen Entwurf erstellen lassen.'); }
                $this->validate($this->mapper->materialize($target,$revision->getPayload()));
            }
            $revision->review($result,$actor,$this->clock->now(),$note);
            if ($result === ModerationStatus::Approved) {
                $this->mapper->apply($target,$revision->getPayload());
                $target->recordApproval($revision->getSubmittedBy(),$revision->getSubmittedAt(),$actor,$this->clock->now(),$revision->getReviewNote());
                if ($target instanceof Event) { $this->schedule->synchronize($target); }
                if ($target instanceof NewsArticle || $target instanceof JobPosting) {
                    if ($target->getStatus() === NewsStatus::Draft) { $target->setStatus($target->getPublishedAt() !== null && $target->getPublishedAt() > $this->clock->now() ? NewsStatus::Scheduled : NewsStatus::Published); }
                    $this->publishing->prepareForSave($target);
                }
                $this->validate($target); $target->touch();
            }
            $this->em->flush(); return $revision;
        });
        $this->notify($revision,$actorId); return $revision;
    }
    /** Discard editable changes; live targets survive. Only genuinely unapproved targets may be removed. */
    public function discard(int $id, int $actorId, int $expectedVersion): void
    {
        $this->transaction(function () use ($id,$actorId,$expectedVersion): void {
            [$revision,$target,$actor] = $this->locked($id,$actorId); $this->version($revision,$expectedVersion); $this->authorize($actor,$target,ContentOwnershipVoter::EDIT);
            if (!$revision->getModerationStatus()->isEditable()) { throw new \DomainException('Nur bearbeitbare Entwürfe können entfernt werden.'); }
            $this->em->remove($revision);
            if (!$target instanceof Company && !$target->isModerationApproved()) { $this->em->remove($target); }
            $this->em->flush();
        });
    }
    private function locked(int $id, int $actorId): array
    {
        $actor = $this->actor($actorId); $revision = $this->em->find(ContentRevision::class,$id) ?? throw new \DomainException('Der Entwurf ist nicht mehr verfügbar.');
        // Global lock order is target before revision for edit/submit/review/discard alike.
        $target = $revision->getTarget(); $this->em->refresh($target,LockMode::PESSIMISTIC_WRITE); $this->em->refresh($revision,LockMode::PESSIMISTIC_WRITE);
        $company = $target instanceof Company ? $target : $target->getCompany();
        if ($company?->getId() !== $revision->getOwnerCompany()->getId()) { throw new \DomainException('Die Unternehmenszuordnung wurde inzwischen geändert.'); }
        return [$revision,$target,$actor];
    }
    private function actor(int $id): User { $actor = $this->em->find(User::class,$id) ?? throw new AccessDeniedException(); $this->em->refresh($actor,LockMode::PESSIMISTIC_READ); return $actor; }
    private function authorize(User $actor, object $target, string $permission): void { if (!$this->access->allows($actor,$target,$permission,true)) { throw new AccessDeniedException(); } }
    private function version(ContentRevision $revision, ?int $expected): void { if ($expected !== $revision->getVersion()) { throw new \DomainException('Der Entwurf wurde inzwischen geändert. Bitte die Seite neu laden.'); } }
    private function validate(object $entity): void { $errors = $this->validator->validate($entity); if (count($errors) !== 0) { throw new \DomainException('Bitte die Inhalte prüfen: '.$errors[0]->getMessage()); } }
    private function transaction(callable $operation): mixed
    {
        $db = $this->em->getConnection(); $db->beginTransaction();
        try { $result = $operation(); $db->commit(); return $result; }
        catch (\Throwable $exception) { if ($db->isTransactionActive()) { $db->rollBack(); } if ($this->em->isOpen()) { $this->em->clear(); } throw $exception; }
    }
    private function notify(ContentRevision $revision, int $actor): void { $this->events->dispatch(new ContentModerationEvent($revision->getId(),$revision->getType(),$revision->getModerationStatus(),$actor,$this->clock->now())); }
}
