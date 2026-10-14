<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\NewsArticle;
use App\Entity\NewsCategory;
use App\Enum\NewsStatus;
use App\Service\CompanyImageStorage;
use App\Service\DirectorySlugger;
use App\Service\NewsPublishing;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Entirely fictional editorial stories, businesses and authors with Clock-relative publication. Never load into production. */
final class NewsFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly DirectorySlugger $slugs, private readonly NewsPublishing $publishing,
        private readonly CompanyImageStorage $storage, private readonly DirectoryImageFixtures $images)
    {
    }

    public function getDependencies(): array
    {
        return [DirectoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $categories = [];
        foreach (['Werbekreis', 'Unternehmen', 'Stadtleben', 'Aktionen', 'Handel', 'Gastronomie', 'Veranstaltungen', 'Allgemein'] as $index => $name) {
            $category = (new NewsCategory())->setName($name)->setPosition($index);
            $this->slugs->assign($category);
            $manager->persist($category);
            $manager->flush();
            $categories[] = $category;
        }
        $inactive = (new NewsCategory())->setName('Archiv News')->setActive(false);
        $this->slugs->assign($inactive);
        $manager->persist($inactive);
        $manager->flush();
        $now = $this->publishing->now();
        $rows = self::data();
        foreach ($rows as $index => [$title, $status, $offset, $featured, $company, $categoryIds, $teaser, $content]) {
            $article = (new NewsArticle($now))->setTitle($title)->setStatus($status)->setPublishedAt($offset !== null ? $now->modify($offset) : null)->setFeatured($featured)
                ->setCompany($company !== null ? $this->getReference($company, Company::class) : null)->setTeaser($teaser)->setContent($content)->setAuthorName('Redaktion Stadtportal');
            foreach ($categoryIds as $id) {
                $article->addCategory($categories[$id]);
            }
            if ($index === 0) {
                $article->addCategory($inactive)->setExternalUrl('https://stadtleben.example.org/aktionen');
            }
            $this->slugs->assign($article);
            if (in_array($index, [0, 1, 2, 7, 8, 9], true)) {
                $article->setImageAltText('Grafisches Nachrichtenmotiv: '.$title);
                $this->storage->save($article, $this->images->png([95 + $index * 10, 110, 100 + $index * 12], 800, 450, $index + 2));
            } else {
                $manager->persist($article);
                $manager->flush();
            }
        }
    }

    /** Data definitions only; safe to inspect without loading fixtures or connecting to a database. */
    public static function data(): array
    {
        return [
            ['Late-Night-Shopping: Haselünne bleibt länger wach', NewsStatus::Published, '-1 day', true, null, [0, 2],
                'Ein Abend für Einkauf, Begegnung und Genuss: Die Innenstadt lädt zum längeren Stadtbummel ein.',
                "Teilnehmende Geschäfte öffnen länger und begleiten den Abend mit Aktionen und kleinen Überraschungen. Auch gastronomische Angebote laden dazu ein, eine Pause einzulegen und den Stadtbummel gemeinsam ausklingen zu lassen.\n\nDer Werbekreis bündelt die Programmpunkte im Veranstaltungskalender. So können Besucher ihre Runde durch die Innenstadt schon vorab planen."],
            ['Neue Geschenkideen bei Hase & Heim', NewsStatus::Published, '-2 days', true, 'Hase & Heim', [1, 4],
                'Natürliche Materialien und warme Farben prägen die neue Auswahl für Zuhause.',
                "Hase & Heim hat die Regale mit kleinen Wohnaccessoires, Kerzenhaltern und passenden Geschenkideen neu gestaltet. Viele Stücke lassen sich zu persönlichen Sets kombinieren.\n\nDas Team unterstützt bei der Auswahl und verpackt Geschenke auf Wunsch direkt im Geschäft. Wer Inspiration sucht, kann sich beim Rundgang Zeit lassen."],
            ['Korn & Kanne startet neue Frühstückskarte', NewsStatus::Published, '-3 days', false, 'Korn & Kanne', [1, 5],
                'Mehr Abwechslung am Morgen: süße, herzhafte und vegetarische Frühstücksideen.',
                "Korn & Kanne erweitert die Frühstücksauswahl um warme Kleinigkeiten und neue Kombinationen zum Teilen. Hausgemachte Aufstriche und saisonales Obst ergänzen die vertrauten Klassiker.\n\nDas Café empfiehlt für größere Runden eine Reservierung. Die Frühstückskarte gibt es während der morgendlichen Küchenzeiten."],
            ['Vorbereitungen für den Adventszauber laufen', NewsStatus::Draft, null, true, 'Blumenzeit am Markt', [1, 6],
                'Stände, Licht und Mitmachangebote werden für das Adventsprogramm abgestimmt.',
                "Die Planung für den Adventszauber nimmt Gestalt an. Gastgeber stimmen Dekoration, Standflächen und kreative Angebote ab.\n\nSobald die letzten Programmpunkte feststehen, folgt die Übersicht im Veranstaltungskalender."],
            ['Werbekreis plant Aktionen für die Adventszeit', NewsStatus::Scheduled, '+3 days', true, null, [0, 2, 3],
                'Gemeinsame Ideen sollen Einkäufe und Begegnungen in der Adventszeit verbinden.',
                "Längere Einkaufsabende, kreative Nachmittage und kleine Begegnungsorte stehen auf der Ideenliste für die Adventszeit. Der Werbekreis sammelt Beiträge der teilnehmenden Betriebe.\n\nDie Aktionen werden nach Abschluss der Abstimmung im Portal angekündigt. Auch neue Mitstreiter können ihre Ideen einbringen."],
            ['Unternehmerabend bringt lokale Betriebe zusammen', NewsStatus::Scheduled, '-1 hour', false, null, [0, 6],
                'Digitale Sichtbarkeit und persönliche Zusammenarbeit standen im Mittelpunkt des Austauschs.',
                "Beim Unternehmerabend haben Betriebe Erfahrungen mit Websites und digitalen Schaufenstern geteilt. Kurze Impulse eröffneten die Gesprächsrunden, in denen praktische Fragen Platz fanden.\n\nMehrere Teilnehmer wollen gemeinsame Aktionen weiterdenken. Die nächste Runde soll an die entstandenen Kontakte anknüpfen."],
            ['Neue Akzeptanzstellen für den Haselünne-Gutschein', NewsStatus::Published, '+2 days', true, null, [3, 4],
                'Weitere Geschäfte erweitern die Auswahl für einen Einkauf mit dem Stadtgutschein.',
                "Die Übersicht der Akzeptanzstellen wächst um neue Einkaufsmöglichkeiten. Der Haselünne-Gutschein verbindet Handel, Gastronomie und handwerkliche Angebote im Portal.\n\nWelche Betriebe teilnehmen und wo Gutscheine erhältlich sind, lässt sich in der aktuellen Gutscheinübersicht nachlesen."],
            ['Gemeinsam für eine lebendige Innenstadt', NewsStatus::Published, '-4 days', false, 'Hase & Heim', [0, 3, 4],
                'Kleine gemeinsame Aktionen machen den Stadtbummel abwechslungsreich.',
                "Persönliche Beratung, einladende Schaufenster und Zeit für Begegnungen tragen zu einer lebendigen Innenstadt bei. Betriebe entwickeln dafür gemeinsame Ideen und stimmen Aktionen miteinander ab.\n\nHase & Heim beteiligt sich mit saisonalen Gestaltungsideen. Weitere Gastgeber ergänzen die Runde mit passenden Angeboten."],
            ['Rad & Reise erweitert den Werkstattservice', NewsStatus::Published, '-1 month', false, 'Rad & Reise', [1, 4],
                'Zusätzliche Werkstatttermine erleichtern die Planung von Wartung und Reparaturen.',
                "Rad & Reise organisiert neue Zeitfenster für Inspektionen und Sicherheitschecks. Kunden können anstehende Arbeiten vorab besprechen und den passenden Termin vereinbaren.\n\nNeben klassischen Fahrrädern stehen E-Bikes im Fokus. Das Team gibt auch Hinweise zur Akku- und Winterpflege."],
            ['Neu im Werbekreis: Stadtblick Optik', NewsStatus::Published, '-6 days', false, 'Stadtblick Optik', [0, 1, 4],
                'Persönliche Brillenberatung und gutes Sehen ergänzen die Vielfalt im Portal.',
                "Stadtblick Optik stellt sich im Werbekreis mit Brillen, Sonnenbrillen und individueller Stilberatung vor. Das Team nimmt sich Zeit für Sehtests und die Anpassung der gewählten Fassung.\n\nMit dem neuen Profil finden Besucher Kontaktdaten, Öffnungszeiten und Informationen zum Angebot an einem Ort."],
            ['Ein Rückblick auf gemeinsame Innenstadtaktionen', NewsStatus::Published, '-6 months', false, null, [7],
                'Begegnungen, kleine Entdeckungen und neue Kontakte bleiben in Erinnerung.',
                "Die vergangenen Innenstadtaktionen haben Einkauf und Austausch miteinander verbunden. Gastgeber haben ihre Angebote vorgestellt und Besucher konnten neue Lieblingsplätze entdecken.\n\nDie Erfahrungen fließen in die nächste Planung ein. Besonders gefragt waren kurze Wege und Programmpunkte für die ganze Familie."],
            ['Das Stadtportal verbindet Angebote und Begegnungen', NewsStatus::Published, null, true, null, [7],
                'Unternehmen, Veranstaltungen und Neuigkeiten lassen sich an einem Ort entdecken.',
                "Das Stadtportal bietet Orientierung für den nächsten Einkauf, eine Veranstaltung oder die Suche nach einer offenen Stelle. Unternehmensprofile zeigen Ansprechpartner und Öffnungszeiten.\n\nRegelmäßige Neuigkeiten ergänzen die Übersicht und machen gemeinsame Vorhaben sichtbar. So entsteht ein kurzer Weg von der Idee zum passenden Kontakt."],
        ];
    }
}
