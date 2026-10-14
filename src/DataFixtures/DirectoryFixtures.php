<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Company;
use App\Entity\ContactPerson;
use App\Entity\OpeningHour;
use App\Service\DirectorySlugger;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Entirely invented businesses, people, streets and contacts, with no real-world affiliation.
 * Reserved example domains and non-dialable local numbers prevent contacting real businesses.
 * Coordinates are illustrative only. Never load into a production database.
 */
final class DirectoryFixtures extends Fixture implements DependentFixtureInterface
{
    public const COMPANY_NAMES = [
        'Hase & Heim', 'Korn & Kanne', 'Werkraum Hasetal', 'Vitalpunkt Haselünne', 'Rad & Reise',
        'Modehaus Linden', 'Elektro Nordlicht', 'Blumenzeit am Markt', 'Stadtblick Optik', 'Landhaus Uferblick',
    ];

    public const COMPANY_DOMAINS = [
        'hase-heim.example.com', 'korn-kanne.example.org', 'werkraum-hasetal.example.net', 'vitalpunkt.example.com', 'rad-reise.example.org',
        'modehaus-linden.example.net', 'elektro-nordlicht.example.com', 'blumenzeit.example.org', 'stadtblick-optik.example.net', 'uferblick.example.com',
    ];

    public const TECHNICAL_COMPANIES = [['Atelier Papierbogen', true, null, null], ['Gartenraum Abendgrün', false, 52.680, 7.490], ['Mobilwerk Auenpfad', true, 52.784, 7.544]];

