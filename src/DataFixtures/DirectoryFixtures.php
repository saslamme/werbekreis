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

/** Entirely fictional development/test data. Never load into production. */
final class DirectoryFixtures extends Fixture implements DependentFixtureInterface
{
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
            $categories[] = $category;
        }
        $manager->flush();
        $names = ['Musterladen Hasebogen', 'Beispielcafé Uferpause', 'Demo-Werkstatt Stadtblick', 'Musterpraxis Wohlgefühl', 'Beispielhotel Lindenhof'];
        $assignments = [[0, 3], [1], [2, 6], [4], [7, 5]];
        foreach ($names as $index => $name) {
            $company = (new Company())->setName($name)->setCity($this->city)->setStreet('Fiktive Beispielstraße')->setHouseNumber((string) ($index + 1))->setPostalCode('49740')
                ->setShortDescription('Fiktives Entwicklungsunternehmen; keine realen Kontaktdaten.')
                ->setDescription('Diese Stammdaten dienen ausschließlich der lokalen Entwicklung und automatisierten Tests.')
                ->setEmail('kontakt@betrieb'.($index + 1).'.example')->setPhone('0000 / 000000')->setActive($index !== 4)->setFeatured($index < 2);
            if ($index % 2 === 0) {
                $company->setWebsite('https://betrieb'.($index + 1).'.example');
            }
            if ($index === 1) {
                $company->setFacebookUrl('https://facebook.example/beispiel')->setInstagramUrl('https://instagram.example/beispiel');
            }
            foreach ($assignments[$index] as $category) {
                $company->addCategory($categories[$category]);
            }
            for ($day = 1; $day <= 5; ++$day) {
                $company->addOpeningHour((new OpeningHour())->setDayOfWeek($day)->setOpensAt(new \DateTimeImmutable('09:00'))->setClosesAt(new \DateTimeImmutable($index === 1 ? '17:00' : '12:30')));
                if ($index !== 1) {
                    $company->addOpeningHour((new OpeningHour())->setDayOfWeek($day)->setOpensAt(new \DateTimeImmutable('14:00'))->setClosesAt(new \DateTimeImmutable('18:00'))->setPosition(1));
                }
            }
            $company->addOpeningHour((new OpeningHour())->setDayOfWeek(7)->setClosed(true));
            $company->addContactPerson((new ContactPerson())->setFirstName('Alex')->setLastName('Beispiel')->setPosition('Ansprechpartner (fiktiv)')->setEmail('kontakt@betrieb'.($index + 1).'.example')->setPrimaryContact(true));
            if ($index === 0) {
                $company->addContactPerson((new ContactPerson())->setFirstName('Sam')->setLastName('Muster')->setPosition('Vertretung (fiktiv)')->setSortOrder(1)->setActive(false));
            }
            $this->slugs->assign($company);
            $manager->persist($company);
        }
        $manager->flush();
    }
}
