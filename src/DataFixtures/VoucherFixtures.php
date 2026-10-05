<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\{User, Voucher, VoucherProduct, VoucherRedemption};
use App\Service\{VoucherCodeGenerator, DirectorySlugger};
use App\Repository\{CompanyRepository, UserRepository};
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Explicit TEST-prefixed fictional codes and relative Clock data; never load in production. */
final class VoucherFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly CompanyRepository $companies, private readonly UserRepository $users, private readonly DirectorySlugger $slugs, private readonly ClockInterface $clock, private readonly UserPasswordHasherInterface $hasher) {}
    public function getDependencies(): array { return [DirectoryFixtures::class, UserFixtures::class]; }
    public static function code(int $index): string { return 'TEST-WK-AAAA-BBBB-CCCC-DDD'.VoucherCodeGenerator::ALPHABET[$index]; }
    public function load(ObjectManager $manager): void
    {
        $shop = $this->companies->findOneBy(['name' => 'Musterladen Hasebogen']); $cafe = $this->companies->findOneBy(['name' => 'Beispielcafé Uferpause']); $workshop = $this->companies->findOneBy(['name' => 'Demo-Werkstatt Stadtblick']);
        $variable = (new VoucherProduct())->setName('TEST Werbekreis-Gutschein')->setDescription('Fiktiver variabler Gutschein für Entwicklung und Tests.')->setMinimumAmount('10')->setMaximumAmount('250')->setValidityMonths(36)->setFeatured(true)->setTerms('Ausschließlich Testdaten, kein produktiver Gutschein.');
        $variable->addAcceptingCompany($shop)->addAcceptingCompany($cafe)->addSellingCompany($cafe);
        $fixed = (new VoucherProduct())->setName('TEST Gutschein 25 Euro')->setDescription('Fiktiver Festwert-Gutschein.')->setFixedAmount('25')->setValidityMonths(12)->setPosition(1)->addAcceptingCompany($shop)->addAcceptingCompany($workshop)->addSellingCompany($shop);
        $inactive = (new VoucherProduct())->setName('TEST Inaktives Produkt')->setDescription('Nicht öffentliches Testprodukt.')->setFixedAmount('50')->setActive(false)->setPosition(2)->addAcceptingCompany($shop);
        foreach ([$variable, $fixed, $inactive] as $product) { $this->slugs->assign($product); $manager->persist($product); }
        $manager->flush();
        $actor = $this->users->findOneBy(['email' => 'admin@example.local']);
        $redeemer = (new User())->setEmail('redeemer@example.local')->setFirstName('TEST')->setLastName('Einlöser')->setRoles(['ROLE_VOUCHER_REDEEMER'])->setCompany($shop);
        $redeemer->setPassword($this->hasher->hashPassword($redeemer, 'redeemer123')); $manager->persist($redeemer); $manager->flush();
        $now = $this->clock->now();
        for ($index = 0; $index < 15; ++$index) {
            $product = $index === 8 ? $fixed : $variable; $amount = match ($index) { 8 => '25', 9 => '10', 10 => '100', 11 => '250', default => '50' };
            $activation = $index === 5 ? $now->modify('-37 months') : $now->modify('-1 day');
            $voucher = new Voucher($product, self::code($index), $amount, $activation, $index === 6 ? $now->modify('+2 days') : null);
            $voucher->setNote('TEST interne Notiz '.$index);
            if ($index !== 0) { $voucher->activate($activation); }
            $manager->persist($voucher); $manager->flush();
            $parts = match ($index) { 2 => ['12.50'], 3 => ['50.00'], 7 => ['10.00', '15.00'], default => [] };
            foreach ($parts as $partIndex => $part) {
                $before = $voucher->getRemainingAmount(); $date = $now->modify('-'.(3 - $partIndex).' hours'); $voucher->redeem($part, $date);
                $entry = new VoucherRedemption($voucher, $shop, $actor, $part, $before, $voucher->getRemainingAmount(), $date,
                    hash('sha256', 'TEST-'.$index.'-'.$partIndex), hash('sha256', 'TEST fingerprint '.$index.'-'.$partIndex), 'TEST-REF-'.$index, 'TEST Auditnotiz');
                $manager->persist($entry); $manager->flush();
            }
            if ($index === 4) { $voucher->block($now); $manager->flush(); }
        }
    }
}
