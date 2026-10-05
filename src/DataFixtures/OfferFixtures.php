<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Offer;
use App\Enum\OfferType;
use App\Repository\CompanyRepository;
use App\Service\CompanyImageStorage;
use App\Service\DirectorySlugger;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockInterface;

/** Fictional examples relative to the injectable clock. Never load fixtures into production. */
final class OfferFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly CompanyRepository $companies, private readonly DirectorySlugger $slugs,
        private readonly ClockInterface $clock, private readonly CompanyImageStorage $storage, private readonly DirectoryImageFixtures $images)
    {
    }

    public function getDependencies(): array
    {
        return [DirectoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $companies = [];
        foreach (['Musterladen Hasebogen', 'Beispielcafé Uferpause', 'Demo-Werkstatt Stadtblick', 'Musterpraxis Wohlgefühl', 'Beispielhotel Lindenhof'] as $name) {
            $companies[] = $this->companies->findOneBy(['name' => $name]) ?? throw new \LogicException('Missing fixture company '.$name);
        }
        $now = $this->clock->now();
        $rows = [
            ['Lieblingsstücke zum Aktionspreis', 0, OfferType::Offer, true, true, '-1 day', '+7 days', '79.90', '59.90', '20 € sparen'],
            ['Genuss-Auszeit', 1, OfferType::Promotion, true, true, '-1 day', '+3 days', null, null, '2 für 1'],
            ['Werkstatt-Neuheit', 2, OfferType::NewProduct, true, false, null, null, null, null, null],
            ['20 Prozent auf Beispieljacken', 0, OfferType::Discount, true, false, '-1 day', '+14 days', null, null, '20 % Rabatt'],
            ['Saison-Ausblick', 0, OfferType::Offer, true, false, '+3 days', '+10 days', null, null, null],
            ['Sommer-Rückblick', 1, OfferType::Promotion, true, true, '-7 days', '-1 day', null, null, null],
            ['Pausierte Aktion', 2, OfferType::Promotion, false, true, null, null, null, null, null],
            ['Fiktives Hotelangebot', 4, OfferType::Offer, true, true, null, null, null, null, null],
            ['Wohlfühlwoche', 3, OfferType::Offer, true, false, '-1 day', '+2 days', null, '49.00', null],
            ['Neue Geschenkideen', 0, OfferType::NewProduct, true, false, null, null, null, null, null],
        ];
        foreach ($rows as $index => [$title, $company, $type, $active, $featured, $start, $end, $regular, $price, $discount]) {
            $offer = (new Offer())->setCompany($companies[$company])->setTitle($title)->setType($type)->setActive($active)->setFeatured($featured)
                ->setStartsAt($start !== null ? $now->modify($start) : null)->setEndsAt($end !== null ? $now->modify($end) : null)
                ->setRegularPrice($regular)->setOfferPrice($price)->setDiscountText($discount)
                ->setShortDescription('Fiktives Entwicklungsangebot – keine realen Preise oder Leistungen.')
                ->setDescription('Dieses Beispiel dient ausschließlich der lokalen Entwicklung und automatisierten Tests.')
                ->setTerms('Nur ein fiktives Beispiel; kein Kauf, keine Reservierung und keine Einlösung möglich.');
            $this->slugs->assign($offer);
            if ($index < 2) {
                $offer->setImageAlt('Fiktives Angebotsmotiv: '.$title);
                $this->storage->save($offer, $this->images->png([80 + $index * 70, 130, 100], 800, 450));
            } else {
                $manager->persist($offer);
            }
            // Flush each slug reservation, exactly as the existing directory fixtures do.
            $manager->flush();
        }
    }
}
