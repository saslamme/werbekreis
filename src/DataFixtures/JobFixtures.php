<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\JobPosting;
use App\Enum\EmploymentType;
use App\Enum\WorkModel;
use App\Enum\SalaryPeriod;
use App\Enum\NewsStatus;
use App\Service\DirectorySlugger;
use App\Service\NewsPublishing;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Invented vacancies, salaries and contact persons with Clock-relative publication. Never load into production. */
final class JobFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly DirectorySlugger $slugs, private readonly NewsPublishing $publishing)
    {
    }

    public function getDependencies(): array
    {
        return [DirectoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $now = $this->publishing->now();
        $rows = self::data();
        foreach ($rows as $index => [$title, $companyName, $employment, $model, $status, $start, $end, $featured, $teaser, $description, $requirements, $benefits]) {
            $company = $this->getReference($companyName, Company::class);
            $job = (new JobPosting($now))->setTitle($title)->setCompany($company)->copyLocationFromCompany($company)
                ->setShortDescription($teaser)->setDescription($description)->setRequirements($requirements)->setBenefits($benefits)
                ->setEmploymentType($employment)->setWorkModel($model)->setStatus($status)->setPublishedAt($start !== null ? $now->modify($start) : null)
                ->setValidThrough($end !== null ? $now->modify($end) : null)->setFeatured($featured)
                ->setContactName($company->getPublicContactPersons()[0]->getFirstName().' '.$company->getPublicContactPersons()[0]->getLastName());
            if ($index % 2 === 0) {
                $job->setApplicationUrl(($company->getWebsite() ?? 'https://karriere.example.org').'/bewerbung');
            } else {
                $job->setApplicationEmail('bewerbung@'.explode('@', (string) $company->getEmail())[1]);
            }
            if (in_array($index, [0, 2, 4, 6, 9], true)) {
                $job->setSalaryMin($index === 0 ? '2500' : '2900')->setSalaryMax($index === 0 ? '3200' : '3700')->setSalaryPeriod(SalaryPeriod::Monthly);
            }
            if ($index === 1) {
                $job->setSalaryMin('15.50')->setSalaryPeriod(SalaryPeriod::Hourly);
            }
            if ($index === 0) {
                $job->setReferenceNumber('ML-001')->setStartsAt($now->modify('+40 days'));
            }
            if ($index === 11) {
                // A distinct location and no coordinates preserve the location override case.
                $job->setCity('Meppen')->setStreet(null)->setHouseNumber(null)->setPostalCode(null)->setLatitude(null)->setLongitude(null);
            }
            if ($model !== WorkModel::Remote) {
                $job->setAddressCountry('DE');
            } else {
                $job->setCity('')->setStreet(null)->setHouseNumber(null)->setPostalCode(null)->setLatitude(null)->setLongitude(null);
            }
            $this->slugs->assign($job);
            $manager->persist($job);
            $manager->flush();
        }
    }

    /** Data definitions only; safe to inspect without loading fixtures or connecting to a database. */
    public static function data(): array
    {
        return [
            ['Modeberater (m/w/d)', 'Modehaus Linden', EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Published, '-1 day', '+30 days', true,
                'Beraten Sie unsere Kunden zu Mode, Passform und passenden Kombinationen.', 'Sie begleiten Kundengespräche, gestalten die Warenpräsentation und unterstützen das Team im Tagesgeschäft. Persönliche Beratung und ein gutes Gespür für unterschiedliche Wünsche stehen im Mittelpunkt.', 'Freude an Mode und Beratung, Teamgeist und idealerweise Erfahrung im Einzelhandel.', 'Planbare Arbeitszeiten, Mitarbeiterrabatt und regelmäßige Produktschulungen.'],
            ['Servicekraft (m/w/d)', 'Korn & Kanne', EmploymentType::PartTime, WorkModel::Onsite, NewsStatus::Published, '-2 days', '+14 days', true,
                'Sorgen Sie für einen freundlichen Empfang und entspannte Cafébesuche.', 'Sie nehmen Bestellungen auf, servieren Frühstück und Kuchen und behalten den Gastraum im Blick. Wir arbeiten mit kurzen Absprachen und unterstützen uns auch in lebhaften Zeiten gegenseitig.', 'Freundliches Auftreten, Zuverlässigkeit und Freude am Kontakt mit Gästen.', 'Abgestimmte Dienstpläne, Getränke während der Schicht und gründliche Einarbeitung.'],
            ['Zweiradmechatroniker (m/w/d)', 'Rad & Reise', EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Published, '-3 days', '+30 days', true,
                'Bringen Sie Fahrräder und E-Bikes zuverlässig zurück auf die Straße.', 'Sie übernehmen Inspektionen, suchen Fehler und führen Reparaturen an Fahrrädern und E-Bikes durch. Kunden erhalten von Ihnen verständliche Hinweise zur Wartung und zu sinnvoller Pflege.', 'Abgeschlossene Ausbildung im Zweiradbereich oder vergleichbare Werkstatterfahrung.', 'Moderne Arbeitsplätze, Fortbildungen zur E-Bike-Technik und ein eingespieltes Team.'],
            ['Physiotherapeut (m/w/d)', 'Vitalpunkt Haselünne', EmploymentType::PartTime, WorkModel::Onsite, NewsStatus::Published, '-4 days', '+45 days', false,
                'Begleiten Sie Menschen mit individuellen Behandlungs- und Bewegungsplänen.', 'Sie behandeln Patienten, dokumentieren Fortschritte und stimmen Übungen mit dem Praxisteam ab. Die ausgeschriebene Teilzeitstelle kann nach Absprache auch in Vollzeit besetzt werden.', 'Abgeschlossene Physiotherapieausbildung, Einfühlungsvermögen und sorgfältige Dokumentation.', 'Flexible Stundenplanung, Fortbildungsbudget und feste Zeiten für den fachlichen Austausch.'],
            ['Tischler (m/w/d)', 'Werkraum Hasetal', EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Published, '-5 days', '+35 days', false,
                'Fertigen Sie Möbel und Innenausbau mit einem Blick für gute Details.', 'Sie arbeiten von der Zeichnung bis zur Montage an individuellen Möbelprojekten. Dazu gehören Zuschnitt, Oberflächenbearbeitung und Reparaturen in enger Abstimmung mit der Werkstattleitung.', 'Abgeschlossene Tischlerausbildung, sicherer Umgang mit Werkzeugen und sorgfältiges Arbeiten.', 'Abwechslungsreiche Projekte, hochwertige Werkzeuge und gemeinsame Projektplanung.'],
            ['Ausbildung Kaufmann im Einzelhandel (m/w/d)', 'Hase & Heim', EmploymentType::Apprenticeship, WorkModel::Onsite, NewsStatus::Published, '-6 days', '+60 days', true,
                'Lernen Sie Beratung, Einkauf und Warenpräsentation von Grund auf kennen.', 'Während Ihrer Ausbildung begleiten Sie Kundengespräche, lernen unser Sortiment kennen und unterstützen bei der Gestaltung des Geschäfts. Schritt für Schritt übernehmen Sie eigene Aufgaben mit festen Ansprechpartnern.', 'Interesse am Handel, Neugier, Zuverlässigkeit und ein Schulabschluss zum Ausbildungsbeginn.', 'Persönliche Begleitung, Zeit für Prüfungsvorbereitung und Einblicke in Einkauf und Gestaltung.'],
            ['Elektroniker für Energie- und Gebäudetechnik (m/w/d)', 'Elektro Nordlicht', EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Published, '-7 days', '+30 days', false,
                'Installieren und betreuen Sie moderne Elektro- und Gebäudetechnik.', 'Sie setzen Installationsprojekte um, prüfen Anlagen und unterstützen bei Smart-Home-Lösungen. Gemeinsam mit der Projektleitung planen Sie die nächsten Schritte und erklären Kunden die Bedienung.', 'Passende abgeschlossene Ausbildung, sorgfältige Arbeitsweise und Führerschein Klasse B.', 'Fortbildungen, moderne Ausstattung und verlässliche Projektabsprachen.'],
            ['Florist (m/w/d)', 'Blumenzeit am Markt', EmploymentType::PartTime, WorkModel::Onsite, NewsStatus::Published, '-8 days', '+21 days', false,
                'Gestalten Sie saisonale Sträuße und persönliche Geschenkarrangements.', 'Sie beraten Kunden, binden Sträuße und pflegen Pflanzen und Schnittblumen. Eigene Gestaltungsideen sind willkommen, ebenso ein freundlicher Blick für kleine und große Anlässe.', 'Floristische Ausbildung oder praktische Erfahrung, Kreativität und Freude an Beratung.', 'Raum für eigene Ideen, Mitarbeiterrabatt und abgestimmte Arbeitszeiten.'],
            ['Koch / Küchenhilfe (m/w/d)', 'Landhaus Uferblick', EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Published, '-9 days', '+30 days', false,
                'Unterstützen Sie unsere Küche mit regionalen Gerichten und guter Vorbereitung.', 'Je nach Erfahrung übernehmen Sie die Zubereitung von Speisen oder unterstützen bei Vorbereitung und Anrichten. Das Küchenteam arbeitet mit saisonalen Zutaten und klaren Abläufen.', 'Küchenerfahrung ist willkommen; Sorgfalt, Hygienebewusstsein und Teamgeist sind wichtig.', 'Verpflegung während der Schicht, strukturierte Einarbeitung und frühzeitige Dienstplanung.'],
            ['Augenoptiker (m/w/d)', 'Stadtblick Optik', EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Published, '-10 days', '+40 days', false,
                'Verbinden Sie gutes Sehen mit persönlicher Brillen- und Stilberatung.', 'Sie beraten zu Fassungen und Gläsern, führen Anpassungen durch und begleiten Kunden auch nach dem Kauf. Kleine Werkstattarbeiten und eine sorgfältige Dokumentation gehören ebenfalls dazu.', 'Abgeschlossene Ausbildung in der Augenoptik und Freude an präziser, persönlicher Beratung.', 'Produktschulungen, moderne Messplätze und Zeit für individuelle Kundengespräche.'],
            ['Aushilfe im Verkauf (m/w/d)', 'Hase & Heim', EmploymentType::Minijob, WorkModel::Onsite, NewsStatus::Published, '-3 days', null, false,
                'Unterstützen Sie unser Team an lebhaften Einkaufstagen.', 'Sie helfen bei Warenpflege, Verpackung und einfachen Kundenfragen. Einsatzzeiten stimmen wir frühzeitig ab.', 'Zuverlässigkeit und ein freundliches Auftreten.', 'Flexible Abstimmung der Einsätze und persönliche Einarbeitung.'],
            ['Praktikum Veranstaltungsorganisation (m/w/d)', 'Hase & Heim', EmploymentType::Internship, WorkModel::Hybrid, NewsStatus::Published, '-5 days', '+5 days', false,
                'Lernen Sie die Planung gemeinsamer Innenstadtaktionen kennen.', 'Sie unterstützen Ablaufplanung, Materialorganisation und die Abstimmung mit Gastgebern. Vorbereitung im Homeoffice und gemeinsame Termine vor Ort ergänzen sich.', 'Organisationstalent, sichere Kommunikation und Interesse am Stadtleben.', 'Einblicke in Veranstaltungsplanung und feste fachliche Begleitung.'],
            ['Werkstudent Digitale Kommunikation (m/w/d)', 'Rad & Reise', EmploymentType::WorkingStudent, WorkModel::Remote, NewsStatus::Published, '-6 days', null, false,
                'Machen Sie Werkstattwissen und Tourenideen digital verständlich.', 'Sie bereiten Beiträge vor, pflegen Informationen und stimmen Themen online mit unserem Team ab. Die Tätigkeit kann vollständig aus dem Homeoffice erfolgen.', 'Laufendes Studium, Freude am Schreiben und sicherer Umgang mit digitalen Werkzeugen.', 'Flexible Stunden neben dem Studium und regelmäßiges Online-Feedback.'],
            ['Projektassistenz Smart Home (m/w/d)', 'Elektro Nordlicht', EmploymentType::Temporary, WorkModel::Hybrid, NewsStatus::Published, '-7 days', '+7 days', false,
                'Halten Sie Unterlagen und Termine für laufende Installationsprojekte zusammen.', 'Sie unterstützen das Projektteam bei Terminplanung und Dokumentation. Für Absprachen wechseln sich Bürotermine und Arbeit im Homeoffice ab.', 'Strukturierte Arbeitsweise und Grundkenntnisse in Bürosoftware.', 'Befristeter Projekteinstieg mit klaren Aufgaben und flexibler Arbeitsplatzwahl.'],
            ['Assistenz Praxisorganisation (m/w/d)', 'Vitalpunkt Haselünne', EmploymentType::PartTime, WorkModel::Onsite, NewsStatus::Draft, null, '+30 days', true,
                'Koordinieren Sie Termine und sorgen Sie für einen freundlichen Empfang.', 'Sie unterstützen Terminvergabe, Telefon und die Organisation im Praxisalltag.', 'Freundlichkeit, Diskretion und organisatorisches Geschick.', 'Planbare Arbeitszeiten und ein kleines Praxisteam.'],
            ['Frühstücksservice (m/w/d)', 'Landhaus Uferblick', EmploymentType::PartTime, WorkModel::Onsite, NewsStatus::Scheduled, '+3 days', '+30 days', true,
                'Begleiten Sie Gäste mit einem gut vorbereiteten Frühstück in den Tag.', 'Sie bereiten das Frühstück vor, betreuen Gäste und kümmern sich um einen gepflegten Frühstücksraum.', 'Zuverlässigkeit und Freude am frühen Tagesbeginn.', 'Frühzeitige Dienstplanung und persönliche Einarbeitung.'],
            ['Schaufenstergestalter (m/w/d)', 'Modehaus Linden', EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Published, '-20 days', '-1 day', true,
                'Gestalten Sie saisonale Schaufenster und Warenflächen.', 'Sie entwickeln Präsentationsideen und setzen neue Themen im Geschäft um.', 'Gestalterisches Gespür und Erfahrung in Warenpräsentation.', 'Kreative Aufgaben und Zusammenarbeit mit dem Verkaufsteam.'],
            ['Aushilfe Frühstücksservice (m/w/d)', 'Korn & Kanne', EmploymentType::Minijob, WorkModel::Onsite, NewsStatus::Scheduled, '-1 hour', '+3 days', false,
                'Helfen Sie unserem Caféteam bei Vorbereitung und Service am Morgen.', 'Sie decken Tische, unterstützen beim Servieren und bereiten den Gastraum für die nächste Frühstücksrunde vor.', 'Freundliches Auftreten und Verlässlichkeit.', 'Kurze Einsätze und abgestimmte Arbeitszeiten.'],
            ['Redakteur für Tourenideen (m/w/d)', 'Rad & Reise', EmploymentType::FullTime, WorkModel::Remote, NewsStatus::Published, '+2 days', '+30 days', true,
                'Entwickeln Sie verständliche Tourenbeschreibungen und Reisetipps.', 'Sie recherchieren Streckeninformationen, strukturieren Beiträge und stimmen Inhalte digital mit dem Team ab.', 'Schreiberfahrung und Interesse an Fahrradtouren.', 'Arbeit im Homeoffice und feste Redaktionsabsprachen.'],
        ];
    }
}
