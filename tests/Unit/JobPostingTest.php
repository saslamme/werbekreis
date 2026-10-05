<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Company, JobPosting};
use App\Enum\{EmploymentType, WorkModel, SalaryPeriod, NewsStatus};
use App\Service\NewsPublishing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\{Clock, MockClock};
use Symfony\Component\Validator\{Validation, Constraints\Callback};

final class JobPostingTest extends TestCase
{
    public static function visibility(): iterable
    {
        yield [NewsStatus::Draft, null, null, false];
        yield [NewsStatus::Scheduled, null, null, false];
        yield [NewsStatus::Scheduled, '+1 second', null, false];
        yield [NewsStatus::Scheduled, 'now', null, true];
        yield [NewsStatus::Scheduled, '-1 hour', '+1 second', true];
        yield [NewsStatus::Published, null, null, true];
        yield [NewsStatus::Published, '+1 second', null, false];
        yield [NewsStatus::Published, 'now', 'now', true];
        yield [NewsStatus::Published, '-1 day', '-1 second', false];
        yield [NewsStatus::Published, null, '-1 second', false];
    }
    #[DataProvider('visibility')]
    public function testPublicationAndExpiryInclusiveBoundaries(NewsStatus $status, ?string $start, ?string $end, bool $visible): void
    {
        $clock = new MockClock('2030-05-01 12:00Z'); $job = (new JobPosting($clock->now()))->setCompany(new Company())->setStatus($status)
            ->setPublishedAt($start !== null ? $clock->now()->modify($start) : null)->setValidThrough($end !== null ? $clock->now()->modify($end) : null);
        self::assertSame($visible, $job->isPubliclyVisible($clock->now()));
        self::assertSame($end === '-1 second', $job->isExpired($clock->now()));
        $job->getCompany()->setActive(false); self::assertFalse($job->isPubliclyVisible($clock->now())); self::assertFalse((new JobPosting())->setStatus($status)->isPubliclyVisible($clock->now()));
    }
    public function testClockAdvancesSchedulingAndExpiryWithoutCron(): void
    {
        $clock = new MockClock('2030-05-01 12:00Z'); $job = (new JobPosting($clock->now()))->setCompany(new Company())->setStatus(NewsStatus::Scheduled)->setPublishedAt($clock->now()->modify('+1 hour'))->setValidThrough($clock->now()->modify('+2 hours'));
        self::assertTrue($job->isScheduled($clock->now())); self::assertSame('Geplant', $job->statusAt($clock->now()));
        $clock->modify('+1 hour'); self::assertTrue($job->isPubliclyVisible($clock->now())); self::assertSame('Veröffentlicht', $job->statusAt($clock->now()));
        $clock->modify('+1 hour'); self::assertTrue($job->isPubliclyVisible($clock->now())); $clock->modify('+1 second'); self::assertFalse($job->isPubliclyVisible($clock->now())); self::assertSame('Abgelaufen', $job->statusAt($clock->now()));
        self::assertSame(NewsStatus::Scheduled, $job->getStatus());
    }
    public function testSharedPublishingAndTimestampsUseClock(): void
    {
        $previous = Clock::get(); $clock = new MockClock('2030-05-01 12:00Z'); Clock::set($clock);
        try {
            $job = (new JobPosting())->setStatus(NewsStatus::Published); $publishing = new NewsPublishing($clock); $publishing->prepareForSave($job);
            self::assertEquals($clock->now(), $job->getCreatedAt()); self::assertEquals($clock->now(), $job->getPublishedAt());
            $clock->modify('+1 hour'); $job->touch(); self::assertEquals($clock->now(), $job->getUpdatedAt());
            $job->setStatus(NewsStatus::Scheduled)->setPublishedAt($clock->now()); self::assertTrue($publishing->invalidScheduleChange($job, NewsStatus::Draft, null));
            $job->setPublishedAt($clock->now()->modify('+1 second')); self::assertFalse($publishing->invalidScheduleChange($job, NewsStatus::Draft, null));
        } finally { Clock::set($previous); }
    }
    public static function salaries(): iterable
    {
        yield ['-1', null, 'salaryMin']; yield ['1.001', null, 'salaryMin']; yield ['100000000', null, 'salaryMin'];
        yield ['20', '10', 'salaryMax']; yield [null, '-2', 'salaryMax'];
    }
    #[DataProvider('salaries')]
    public function testInvalidSalariesRemainAvailableForValidation(?string $min, ?string $max, string $path): void
    {
        $job = $this->validJob()->setSalaryMin($min)->setSalaryMax($max)->setSalaryPeriod(SalaryPeriod::Hourly);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $errors = [...$validator->validateProperty($job, $path), ...Validation::createValidator()->validate($job, new Callback('validate'))];
        self::assertContains($path, array_map(static fn ($error) => $error->getPropertyPath(), $errors));
    }
    public function testDecimalSalaryFormattingAndOptionalRange(): void
    {
        $job = $this->validJob()->setSalaryMin('2500.5')->setSalaryMax('3200')->setSalaryPeriod(SalaryPeriod::Monthly);
        self::assertSame('2500.50', $job->getSalaryMin()); self::assertSame('3200.00', $job->getSalaryMax()); self::assertSame('2.500,50 €', $job->getFormattedSalaryMin());
        self::assertCount(0, Validation::createValidator()->validate($job, new Callback('validate')));
        $job->setSalaryPeriod(null); self::assertSame('salaryPeriod', Validation::createValidator()->validate($job, new Callback('validate'))[0]->getPropertyPath());
    }
    public function testValidationForCompanyLocationApplicationsAndDates(): void
    {
        $job = $this->validJob()->setCity('')->setCompany(null)->setApplicationEmail(null)->setStatus(NewsStatus::Scheduled)->setLatitude(52);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(); self::assertCount(1, $validator->validateProperty($job, 'company'));
        $paths = array_map(static fn ($error) => $error->getPropertyPath(), iterator_to_array(Validation::createValidator()->validate($job, new Callback('validate'))));
        foreach (['city', 'publishedAt', 'latitude', 'applicationEmail'] as $path) { self::assertContains($path, $paths); }
        $job = $this->validJob()->setPublishedAt(new \DateTimeImmutable('2030-05-01'))->setValidThrough(new \DateTimeImmutable('2030-04-30'));
        self::assertSame('validThrough', Validation::createValidator()->validate($job, new Callback('validate'))[0]->getPropertyPath());
        $job = $this->validJob()->setWorkModel(WorkModel::Remote)->setCity(''); self::assertCount(0, Validation::createValidator()->validate($job, new Callback('validate')));
    }
    public function testCompanyHelpersAndCopiedLocationAreIndependent(): void
    {
        $a = (new Company())->setCity('Haselünne')->setStreet('Beispielweg')->setEmail('kontakt@example.org'); $b = new Company(); $job = new JobPosting();
        $a->addJobPosting($job); self::assertSame($a, $job->getCompany()); self::assertCount(1, $a->getJobPostings());
        $job->copyLocationFromCompany($a); $a->setCity('Meppen'); self::assertSame('Haselünne', $job->getCity()); self::assertSame('kontakt@example.org', $job->getEffectiveApplicationEmail());
        $job->setApplicationEmail('job@example.org'); self::assertSame('job@example.org', $job->getEffectiveApplicationEmail());
        $job->setCompany($b); self::assertCount(0, $a->getJobPostings()); self::assertCount(1, $b->getJobPostings()); $b->removeJobPosting($job); self::assertNull($job->getCompany());
    }
    public function testEnumsCentralizeGermanLabelsAndSchemaMapping(): void
    {
        self::assertCount(7, EmploymentType::cases());
        self::assertSame(['Vollzeit', 'Teilzeit', 'Minijob', 'Ausbildung', 'Praktikum', 'Werkstudent', 'Befristet'], array_map(static fn ($type) => $type->label(), EmploymentType::cases()));
        self::assertSame(['FULL_TIME', 'PART_TIME', 'PART_TIME', 'OTHER', 'INTERN', 'PART_TIME', 'TEMPORARY'], array_map(static fn ($type) => $type->schemaValue(), EmploymentType::cases()));
        self::assertSame(['Vor Ort', 'Hybrid', 'Remote'], array_map(static fn ($type) => $type->label(), WorkModel::cases()));
        self::assertSame(['HOUR', 'MONTH', 'YEAR'], array_map(static fn ($type) => $type->schemaValue(), SalaryPeriod::cases()));
    }
    private function validJob(): JobPosting
    {
        return (new JobPosting())->setCompany(new Company())->setCity('Haselünne')->setApplicationEmail('job@example.org');
    }
}
