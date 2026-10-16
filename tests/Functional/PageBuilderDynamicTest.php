<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\{ContentElement,ContentPage};
use App\Service\ContentElementViewFactory;
use Doctrine\ORM\EntityManagerInterface;
final class PageBuilderDynamicTest extends EventDatabaseTestCase
{
    protected function tearDown(): void {
        $em=static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement("DELETE FROM ContentElement WHERE anchor LIKE 'builder-dynamic-%'");
        parent::tearDown();
    }
    public function testLinkedCompanyAndEventFollowPublicVisibilityAndRemainLinkedInAdmin(): void {
        $em=static::getContainer()->get(EntityManagerInterface::class);
        $factory=static::getContainer()->get(ContentElementViewFactory::class);
        $page=$em->getRepository(ContentPage::class)->findOneBy(['code'=>'about']);
        $company=$this->company('Musterladen Hasebogen');$event=$this->event();
        $companyElement=(new ContentElement('company'))->setPage($page)->setAnchor('builder-dynamic-company')->setLinkedCompany($company);
        $eventElement=(new ContentElement('event'))->setPage($page)->setAnchor('builder-dynamic-event')->setLinkedEvent($event);
        self::assertNotNull($factory->create($companyElement));self::assertNotNull($factory->create($eventElement));
        $em->persist($companyElement);$em->persist($eventElement);$em->flush();$companyId=$companyElement->getId();$eventId=$eventElement->getId();
        $this->client->request('GET','/werbekreis/ueber-uns');self::assertSelectorExists('#builder-dynamic-company .company-card');self::assertSelectorExists('#builder-dynamic-event .event-card');
        $em=static::getContainer()->get(EntityManagerInterface::class);$factory=static::getContainer()->get(ContentElementViewFactory::class);
        $companyElement=$em->find(ContentElement::class,$companyId);$eventElement=$em->find(ContentElement::class,$eventId);
        $company=$companyElement->getLinkedCompany();$event=$eventElement->getLinkedEvent();
        $company->setActive(false);$event->setActive(false);$em->flush();
        self::assertNull($factory->create($companyElement));self::assertNull($factory->create($eventElement));
        self::assertSame($company,$companyElement->getLinkedCompany());self::assertSame($event,$eventElement->getLinkedEvent());
        $company->setActive(true);$company->beginMemberDraft();$event->setActive(true);$event->beginMemberDraft();$em->flush();
        self::assertNull($factory->create($companyElement));self::assertNull($factory->create($eventElement));
        $this->client->request('GET','/werbekreis/ueber-uns');self::assertSelectorNotExists('#builder-dynamic-company, #builder-dynamic-event');
    }
}
