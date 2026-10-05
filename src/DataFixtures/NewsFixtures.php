<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\{NewsArticle, NewsCategory};
use App\Enum\NewsStatus;
use App\Repository\CompanyRepository;
use App\Service\{CompanyImageStorage, DirectorySlugger, NewsPublishing};
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Relative-clock fictional examples, exclusively for development and tests. */
final class NewsFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly CompanyRepository $companies, private readonly DirectorySlugger $slugs, private readonly NewsPublishing $publishing,
        private readonly CompanyImageStorage $storage, private readonly DirectoryImageFixtures $images) {}
    public function getDependencies(): array { return [DirectoryFixtures::class]; }
    public function load(ObjectManager $manager): void
    {
        $categories = [];
        foreach (['Werbekreis', 'Unternehmen', 'Stadtleben', 'Aktionen', 'Handel', 'Gastronomie', 'Veranstaltungen', 'Allgemein'] as $index => $name) {
            $category = (new NewsCategory())->setName($name)->setPosition($index); $this->slugs->assign($category); $manager->persist($category); $manager->flush(); $categories[] = $category;
        }
        $inactive = (new NewsCategory())->setName('Fiktive inaktive News-Kategorie')->setActive(false); $this->slugs->assign($inactive); $manager->persist($inactive); $manager->flush();
        $shop = $this->companies->findOneBy(['name' => 'Musterladen Hasebogen']); $cafe = $this->companies->findOneBy(['name' => 'Beispielcafé Uferpause']);
        $rows = [
            ['Gemeinsam vor Ort', NewsStatus::Published, '-1 day', true, null, [0, 2]],
            ['Neues aus dem Musterladen', NewsStatus::Published, '-2 days', true, $shop, [1, 4]],
            ['Fiktive Café-Geschichten', NewsStatus::Published, '-3 days', false, $cafe, [1, 5]],
            ['Entwurf einer Neuigkeit', NewsStatus::Draft, null, true, $shop, [1]],
            ['Geplanter Stadtblick', NewsStatus::Scheduled, '+3 days', true, null, [2]],
            ['Automatisch veröffentlichter Rückblick', NewsStatus::Scheduled, '-1 hour', false, null, [0, 6]],
            ['Veröffentlichung in der Zukunft', NewsStatus::Published, '+2 days', true, null, [3]],
            ['Ideen aus dem Handel', NewsStatus::Published, '-4 days', false, $shop, [4]],
            ['Ältere Einblicke', NewsStatus::Published, '-1 month', false, $shop, [1, 4]],
            ['Kleine Schritte vor Ort', NewsStatus::Published, '-6 days', false, $shop, [3, 4]],
            ['Aus unserem Archiv', NewsStatus::Published, '-6 months', false, null, [7]],
            ['Neuigkeit ohne Datumsangabe', NewsStatus::Published, null, true, null, [7]],
        ];
        foreach ($rows as $index => [$title, $status, $offset, $featured, $company, $categoryIds]) {
            $article = (new NewsArticle($this->publishing->now()))->setTitle($title)->setStatus($status)->setPublishedAt($offset !== null ? $this->publishing->now()->modify($offset) : null)->setFeatured($featured)->setCompany($company)
                ->setTeaser('Fiktive Neuigkeit für Entwicklung und Tests – keine reale Meldung.')
                ->setContent("Dieser Beitrag beschreibt ausschließlich eine fiktive Beispielgeschichte.\n\nEr dient der Entwicklung und automatisierten Prüfung des Werbekreis-Portals.");
            foreach ($categoryIds as $id) { $article->addCategory($categories[$id]); }
            if ($index === 0) { $article->addCategory($inactive)->setAuthorName('Fiktive Redaktion')->setExternalUrl('https://example.org'); }
            $this->slugs->assign($article);
            if ($index < 2) { $article->setImageAltText('Fiktives News-Motiv: '.$title); $this->storage->save($article, $this->images->png([120 + $index * 30, 110, 80], 800, 450)); }
            else { $manager->persist($article); $manager->flush(); }
        }
    }
}
