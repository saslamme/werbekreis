<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\JobPosting;
use App\Enum\{EmploymentType, WorkModel, SalaryPeriod, NewsStatus};
use App\Repository\CompanyRepository;
use App\Service\{DirectorySlugger, NewsPublishing};
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Fictional development/test examples with publication windows relative to the shared Clock. */
final class JobFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly CompanyRepository $companies, private readonly DirectorySlugger $slugs, private readonly NewsPublishing $publishing) {}
    public function getDependencies(): array { return [DirectoryFixtures::class]; }
    public function load(ObjectManager $manager): void
    {
        $shop = $this->companies->findOneBy(['name' => 'Musterladen Hasebogen']);
        $cafe = $this->companies->findOneBy(['name' => 'Beispielcafé Uferpause']);
        $now = $this->publishing->now();
        $rows = [
            ['Verkäufer im Einzelhandel (m/w/d)', $shop, EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Published, '-1 day', '+30 days', true],
            ['Service im Beispielcafé', $cafe, EmploymentType::PartTime, WorkModel::Onsite, NewsStatus::Published, '-2 days', '+14 days', true],
            ['Minijob im Musterladen', $shop, EmploymentType::Minijob, WorkModel::Onsite, NewsStatus::Published, '-3 days', null, false],
            ['Ausbildung im Beispielhandel', $shop, EmploymentType::Apprenticeship, WorkModel::Onsite, NewsStatus::Published, '-4 days', '+60 days', true],
            ['Praktikum im Stadtbüro', $shop, EmploymentType::Internship, WorkModel::Hybrid, NewsStatus::Published, '-5 days', '+5 days', false],
            ['Werkstudent Digitale Beispiele', $shop, EmploymentType::WorkingStudent, WorkModel::Remote, NewsStatus::Published, '-6 days', null, false],
            ['Befristete Unterstützung', $cafe, EmploymentType::Temporary, WorkModel::Hybrid, NewsStatus::Published, '-7 days', '+7 days', false],
            ['Entwurf einer Stelle', $shop, EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Draft, null, '+30 days', true],
            ['Geplante Stelle', $cafe, EmploymentType::PartTime, WorkModel::Onsite, NewsStatus::Scheduled, '+3 days', '+30 days', true],
            ['Abgelaufene Beispielstelle', $shop, EmploymentType::FullTime, WorkModel::Onsite, NewsStatus::Published, '-20 days', '-1 day', true],
            ['Automatisch offene Stelle', $cafe, EmploymentType::Minijob, WorkModel::Onsite, NewsStatus::Scheduled, '-1 hour', '+3 days', false],
            ['Zukünftige Veröffentlichung', $shop, EmploymentType::FullTime, WorkModel::Remote, NewsStatus::Published, '+2 days', '+30 days', true],
        ];
        foreach ($rows as $index => [$title, $company, $employment, $model, $status, $start, $end, $featured]) {
            $job = (new JobPosting($now))->setTitle($title)->setCompany($company)->copyLocationFromCompany($company)
                ->setShortDescription('Fiktive Beispielstelle für Entwicklung und Tests – kein reales Stellenangebot.')
                ->setDescription("Wir suchen Unterstützung für ausschließlich fiktive Aufgaben.\n\nDiese Stellenanzeige dient nur der Entwicklung des Werbekreis-Portals.")
                ->setEmploymentType($employment)->setWorkModel($model)->setStatus($status)->setPublishedAt($start !== null ? $now->modify($start) : null)
                ->setValidThrough($end !== null ? $now->modify($end) : null)->setFeatured($featured);
            if ($index % 2 === 0) { $job->setApplicationUrl('https://example.org/bewerbung')->setContactName('Fiktiver Kontakt'); }
            else { $job->setApplicationEmail('bewerbung@example.org'); }
            if ($index === 0) { $job->setSalaryMin('2500')->setSalaryMax('3200')->setSalaryPeriod(SalaryPeriod::Monthly)->setRequirements('Freude an fiktiver Beratung.')->setBenefits('Fiktive flexible Arbeitszeiten.')->setReferenceNumber('DEMO-001')->setStartsAt($now->modify('+40 days')); }
            if ($index === 1) { $job->setSalaryMin('15.50')->setSalaryPeriod(SalaryPeriod::Hourly); }
            if ($index === 4) { $job->setCity('Meppen')->setStreet(null)->setPostalCode(null)->setLatitude(null)->setLongitude(null); }
            if ($model !== WorkModel::Remote) { $job->setAddressCountry('DE'); }
            if ($model === WorkModel::Remote) { $job->setCity('')->setStreet(null)->setHouseNumber(null)->setPostalCode(null)->setLatitude(null)->setLongitude(null); }
            $this->slugs->assign($job); $manager->persist($job); $manager->flush();
        }
    }
}
