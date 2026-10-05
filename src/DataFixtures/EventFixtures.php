<?php

declare(strict_types=1);
namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\EventCategory;
use App\Enum\EventRecurrence;
use App\Repository\CompanyRepository;
use App\Service\CompanyImageStorage;
use App\Service\DirectorySlugger;
use App\Service\EventDateRangeResolver;
use App\Service\EventSchedule;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Fictional, relative-clock development/test data. */
final class EventFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly CompanyRepository $companies, private readonly DirectorySlugger $slugs, private readonly EventDateRangeResolver $dates,
        private readonly EventSchedule $schedule, private readonly CompanyImageStorage $storage, private readonly DirectoryImageFixtures $images) {}
    public function getDependencies(): array { return [DirectoryFixtures::class]; }
    public function load(ObjectManager $manager): void
    {
        $categories = [];
        foreach (['Shopping', 'Musik', 'Kultur', 'Familie', 'Gastronomie', 'Sport', 'Märkte', 'Vereine', 'Sonstiges'] as $index => $name) {
            $category = (new EventCategory())->setName($name)->setPosition($index)->setIcon('fa-calendar-days');
            $this->slugs->assign($category); $manager->persist($category); $manager->flush(); $categories[] = $category;
        }
        $inactive = (new EventCategory())->setName('Fiktive inaktive Kategorie')->setActive(false); $this->slugs->assign($inactive); $manager->persist($inactive); $manager->flush();
        $today = $this->dates->localNow()->setTime(0, 0);
        $shop = $this->companies->findOneBy(['name' => 'Musterladen Hasebogen']);
        $cafe = $this->companies->findOneBy(['name' => 'Beispielcafé Uferpause']);
        $rows = [
            ['Beispielabend heute', $today->setTime(18, 0), $today->setTime(22, 0), $cafe, [1, 4], true, true, false],
            ['Familientag morgen', $today->modify('+1 day'), $today->modify('+1 day'), null, [3], true, true, true],
            ['Fiktiver Wochenendmarkt', $today->modify('monday this week')->modify('+5 days')->setTime(10, 0), $today->modify('monday this week')->modify('+5 days')->setTime(16, 0), $shop, [0, 6], true, false, false],
            ['Kultur im nächsten Monat', $today->modify('first day of next month')->setTime(19, 0), $today->modify('first day of next month')->setTime(21, 0), null, [2], true, true, false],
            ['Mehrtagige Beispielausstellung', $today->modify('+2 days'), $today->modify('+4 days'), $shop, [2], true, false, true],
            ['Vergangener Beispielabend', $today->modify('-2 days')->setTime(18, 0), $today->modify('-2 days')->setTime(22, 0), $cafe, [1], true, true, false],
            ['Inaktive Beispielveranstaltung', $today->modify('+1 day')->setTime(10, 0), $today->modify('+1 day')->setTime(12, 0), $shop, [0], false, true, false],
            ['Abgesagtes Beispielkonzert', $today->modify('+6 days')->setTime(18, 0), $today->modify('+6 days')->setTime(20, 0), null, [1], true, false, false],
            ['Wöchentliche Ideenrunde', $today->modify('-7 days')->setTime(17, 0), $today->modify('-7 days')->setTime(19, 0), $shop, [7], true, false, false],
            ['Sportliche Begegnung', $today->modify('+3 days')->setTime(9, 0), $today->modify('+3 days')->setTime(11, 0), null, [5], true, false, false],
            ['Langer offener Nachmittag', $today->modify('-1 day')->setTime(14, 0), $today->modify('+1 day')->setTime(16, 0), $shop, [0, 3], true, false, false],
            ['Freier Beispieltalk', $today->modify('+5 days')->setTime(18, 0), $today->modify('+5 days')->setTime(19, 0), null, [8], true, false, false],
        ];
        foreach ($rows as $index => [$title, $start, $end, $company, $categoryIds, $active, $featured, $allDay]) {
            $event = (new Event())->setTitle($title)->setCompany($company)->setStartsAt($start->setTimezone(new \DateTimeZone('UTC')))->setEndsAt($end->setTimezone(new \DateTimeZone('UTC')))
                ->setActive($active)->setFeatured($featured)->setAllDay($allDay)->setLocationName('Fiktiver Begegnungsort')->setStreet('Fiktive Beispielstraße')->setHouseNumber('10')->setPostalCode('49740')->setCity('Haselünne')
                ->setShortDescription('Fiktive Beispielveranstaltung – kein realer Termin.')->setDescription('Dieses Beispiel dient ausschließlich der Entwicklung und automatisierten Tests.');
            foreach ($categoryIds as $id) { $event->addCategory($categories[$id]); }
            if ($company === null) { $event->setOrganizerName('Fiktive Initiative Stadtleben')->setOrganizerEmail('initiative@example.local')->setOrganizerWebsite('https://example.org'); }
            if ($index === 0) { $event->setLatitude(52.674)->setLongitude(7.482); }
            if ($index === 1) { $event->setFreeAdmission(true); }
            if ($index === 7) { $event->setCancelled(true)->setCancellationNotice('Fiktive Absage zur Demonstration.'); }
            if ($index === 8) { $event->setRecurrenceType(EventRecurrence::Weekly)->setRecurrenceUntil($today->modify('+21 days')); }
            $this->slugs->assign($event); $this->schedule->synchronize($event);
            if ($index < 2) { $event->setImageAlt('Fiktives Veranstaltungsmotiv: '.$title); $this->storage->save($event, $this->images->png([110, 120 + $index * 30, 90], 800, 450)); }
            else { $manager->persist($event); $manager->flush(); }
        }
    }
}
