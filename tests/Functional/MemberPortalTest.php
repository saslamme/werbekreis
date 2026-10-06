<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\ContentRevision;
use App\Enum\ContentType;
use App\Enum\ModerationStatus;
use App\Repository\ContentRevisionRepository;
final class MemberPortalTest extends MemberDatabaseTestCase
{
    public function testDashboardAndListsScopeCompaniesAndNoCompanyIsSafe(): void
    {
        $this->login('membera'); $this->client->request('GET','/member'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body','Musterladen Hasebogen'); self::assertStringNotContainsString('Beispielcafé Uferpause',$this->client->getResponse()->getContent());
        foreach (ContentType::cases() as $type) { $this->client->request('GET','/member/'.$type->value); self::assertResponseIsSuccessful(); self::assertStringNotContainsString('TEST fremd',$this->client->getResponse()->getContent()); }
        $this->login('member'); $this->client->request('GET','/member'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body','Ihrem Benutzerkonto ist noch kein Unternehmen zugeordnet.');
    }
    public function testCompanyEditIsDetachedAndForbiddenFlagsAreRejected(): void
    {
        $this->login('memberb'); $company=$this->company('Beispielcafé Uferpause'); $crawler=$this->client->request('GET','/member/companies/'.$company->getId().'/edit'); self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[name="member_company[active]"]');
        self::assertSelectorNotExists('[name="member_company[featured]"]');
        self::assertSelectorNotExists('[name="member_company[slug]"]');
        $form=$crawler->selectButton('Entwurf speichern')->form(['member_company[description]'=>'TEST neue Beschreibung']); $this->client->submit($form); self::assertResponseStatusCodeSame(303);
        self::assertNotSame('TEST neue Beschreibung',$this->company('Beispielcafé Uferpause')->getDescription());
    }

    public function testMemberCanCreateEditAndSubmitEveryOwnedContentType(): void
    {
        $this->login('membera');
        $company = $this->company('Musterladen Hasebogen');
        $forms = [
            ContentType::Offer->value => [
                'member_offer[title]' => 'TEST Mitglied Angebot neu',
                'member_offer[description]' => 'Ein sicherer Angebotsentwurf',
            ],
            ContentType::Event->value => [
                'member_event[title]' => 'TEST Mitglied Veranstaltung neu',
                'member_event[description]' => 'Ein sicherer Veranstaltungsentwurf',
                'member_event[startsAt]' => '2030-05-03T10:00',
                'member_event[endsAt]' => '2030-05-03T12:00',
            ],
            ContentType::News->value => [
                'member_news[title]' => 'TEST Mitglied News neu',
                'member_news[content]' => 'Ein sicherer Newsentwurf',
            ],
            ContentType::Job->value => [
                'member_job[title]' => 'TEST Mitglied Job neu',
                'member_job[shortDescription]' => 'Kurze Stellenbeschreibung',
                'member_job[description]' => 'Ein sicherer Stellenentwurf',
            ],
        ];

        foreach ([ContentType::Offer, ContentType::Event, ContentType::News, ContentType::Job] as $type) {
            $crawler = $this->client->request('GET', '/member/'.$type->value.'/new?company='.$company->getId());
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('[name$="[company]"]');
            self::assertSelectorNotExists('[name$="[active]"]');
            self::assertSelectorNotExists('[name$="[featured]"]');
            self::assertSelectorNotExists('[name$="[status]"]');
            $this->client->submit($crawler->selectButton('Entwurf speichern')->form($forms[$type->value]));
            self::assertResponseStatusCodeSame(303);

            $crawler = $this->client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'Entwurf');
            $this->client->submit($crawler->selectButton('Zur Freigabe einreichen')->form());
            self::assertResponseStatusCodeSame(303);

            static::getContainer()->get('doctrine')->getManager()->clear();
            $title = array_values(array_filter($forms[$type->value], static fn (string $field): bool => str_ends_with($field, '[title]'), ARRAY_FILTER_USE_KEY))[0];
            $target = static::getContainer()->get('doctrine')->getRepository($type->entityClass())->findOneBy(['title' => $title]);
            self::assertNotNull($target);
            self::assertSame($company->getId(), $target->getCompany()->getId());
            $revision = static::getContainer()->get(ContentRevisionRepository::class)->forTarget($target);
            self::assertInstanceOf(ContentRevision::class, $revision);
            self::assertSame(ModerationStatus::PendingReview, $revision->getModerationStatus());
            self::assertNotSame(ModerationStatus::Approved, $target->getModerationStatus());
        }
    }

    public function testCompanyChooserAndCreateCompanyIdAreOwnershipScoped(): void
    {
        $this->login('membera');
        $own = $this->company('Musterladen Hasebogen');
        $foreign = $this->company('Beispielcafé Uferpause');
        $this->client->request('GET', '/member/offers/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $own->getName());
        self::assertStringNotContainsString($foreign->getName(), $this->client->getResponse()->getContent());

        $this->client->request('GET', '/member/offers/new?company='.$foreign->getId());
        self::assertResponseStatusCodeSame(403);
    }
    public function testAllForeignEditIdsAreDenied(): void
    {
        $this->login('membera'); $company=$this->company('Beispielcafé Uferpause'); $this->client->request('GET','/member/companies/'.$company->getId().'/edit'); self::assertResponseStatusCodeSame(403);
        foreach ([ContentType::Offer,ContentType::Event,ContentType::News,ContentType::Job] as $type) { $foreign=static::getContainer()->get('doctrine')->getRepository($type->entityClass())->findOneBy(['title'=>'TEST fremd '.$type->value]); $this->client->request('GET','/member/'.$type->value.'/'.$foreign->getId().'/edit'); self::assertResponseStatusCodeSame(403); }
    }
}
