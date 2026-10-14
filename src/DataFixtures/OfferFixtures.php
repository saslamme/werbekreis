<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\Offer;
use App\Enum\OfferType;
use App\Service\CompanyImageStorage;
use App\Service\DirectorySlugger;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockInterface;

/** Invented offers and prices relative to the injectable clock. Never load into production. */
final class OfferFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly DirectorySlugger $slugs, private readonly ClockInterface $clock,
        private readonly CompanyImageStorage $storage, private readonly DirectoryImageFixtures $images)
    {
    }

    public function getDependencies(): array
    {
        return [DirectoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $now = $this->clock->now();
        $rows = self::data();
        foreach ($rows as $index => [$title, $company, $type, $active, $featured, $start, $end, $regular, $price, $discount, $teaser, $description, $terms]) {
            $offer = (new Offer())->setCompany($this->getReference($company, Company::class))->setTitle($title)->setType($type)->setActive($active)->setFeatured($featured)
                ->setStartsAt($start !== null ? $now->modify($start) : null)->setEndsAt($end !== null ? $now->modify($end) : null)
                ->setRegularPrice($regular)->setOfferPrice($price)->setDiscountText($discount)
                ->setShortDescription($teaser)->setDescription($description)->setTerms($terms);
            $this->slugs->assign($offer);
            if ($index < 6) {
                $offer->setImageAlt('Grafisches Aktionsmotiv: '.$title);
                $this->storage->save($offer, $this->images->png([75 + $index * 20, 125, 90 + $index * 15], 800, 450, $index));
            } else {
                $manager->persist($offer);
            }
            $manager->flush();
        }
    }

    /** Data definitions only; safe to inspect without loading fixtures or connecting to a database. */
    public static function data(): array
    {
        return [
            ['Herbstlieblinge – 20 % reduziert', 'Modehaus Linden', OfferType::Discount, true, true, '-1 day', '+14 days', null, null, '20 % Rabatt',
                'Neue Farben für kühle Tage: ausgewählte Jacken, Strickteile und Accessoires günstiger entdecken.', 'Wir stellen kombinierbare Herbstlooks zusammen und beraten Sie zu Schnitt und Farbe. Der Rabatt gilt auf die gekennzeichnete Auswahl im Geschäft.', 'Gilt für gekennzeichnete Artikel, solange der Vorrat reicht. Nicht mit anderen Rabatten kombinierbar.'],
            ['Frühstück für Zwei', 'Korn & Kanne', OfferType::Offer, true, true, '-1 day', '+21 days', '31.80', '24.90', null,
                'Gemeinsam in den Tag starten mit Brot, Aufstrichen, Obst und zwei Kaffeespezialitäten.', 'Unser Frühstück für Zwei wird am Tisch serviert und umfasst eine abwechslungsreiche Auswahl süßer und herzhafter Kleinigkeiten. Vegetarische Wünsche berücksichtigen wir gern.', 'Täglich bis 11 Uhr. Reservierung empfohlen. Preis für zwei Personen.'],
            ['E-Bike Herbstcheck', 'Rad & Reise', OfferType::Offer, true, true, '-1 day', '+28 days', null, '29.00', null,
                'Gut vorbereitet in die nächste Tour: Bremsen, Licht und Antrieb im Blick.', 'Unsere Werkstatt kontrolliert die wichtigsten Sicherheitsfunktionen Ihres E-Bikes und gibt Hinweise zur Akku- und Winterpflege. Den Termin vereinbaren Sie direkt mit unserem Team.', 'Kontrolle ohne Ersatzteile und zusätzliche Reparaturen. Termin nach Vereinbarung.'],
            ['3 für 2 auf ausgewählte Wohnaccessoires', 'Hase & Heim', OfferType::Promotion, true, false, '-1 day', '+10 days', null, null, '3 für 2',
                'Drei schöne Dinge auswählen und nur zwei bezahlen.', 'Kombinieren Sie Kerzenhalter, kleine Vasen und ausgewählte Dekoration aus unserer Aktionsauswahl. So entstehen persönliche Geschenksets und neue Lieblingsplätze zuhause.', 'Der günstigste von drei gekennzeichneten Artikeln ist gratis. Solange der Vorrat reicht.'],
            ['Rückenfit-Schnupperwoche', 'Vitalpunkt Haselünne', OfferType::Offer, true, false, '-1 day', '+14 days', null, '49.00', null,
                'Bewegung kennenlernen und kleine Routinen für einen aktiven Alltag finden.', 'In drei begleiteten Kurseinheiten lernen Sie Übungen für Beweglichkeit und Rumpfstabilität kennen. Kleine Gruppen ermöglichen individuelle Hinweise und einen entspannten Einstieg.', 'Drei Kurseinheiten innerhalb einer Woche. Anmeldung erforderlich; keine individuelle Heilbehandlung.'],
            ['Sonnenbrillen-Special', 'Stadtblick Optik', OfferType::Discount, true, false, '-1 day', '+18 days', null, null, '15 % Rabatt',
                'Sonnenbrillen mit Stil und passender Beratung entdecken.', 'Auf ausgewählte Sonnenbrillenfassungen erhalten Sie einen Preisvorteil. Unser Team unterstützt Sie bei Form, Sitz und der Auswahl geeigneter Gläser.', 'Rabatt auf gekennzeichnete Fassungen; individuelle Korrektionsgläser ausgenommen.'],
            ['Herbststrauß der Woche', 'Blumenzeit am Markt', OfferType::Offer, true, false, '-1 day', '+7 days', null, '19.50', null,
                'Warme Farben und saisonale Blüten für Ihren Lieblingsplatz.', 'Jede Woche binden wir einen neuen Strauß aus saisonalen Blüten und passendem Grün. Kleine Farbvariationen machen jedes Arrangement zu einem eigenen Stück.', 'Abholung im Geschäft. Zusammenstellung kann je nach Verfügbarkeit variieren.'],
            ['Kostenlose Erstberatung für individuelle Möbel', 'Werkraum Hasetal', OfferType::Promotion, true, false, null, null, null, null, 'Erstberatung kostenlos',
                'Von der ersten Idee zum Möbel, das zu Ihrem Raum passt.', 'Bringen Sie Maße, Fotos oder eine Skizze mit. Wir besprechen Materialien, Nutzung und mögliche Lösungen für Ihr Möbelprojekt und erläutern die nächsten Planungsschritte.', 'Ein erstes Beratungsgespräch nach Terminvereinbarung; keine Ausführungsplanung enthalten.'],
            ['Smart-Home-Beratungswochen', 'Elektro Nordlicht', OfferType::NewProduct, true, false, '-1 day', '+30 days', null, null, null,
                'Beleuchtung und Gebäudetechnik einfach und verständlich vernetzen.', 'Wir zeigen Möglichkeiten für Lichtsteuerung, Beschattung und Energieübersicht. Gemeinsam prüfen wir, welche Funktionen zu Ihrem Gebäude und Ihrem Alltag passen.', 'Beratung nach Terminvereinbarung. Installation und Geräte werden gesondert angeboten.'],
            ['Genuss-Wochenende im Hasetal', 'Landhaus Uferblick', OfferType::Offer, true, true, '-1 day', '+35 days', '249.00', '219.00', null,
                'Zwei Nächte, Frühstück und ein regionales Abendmenü für eine entspannte Auszeit.', 'Genießen Sie das Hasetal mit Zeit für eine Radtour und einen gemütlichen Abend. Das Arrangement umfasst zwei Übernachtungen im Doppelzimmer, Frühstück und ein Drei-Gänge-Menü.', 'Preis pro Person bei zwei Personen im Doppelzimmer. Nach Verfügbarkeit, Getränke zum Menü separat.'],
            ['Winterlichter für Zuhause', 'Hase & Heim', OfferType::NewProduct, true, false, '+3 days', '+24 days', null, null, null,
                'Neue Lichtobjekte und ruhige Farben für die dunklere Jahreszeit.', 'Unsere neue Auswahl verbindet kleine Tischleuchten und dekorative Lichtobjekte mit natürlichen Materialien. Entdecken Sie Ideen für ein stimmungsvolles Zuhause.', 'Verfügbar ab Aktionsbeginn, solange der Vorrat reicht.'],
            ['Sommerpicknick für Zwei', 'Korn & Kanne', OfferType::Promotion, true, true, '-7 days', '-1 day', null, null, 'Picknickset inklusive',
                'Herzhafte Kleinigkeiten für eine Pause unter freiem Himmel.', 'Unser Picknickpaket kombiniert belegte Brote, frisches Obst und hausgemachte Limonade. Die saisonale Aktion hat den Sommer mit vielen kleinen Auszeiten begleitet.', 'Abholung nach Vorbestellung während des Aktionszeitraums.'],
            ['Möbelpflege im Werkraum', 'Werkraum Hasetal', OfferType::Promotion, false, true, null, null, null, null, null,
                'Holzmöbel mit passender Pflege lange schön erhalten.', 'Wir erläutern die Pflege unterschiedlicher Holzoberflächen und zeigen den Umgang mit Ölen und Wachsen. Das Angebot umfasst eine Materialberatung anhand Ihres Möbelstücks.', 'Beratung nach Vereinbarung; Pflegeprodukte und Arbeiten werden separat berechnet.'],
            ['Gartenplanung im Gespräch', 'Gartenraum Abendgrün', OfferType::Offer, true, true, null, null, null, null, null,
                'Erste Ideen für einen grünen Lieblingsplatz sammeln.', 'Wir besprechen Pflanzenauswahl, Licht und die Gestaltung kleiner Gartenbereiche. Ein persönlicher Termin schafft Raum für Ihre Wünsche.', 'Termin nach Vereinbarung.'],
        ];
    }
}
