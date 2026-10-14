<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\Event;
use App\Entity\EventCategory;
use App\Enum\EventRecurrence;
use App\Service\CompanyImageStorage;
use App\Service\DirectorySlugger;
use App\Service\EventDateRangeResolver;
use App\Service\EventSchedule;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Invented events, venues and organizers; all dates follow the shared Clock. Never load into production. */
final class EventFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly DirectorySlugger $slugs, private readonly EventDateRangeResolver $dates,
        private readonly EventSchedule $schedule, private readonly CompanyImageStorage $storage, private readonly DirectoryImageFixtures $images)
    {
    }

    public function getDependencies(): array
    {
        return [DirectoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $categories = [];
        foreach (['Shopping', 'Musik', 'Kultur', 'Familie', 'Gastronomie', 'Sport', 'Märkte', 'Vereine', 'Sonstiges'] as $index => $name) {
            $category = (new EventCategory())->setName($name)->setPosition($index)->setIcon('fa-calendar-days');
            $this->slugs->assign($category);
            $manager->persist($category);
            $manager->flush();
            $categories[] = $category;
        }
        $inactive = (new EventCategory())->setName('Archiv Veranstaltungen')->setActive(false);
        $this->slugs->assign($inactive);
        $manager->persist($inactive);
        $manager->flush();
        $today = $this->dates->localNow()->setTime(0, 0);
        $rows = self::data($today);
        foreach ($rows as $index => [$title, $start, $end, $companyName, $categoryIds, $active, $featured, $allDay, $teaser, $description]) {
            $company = $companyName !== null ? $this->getReference($companyName, Company::class) : null;
            $event = (new Event())->setTitle($title)->setCompany($company)->setStartsAt($start->setTimezone(new \DateTimeZone('UTC')))->setEndsAt($end->setTimezone(new \DateTimeZone('UTC')))
                ->setActive($active)->setFeatured($featured)->setAllDay($allDay)->setLocationName($company?->getName() ?? ($index === 7 ? 'Uferbühne Lichtbogen' : 'Lichtbogenplatz'))
                ->setStreet($company?->getStreet() ?? 'Lichtbogenweg')->setHouseNumber($company?->getHouseNumber() ?? '15')->setPostalCode('49740')->setCity('Haselünne')
                ->setShortDescription($teaser)->setDescription($description);
            foreach ($categoryIds as $id) {
                $event->addCategory($categories[$id]);
            }
            if ($company === null) {
                $event->setOrganizerName('Initiative Innenstadtleben')->setOrganizerEmail('stadtleben@example.org')->setOrganizerWebsite('https://stadtleben.example.org');
            }
            if ($index === 0 || $index === 3 || $index === 9) {
                $event->setLatitude(52.674 + $index * 0.0003)->setLongitude(7.482 + $index * 0.0004);
            }
            if ($index === 1) {
                $event->setFreeAdmission(true);
            }
            if ($index === 7) {
                $event->setCancelled(true)->setCancellationNotice('Der Akustikabend kann wegen Reparaturarbeiten an der Bühne leider nicht stattfinden. Ein Ersatztermin wird rechtzeitig bekannt gegeben.');
            }
            if ($index === 8) {
                $event->setRecurrenceType(EventRecurrence::Weekly)->setRecurrenceUntil($today->modify('+21 days'));
            }
            $this->slugs->assign($event);
            $this->schedule->synchronize($event);
            if ($index < 5) {
                $event->setImageAlt('Grafisches Veranstaltungsmotiv: '.$title);
                $this->storage->save($event, $this->images->png([110, 105 + $index * 20, 95 + $index * 15], 900, 500, $index + 1));
            } else {
                $manager->persist($event);
                $manager->flush();
            }
        }
    }

    /** Data definitions only; safe to inspect without loading fixtures or connecting to a database. */
    public static function data(\DateTimeImmutable $today): array
    {
        return [
            ['Genussabend bei Korn & Kanne', $today->setTime(18, 0), $today->setTime(22, 0), 'Korn & Kanne', [1, 4], true, true, false,
                'Ein geselliger Abend mit kleinen Gerichten, Kaffee und akustischer Musik.', 'Unsere Küche stellt eine Auswahl zum Teilen zusammen. Zwischen den Gängen bleibt Zeit zum Gespräch und für musikalische Begleitung. Bitte reservieren Sie Ihren Tisch im Café.'],
            ['Familien-Samstag in der Innenstadt', $today->modify('next saturday'), $today->modify('next saturday'), null, [3], true, true, true,
                'Spielstationen, kleine Entdeckungen und gemeinsame Zeit für Familien.', 'Entdecken Sie Mitmachstationen rund um den Lichtbogenplatz. Geschäfte und Gastgeber begleiten den Tag mit kleinen Aktionen; eine gemütliche Pause lässt sich unterwegs einplanen.'],
            ['Herbstmarkt in der Innenstadt', $today->modify('+5 days')->setTime(10, 0), $today->modify('+5 days')->setTime(16, 0), 'Hase & Heim', [0, 6], true, false, false,
                'Saisonale Ideen, Handgemachtes und herbstliche Farben beim Stadtbummel.', 'Am Lichtbogenplatz laden Marktstände und teilnehmende Geschäfte zum Stöbern ein. Entdecken Sie Dekoration, kleine Geschenkideen und regionale Genüsse bei einem entspannten Rundgang.'],
            ['Late-Night-Shopping in Haselünne', $today->modify('first day of next month')->setTime(18, 0), $today->modify('first day of next month')->setTime(22, 0), null, [0, 2], true, true, false,
                'Länger einkaufen, gemeinsam genießen und die Innenstadt am Abend erleben.', 'Teilnehmende Geschäfte öffnen bis in den Abend und begleiten den Stadtbummel mit Aktionen und kleinen Überraschungen. Gastronomische Angebote und Musik schaffen Raum für eine Pause.'],
            ['Haselünner Adventszauber', $today->modify('+12 days'), $today->modify('+14 days'), 'Blumenzeit am Markt', [2, 3, 6], true, false, true,
                'Drei Tage voller Licht, Geschenkideen und gemütlicher Begegnungen.', 'Ein geschmückter Lichtbogenplatz, kleine Stände und kreative Mitmachangebote laden zum Verweilen ein. An allen drei Tagen gibt es wechselnde Programmpunkte für Familien und Besucher der Innenstadt.'],
            ['Unternehmerabend: Sichtbar im Netz', $today->modify('-2 days')->setTime(18, 0), $today->modify('-2 days')->setTime(22, 0), null, [7, 8], true, true, false,
                'Lokale Betriebe tauschen Erfahrungen zu Websites und digitalen Schaufenstern aus.', 'Kurze Impulse und offene Gesprächsrunden verbinden praktische Ideen mit dem Alltag kleiner Betriebe. Im Mittelpunkt stehen verständliche Inhalte, gute Erreichbarkeit und gemeinsame Aktionen.'],
            ['Herbstliche Stadtführung', $today->modify('+1 day')->setTime(10, 0), $today->modify('+1 day')->setTime(12, 0), 'Landhaus Uferblick', [2], false, true, false,
                'Die Stadt bei einem entspannten Spaziergang aus neuen Blickwinkeln entdecken.', 'Unsere begleitete Runde verbindet kleine Geschichten mit ruhigen Wegen durch die Innenstadt. Treffpunkt ist das Landhaus; wetterfeste Kleidung und bequeme Schuhe sind empfehlenswert.'],
            ['Akustikabend am Haseufer', $today->modify('+6 days')->setTime(18, 0), $today->modify('+6 days')->setTime(20, 0), null, [1], true, false, false,
                'Gitarrenklänge und ruhige Songs unter freiem Himmel.', 'Ein kleines akustisches Programm lädt zum Zuhören und Verweilen ein. Sitzgelegenheiten stehen in begrenzter Zahl bereit, eigene Decken können mitgebracht werden.'],
            ['Wochenmarkt-Spezial', $today->modify('-7 days')->setTime(17, 0), $today->modify('-7 days')->setTime(19, 0), 'Blumenzeit am Markt', [6, 7], true, false, false,
                'Eine wöchentliche Runde mit wechselnden Ideen rund um saisonale Produkte.', 'Bei unserem Markt-Spezial stellen wechselnde Gastgeber ihre saisonalen Lieblingsstücke vor. Besucher können Fragen stellen, Anregungen mitnehmen und miteinander ins Gespräch kommen.'],
            ['Fahrrad-Sicherheitscheck', $today->modify('+3 days')->setTime(9, 0), $today->modify('+3 days')->setTime(11, 0), 'Rad & Reise', [5], true, false, false,
                'Licht, Bremsen und Sichtbarkeit für sichere Wege im Alltag.', 'Unser Werkstattteam zeigt, welche Kontrollen Sie selbst durchführen können. Bringen Sie Ihr Fahrrad mit und erhalten Sie Hinweise zur Pflege und zu sinnvollem Zubehör. Reparaturen werden separat vereinbart.'],
            ['Kreativnachmittag für Familien', $today->modify('-1 day')->setTime(14, 0), $today->modify('+1 day')->setTime(16, 0), 'Hase & Heim', [0, 3], true, false, false,
                'An drei Nachmittagen gemeinsam gestalten, ausprobieren und kleine Geschenke basteln.', 'Der offene Kreativbereich bietet jeden Nachmittag Papier, Farben und saisonale Materialien. Familien können ohne feste Kurszeit vorbeischauen und eigene Ideen umsetzen. Kinder nehmen in Begleitung eines Erwachsenen teil.'],
            ['Netzwerkabend des Werbekreises', $today->modify('+5 days')->setTime(18, 0), $today->modify('+5 days')->setTime(19, 0), null, [7, 8], true, false, false,
                'Betriebe kennenlernen, Erfahrungen teilen und gemeinsame Aktionen anstoßen.', 'Ein kurzer Überblick über kommende Vorhaben eröffnet den Abend. Anschließend gibt es Zeit für Gespräche und konkrete Ideen zur Zusammenarbeit in der Innenstadt. Anmeldung per E-Mail erleichtert die Planung.'],
        ];
    }
}