    public function __construct(private readonly DirectorySlugger $slugs, #[Autowire('%portal.city%')] private readonly string $city)
    {
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $categories = [];
        foreach (['Handel' => 'fa-bag-shopping', 'Gastronomie' => 'fa-utensils', 'Handwerk' => 'fa-hammer', 'Dienstleistungen' => 'fa-briefcase', 'Gesundheit' => 'fa-heart-pulse', 'Freizeit' => 'fa-person-biking', 'Industrie' => 'fa-industry', 'Tourismus' => 'fa-suitcase'] as $name => $icon) {
            $category = (new Category())->setName($name)->setIcon($icon)->setPosition(count($categories));
            $this->slugs->assign($category);
            $manager->persist($category);
            $manager->flush();
            $categories[] = $category;
        }
        // Assigned to an active company, but never visible publicly.
        $archive = (new Category())->setName('Archiv')->setIcon('fa-box-archive')->setPosition(count($categories))->setActive(false);
        $this->slugs->assign($archive);
        $manager->persist($archive);
        $rows = self::data();
        foreach ($rows as $index => [$teaser, $description, $categoryIds, $street, $number, $first, $last, $position, $hours, $saturday]) {
            $domain = self::COMPANY_DOMAINS[$index];
            $email = 'kontakt@'.$domain;
            // The impossible local subscriber prefix 000 keeps these plausible-looking numbers non-dialable.
            $phone = sprintf('05961 / 000 %03d', 110 + $index);
            $company = (new Company())->setName(self::COMPANY_NAMES[$index])->setCity($this->city)->setStreet($street)->setHouseNumber($number)->setPostalCode('49740')
                ->setShortDescription($teaser)->setDescription($description)->setEmail($email)->setPhone($phone)->setActive(true)->setFeatured(in_array($index, [0, 1, 4], true))
                ->setLatitude($index === 4 ? 52.729 : 52.674 + $index * 0.0006)->setLongitude($index === 4 ? 7.514 : 7.484 + $index * 0.0008);
            if ($index % 2 === 0 || $index === 9) {
                $company->setWebsite('https://'.$domain);
            }
            if (in_array($index, [0, 1, 5, 7], true)) {
                $company->setFacebookUrl('https://social.example.org/facebook/'.explode('.', $domain)[0])->setInstagramUrl('https://social.example.org/instagram/'.explode('.', $domain)[0]);
            }
            foreach ($categoryIds as $category) {
                $company->addCategory($categories[$category]);
            }
            for ($day = 1; $day <= 5; ++$day) {
                foreach ($hours as $slot => [$opens, $closes]) {
                    $company->addOpeningHour((new OpeningHour())->setDayOfWeek($day)->setOpensAt(new \DateTimeImmutable('2000-01-01 '.$opens))->setClosesAt(new \DateTimeImmutable('2000-01-01 '.$closes))->setPosition($slot));
                }
            }
            $company->addOpeningHour($saturday === null ? (new OpeningHour())->setDayOfWeek(6)->setClosed(true)
                : (new OpeningHour())->setDayOfWeek(6)->setOpensAt(new \DateTimeImmutable('2000-01-01 '.$saturday[0]))->setClosesAt(new \DateTimeImmutable('2000-01-01 '.$saturday[1])));
            $company->addOpeningHour($index === 1 || $index === 9 ? (new OpeningHour())->setDayOfWeek(7)->setOpensAt(new \DateTimeImmutable('2000-01-01 10:00'))->setClosesAt(new \DateTimeImmutable('2000-01-01 16:00'))
                : (new OpeningHour())->setDayOfWeek(7)->setClosed(true));
            $company->addContactPerson((new ContactPerson())->setFirstName($first)->setLastName($last)->setPosition($position)->setEmail($email)->setPhone($phone)->setPrimaryContact(true));
            if ($index === 0) {
                $company->addCategory($archive);
                $company->addContactPerson((new ContactPerson())->setFirstName('Robin')->setLastName('Lichtwald')->setPosition('Verkauf')->setPhone('05961 / 000 210')->setMobile('0152 / 000 000')->setSortOrder(2));
                $company->addContactPerson((new ContactPerson())->setFirstName('Sam')->setLastName('Auenfeld')->setPosition('Vertretung')->setSortOrder(1)->setActive(false));
            }
            $this->slugs->assign($company);
            $manager->persist($company);
            $manager->flush();
            // References bind related fixtures to newly created objects, including during --append.
            $this->addReference(self::COMPANY_NAMES[$index], $company);
        }
        // Keep missing coordinates, image fallbacks and active points outside 10 km independent of the ten portal businesses.
        foreach (self::TECHNICAL_COMPANIES as $index => [$name, $active, $lat, $lng]) {
            $email = 'kontakt@'.['papierbogen', 'gartenraum', 'mobilwerk'][$index].'.example.net';
            $phone = sprintf('05961 / 000 %03d', 230 + $index);
            $company = (new Company())->setName($name)->setCity($this->city)->setStreet(['Papierbogenweg', 'Abendgrünpfad', 'Auenbogenweg'][$index])->setHouseNumber((string) (30 + $index))->setPostalCode('49740')
                ->setShortDescription('Gestaltungsideen und persönliche Beratung für besondere Projekte.')->setDescription('Wir nehmen uns Zeit für Ihre Wünsche und entwickeln passende Ideen für persönliche Projekte.')
                ->setEmail($email)->setPhone($phone)->setActive($active)->setFeatured(false)->setLatitude($lat)->setLongitude($lng)->addCategory($categories[3]);
            $company->addContactPerson((new ContactPerson())->setFirstName(['Jule', 'Milo', 'Leni'][$index])->setLastName(['Papierwald', 'Grünried', 'Auenholt'][$index])->setPosition('Beratung')->setEmail($email)->setPhone($phone)->setPrimaryContact(true));
            for ($day = 1; $day <= 5; ++$day) {
                $company->addOpeningHour((new OpeningHour())->setDayOfWeek($day)->setOpensAt(new \DateTimeImmutable('2000-01-01 '.(9 + $index).':00'))->setClosesAt(new \DateTimeImmutable('2000-01-01 16:00')));
            }
            $company->addOpeningHour((new OpeningHour())->setDayOfWeek(7)->setClosed(true));
            $this->slugs->assign($company);
            $manager->persist($company);
            $manager->flush();
            $this->addReference($name, $company);
        }
    }

    /** Data definitions only; safe to inspect without loading fixtures or connecting to a database. */
    public static function data(): array
    {
        return [
            ['Wohnaccessoires, Geschenkideen und ausgewählte Lieblingsstücke für Zuhause.',
                'Bei Hase & Heim finden Sie Dinge, die aus vier Wänden ein Zuhause machen: sorgfältig ausgewählte Wohnaccessoires, saisonale Dekoration und Geschenkideen für kleine und große Anlässe. Unser inhabergeführtes Geschäft verbindet natürliche Materialien mit zeitlosen Farben. Wir nehmen uns Zeit für Ihre Wünsche, stellen passende Kombinationen zusammen und verpacken Ihren Einkauf auf Wunsch als Geschenk.',
                [0], 'Lichtbogenweg', '4', 'Mara', 'Lichtenau', 'Geschäftsinhaberin', [['09:30', '12:30'], ['14:00', '18:00']], ['10:00', '14:00']],
            ['Frühstück, hausgemachter Kuchen, Kaffeespezialitäten und kleine Mittagsgerichte.',
                'Korn & Kanne ist ein Treffpunkt für gemütliche Frühstücksrunden und die kleine Pause zwischendurch. Morgens servieren wir frisch belegte Brote und warme Frühstücksgerichte, mittags eine wechselnde Auswahl aus unserer Küche. Dazu kommen hausgemachte Kuchen und sorgfältig zubereitete Kaffeespezialitäten. Auch für Familien und kleine Gruppen halten wir einen passenden Tisch bereit.',
                [1], 'Kannenbogen', '7', 'Jonas', 'Kornfeld', 'Caféleitung', [['08:00', '17:30']], ['08:00', '16:00']],
            ['Individueller Möbelbau, Innenausbau und Reparaturen aus Meisterhand.',
                'Werkraum Hasetal plant und fertigt Möbel, die zu Ihrem Alltag passen. Von der ersten Skizze bis zur Montage begleiten wir Einbauschränke, Regale und Innenausbau mit handwerklichem Blick für Details. Unsere Werkstatt verbindet klassische Holzverarbeitung mit präziser Fertigung. Wir kümmern uns ebenso um Reparaturen und die behutsame Aufarbeitung liebgewonnener Stücke.',
                [2, 6], 'Spänepfad', '12', 'Finn', 'Holzried', 'Tischlermeister', [['07:30', '12:00'], ['13:00', '16:30']], null],
            ['Physiotherapie, Prävention, Massage und moderne Bewegungskonzepte.',
                'Vitalpunkt Haselünne begleitet Menschen auf dem Weg zu mehr Beweglichkeit und Wohlbefinden. Wir verbinden Physiotherapie mit alltagstauglicher Prävention, persönlichen Übungsplänen und wohltuenden Massagen. In kleinen Kursgruppen vermitteln wir Bewegungsroutinen, die auch zuhause leicht umzusetzen sind. Termine und Behandlungsziele stimmen wir im persönlichen Gespräch ab.',
                [4, 3], 'Bewegungsbogen', '3', 'Nele', 'Quellenberg', 'Praxisleitung', [['08:00', '12:00'], ['13:00', '19:00']], null],
            ['Fahrräder, E-Bikes, Zubehör, Werkstattservice und Tourentipps.',
                'Bei Rad & Reise beginnt die nächste Tour mit einer guten Beratung. Wir helfen bei der Wahl von Fahrrad, E-Bike und passendem Zubehör und sorgen in unserer Werkstatt für zuverlässige Technik. Ob täglicher Weg zur Arbeit oder Ausflug ins Hasetal: Wir stellen individuelle Lösungen zusammen und teilen unsere liebsten Tourentipps. Probefahrten vereinbaren wir nach Absprache.',
                [5, 7, 0], 'Speichenpfad', '18', 'Luca', 'Wiesenholt', 'Werkstattleitung', [['09:00', '13:00'], ['14:00', '18:30']], ['09:00', '13:00']],
            ['Damen- und Herrenmode, Accessoires und persönliche Beratung.',
                'Modehaus Linden bietet eine entspannte Auswahl für Alltag, Beruf und besondere Anlässe. Entdecken Sie Damen- und Herrenmode, kombinierbare Basics und Accessoires mit Charakter. Unser Team berät zu Schnitten, Farben und passenden Ergänzungen, ohne Zeitdruck. Kleine Änderungen organisieren wir nach Absprache, damit Ihr neues Lieblingsstück richtig sitzt.',
                [0], 'Lindenbogen', '9', 'Tessa', 'Farbenthal', 'Modeberatung', [['09:30', '18:00']], ['09:30', '15:00']],
            ['Elektroinstallation, Smart Home, Beleuchtung und moderne Gebäudetechnik.',
                'Elektro Nordlicht begleitet private und gewerbliche Bauvorhaben von der Planung bis zur Inbetriebnahme. Wir entwickeln verständliche Lösungen für Elektroinstallationen, Beleuchtung und vernetzte Gebäudetechnik. Auch bei Modernisierung und Fehlersuche arbeiten wir mit klaren Absprachen und einem Blick auf den langfristigen Betrieb. Smart Home erklären wir anhand konkreter Anwendungen.',
                [2, 3], 'Leuchtenpfad', '6', 'Joris', 'Hellwinkel', 'Elektromeister', [['07:00', '12:00'], ['13:00', '17:00']], null],
            ['Floristik, Pflanzen, Dekoration und individuelle Geschenkarrangements.',
                'Blumenzeit am Markt gestaltet Sträuße und Arrangements für die schönen Momente im Leben. Saisonale Blüten, grüne Pflanzen und ausgewählte Gefäße bilden die Grundlage unserer Floristik. Wir beraten zu Farben, Pflege und persönlichen Geschenkideen und nehmen uns auch für größere Anlässe Zeit. Vorbestellungen können nach Absprache abgeholt werden.',
                [0], 'Blütenbogen', '2', 'Liv', 'Blattner', 'Floristmeisterin', [['08:30', '18:00']], ['08:30', '14:00']],
            ['Brillen, Sonnenbrillen, Sehtests und individuelle Stilberatung.',
                'Stadtblick Optik verbindet gutes Sehen mit einer Brille, die zu Ihnen passt. Wir beraten zu Fassungen, Gläsern und Sonnenbrillen und nehmen uns Zeit für eine sorgfältige Anpassung. Ein Sehtest und verständliche Erläuterungen helfen bei der Auswahl. Für Pflege, kleine Reparaturen und die regelmäßige Kontrolle Ihrer Brille sind wir ebenfalls da.',
                [4, 0], 'Blickwinkelweg', '11', 'Enno', 'Klarfeld', 'Augenoptikermeister', [['09:00', '13:00'], ['14:00', '18:00']], ['09:00', '13:00']],
            ['Regionale Küche, Übernachtungen und ein idealer Ausgangspunkt für Radtouren.',
                'Landhaus Uferblick lädt zu erholsamen Tagen und gutem Essen im Hasetal ein. Unsere Küche interpretiert regionale Gerichte mit saisonalen Zutaten, dazu bieten wir gemütliche Zimmer und einen unkomplizierten Service für Radreisende. Gäste erhalten Tipps für Ausflüge und können ihr Fahrrad sicher unterstellen. Für gemeinsame Essen und kleine Feiern beraten wir Sie persönlich.',
                [1, 7], 'Uferbogen', '21', 'Alva', 'Auenried', 'Gastgeberin', [['11:30', '14:00'], ['17:00', '21:00']], ['11:30', '21:00']],
        ];
    }
}
