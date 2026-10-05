<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobPosting;
use App\Enum\WorkModel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Stored facts only; remote location restrictions and missing salaries are never invented. */
final readonly class JobStructuredData
{
    public function __construct(private UrlGeneratorInterface $urls) {}

    public function forJob(JobPosting $job): array
    {
        $company = $job->getCompany() ?? throw new \LogicException('A JobPosting requires a Company.');
        $organization = ['@type' => 'Organization', 'name' => $company->getName(),
            'url' => $this->urls->generate('company_show', ['slug' => $company->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)];
        if ($company->getWebsite() !== null) { $organization['sameAs'] = $company->getWebsite(); }
        if (($logo = $company->getLogoImage()) !== null) {
            $organization['logo'] = $this->urls->generate('company_image', ['slug' => $company->getSlug(), 'fileName' => $logo->getFileName()], UrlGeneratorInterface::ABSOLUTE_URL);
        }
        $description = htmlspecialchars($job->getDescription(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        foreach (['Anforderungen' => $job->getRequirements(), 'Benefits' => $job->getBenefits()] as $label => $text) {
            if ($text !== null) { $description .= "\n\n".$label."\n".htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
        }
        $data = ['@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => $job->getTitle(), 'description' => nl2br($description),
            'datePosted' => $job->getPublicationDate()->format(DATE_ATOM), 'employmentType' => $job->getEmploymentType()->schemaValue(), 'hiringOrganization' => $organization,
            'url' => $this->urls->generate('job_show', ['slug' => $job->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)];
        if ($job->getValidThrough() !== null) { $data['validThrough'] = $job->getValidThrough()->format(DATE_ATOM); }
        if ($job->getReferenceNumber() !== null) { $data['identifier'] = ['@type' => 'PropertyValue', 'name' => $company->getName(), 'value' => $job->getReferenceNumber()]; }
        $address = array_filter(['@type' => 'PostalAddress', 'streetAddress' => trim(($job->getStreet() ?? '').' '.($job->getHouseNumber() ?? '')), 'postalCode' => $job->getPostalCode(), 'addressLocality' => $job->getCity(), 'addressCountry' => $job->getAddressCountry()]);
        if (count($address) > 1) {
            $place = ['@type' => 'Place', 'address' => $address];
            if ($job->getLocationName() !== null) { $place['name'] = $job->getLocationName(); }
            if ($job->hasCoordinates()) { $place['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $job->getLatitude(), 'longitude' => $job->getLongitude()]; }
            $data['jobLocation'] = $place;
        }
        if ($job->getWorkModel() === WorkModel::Remote) { $data['jobLocationType'] = 'TELECOMMUTE'; }
        if (($job->getSalaryMin() !== null || $job->getSalaryMax() !== null) && $job->getSalaryPeriod() !== null) {
            $value = ['@type' => 'QuantitativeValue', 'unitText' => $job->getSalaryPeriod()->schemaValue()];
            // Numeric JSON output only; storage, validation and comparisons retain decimal strings.
            if ($job->getSalaryMin() !== null) { $value['minValue'] = (float) $job->getSalaryMin(); }
            if ($job->getSalaryMax() !== null) { $value['maxValue'] = (float) $job->getSalaryMax(); }
            $data['baseSalary'] = ['@type' => 'MonetaryAmount', 'currency' => 'EUR', 'value' => $value];
        }
        return $data;
    }
}
