<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ContentRevision;
use App\Enum\{ContentType, ModerationStatus};
use App\Repository\ContentRevisionRepository;

final class ReviewQueueTest extends MemberDatabaseTestCase
{
    public function testOnlyEditorsAndAdminsCanUseThePendingReviewQueue(): void
    {
        $this->login('membera');
        $this->client->request('GET', '/admin/reviews');
        self::assertResponseStatusCodeSame(403);

        foreach (['editor', 'admin'] as $role) {
            $this->login($role);
            $this->client->request('GET', '/admin/reviews');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'TEST offers pending_review');
            self::assertSelectorTextContains('body', 'Musterladen Hasebogen');
        }
    }

    public function testEditorCanApproveRequestChangesAndRejectViaCsrfProtectedForms(): void
    {
        $this->login('editor');

        foreach ([
            [ContentType::Offer, ModerationStatus::Approved, ''],
            [ContentType::Event, ModerationStatus::ChangesRequested, 'Bitte Datum prüfen'],
            [ContentType::News, ModerationStatus::Rejected, 'Passt nicht zum Portal'],
        ] as [$type, $decision, $note]) {
            $revision = $this->pending($type);
            $crawler = $this->client->request('GET', '/admin/reviews/'.$revision->getId());
            self::assertResponseIsSuccessful();
            $form = $crawler->selectButton('Entscheidung speichern')->form([
                'review_decision[decision]' => $decision->value,
                'review_decision[note]' => $note,
            ]);
            $this->client->submit($form);
            self::assertResponseStatusCodeSame(303);

            static::getContainer()->get('doctrine')->getManager()->clear();
            $reviewed = static::getContainer()->get(ContentRevisionRepository::class)->find($revision->getId());
            self::assertSame($decision, $reviewed->getModerationStatus());
            self::assertSame($note === '' ? null : $note, $reviewed->getReviewNote());
            self::assertSame('editor@example.local', $reviewed->getReviewedBy()?->getEmail());
        }
    }

    public function testRejectRequiresAVisibleReason(): void
    {
        $this->login('editor');
        $revision = $this->pending(ContentType::Job);
        $crawler = $this->client->request('GET', '/admin/reviews/'.$revision->getId());
        $form = $crawler->selectButton('Entscheidung speichern')->form([
            'review_decision[decision]' => ModerationStatus::Rejected->value,
            'review_decision[note]' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Bitte einen verständlichen Kommentar');
        static::getContainer()->get('doctrine')->getManager()->clear();
        self::assertSame(ModerationStatus::PendingReview, static::getContainer()->get(ContentRevisionRepository::class)->find($revision->getId())->getModerationStatus());
    }

    private function pending(ContentType $type): ContentRevision
    {
        $revision = static::getContainer()->get(ContentRevisionRepository::class)->findOneBy([
            'type' => $type,
            'moderationStatus' => ModerationStatus::PendingReview,
        ]);
        self::assertInstanceOf(ContentRevision::class, $revision);

        return $revision;
    }
}
