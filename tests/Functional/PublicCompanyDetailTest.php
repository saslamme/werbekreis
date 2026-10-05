<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;

final class PublicCompanyDetailTest extends PublicDirectoryTestCase
{
    public function testDetailPageShowsContactHoursAndActiveContactsOnly(): void
    {
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Musterladen Hasebogen');
        self::assertSelectorTextContains('.company-header', 'Handel');
        self::assertSelectorTextNotContains('.company-header', 'Archiv');
        self::assertSelectorTextContains('.company-header .lead', 'Mode, Geschenkideen und Wohnaccessoires');
        self::assertSelectorTextContains('#about-heading + .company-description', 'ausschließlich der lokalen Entwicklung');
        // Contact: only filled fields; this company has a website but no social media.
        self::assertSelectorTextContains('address', 'Fiktive Beispielstraße 1');
        self::assertSelectorTextContains('address', '49740 Haselünne');
        self::assertSelectorExists('.company-contact a[href="tel:0000000000"]');
        self::assertSelectorExists('.company-contact a[href="mailto:kontakt@betrieb1.example"]');
        self::assertSelectorExists('.company-contact a[href="https://betrieb1.example"][target="_blank"][rel="noopener noreferrer"]');
        self::assertSelectorTextNotContains('.company-contact', 'Facebook');
        // Opening hours grouped per day, several windows per day, Saturday absent (no data, not "closed").
        $rows = $this->client->getCrawler()->filter('.opening-hours-row')->each(static fn ($row): string => preg_replace('/\s+/', ' ', trim($row->text())));
        self::assertSame('Montag 09:00–12:30 Uhr 14:00–18:00 Uhr', $rows[0]);
        self::assertSame('Sonntag geschlossen', $rows[5]);
        self::assertCount(6, $rows);
        self::assertSelectorTextNotContains('.opening-hours', 'Samstag');
        // Contacts: primary first, then sort order; inactive "Sam Muster" is hidden.
        $contacts = $this->client->getCrawler()->filter('.contact-person h3')->each(static fn ($node): string => trim($node->text()));
        self::assertSame(['Alex Beispiel Hauptansprechpartner', 'Robin Exempel'], $contacts);
        self::assertSelectorTextNotContains('main', 'Sam Muster');
        self::assertSelectorTextContains('.contact-person:nth-of-type(1)', 'Ansprechpartner (fiktiv)');
        self::assertSelectorExists('.contact-person a[href="tel:0000000002"]');
    }

    public function testSocialLinksWhenPresent(): void
    {
        $this->client->request('GET', '/unternehmen/beispielcafe-uferpause');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.company-contact a[href="https://facebook.example/beispiel"]');
        self::assertSelectorExists('.company-contact a[href="https://instagram.example/beispiel"]');
        self::assertSelectorTextNotContains('.company-contact', 'Website');
    }

    public function testUnknownAndInactiveCompaniesAreNotFound(): void
    {
        $this->client->request('GET', '/unternehmen/gibt-es-nicht');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/unternehmen/beispielhotel-lindenhof');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/unternehmen/'.$this->firstCompany()->getId());
        self::assertResponseStatusCodeSame(404, 'Numeric IDs are no public addresses.');
    }

    public function testSeoMetadataAndStructuredData(): void
    {
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen');
        self::assertPageTitleSame('Musterladen Hasebogen | Werbekreis Haselünne');
        self::assertSelectorExists('meta[name="description"][content^="Mode, Geschenkideen und Wohnaccessoires"]');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/unternehmen/musterladen-hasebogen"]');
        self::assertSelectorExists('meta[property="og:title"][content="Musterladen Hasebogen"]');
        self::assertSelectorExists('meta[property="og:url"][content="http://localhost/unternehmen/musterladen-hasebogen"]');
        self::assertSelectorNotExists('meta[property="og:image"]', 'No image, no og:image.');
        $json = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('LocalBusiness', $json['@type']);
        self::assertSame('Musterladen Hasebogen', $json['name']);
        self::assertSame('http://localhost/unternehmen/musterladen-hasebogen', $json['url']);
        self::assertSame(['@type' => 'PostalAddress', 'streetAddress' => 'Fiktive Beispielstraße 1', 'postalCode' => '49740', 'addressLocality' => 'Haselünne', 'addressCountry' => 'DE'], $json['address']);
        self::assertSame('0000 / 000000', $json['telephone']);
        self::assertSame('kontakt@betrieb1.example', $json['email']);
        self::assertArrayNotHasKey('image', $json);
        self::assertCount(10, $json['openingHoursSpecification'], 'Five weekdays with two windows; the closed Sunday is omitted.');
        self::assertSame(['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '09:00', 'closes' => '12:30'], $json['openingHoursSpecification'][0]);
    }

    public function testListingTitleAndCanonical(): void
    {
        $this->client->request('GET', '/unternehmen');
        self::assertPageTitleSame('Unternehmen in Haselünne | Werbekreis Haselünne');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/unternehmen"]');
        self::assertSelectorExists('meta[name="description"][content^="Lokale Unternehmen in Haselünne"]');
    }

    public function testImagesAreUsedByType(): void
    {
        $this->loadImages();
        $company = $this->company('Musterladen Hasebogen');
        $logo = $company->getLogoImage();
        $cover = $company->getCoverImage();
        self::assertNotNull($logo);
        self::assertNotNull($cover);
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen');
        $base = '/unternehmen/musterladen-hasebogen/bilder/';
        self::assertSelectorExists('.company-cover img[src="'.$base.$cover->getFileName().'"][alt="Schaufenster des Musterladens"]');
        self::assertSelectorExists('.company-header .company-logo img[src="'.$base.$logo->getFileName().'"]');
        $gallery = $this->client->getCrawler()->filter('.company-gallery img')->each(static fn ($img): string => (string) $img->attr('src'));
        self::assertCount(2, $gallery);
        self::assertNotContains($base.$logo->getFileName(), $gallery);
        self::assertNotContains($base.$cover->getFileName(), $gallery);
        self::assertSelectorExists('meta[property="og:image"][content="http://localhost'.$base.$cover->getFileName().'"]');
        $json = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['http://localhost'.$base.$cover->getFileName(), 'http://localhost'.$base.$logo->getFileName()], $json['image']);
        // Card images: logo where present, neutral fallback otherwise.
        $this->client->request('GET', '/unternehmen');
        self::assertSelectorExists('.company-card .company-logo img[src="'.$base.$logo->getFileName().'"]');
        self::assertSelectorCount(2, '.company-card .company-logo img');
        self::assertSelectorCount(2, '.company-card .company-logo span[aria-hidden="true"]');
        // A company without any images has no gallery section.
        $this->client->request('GET', '/unternehmen/demo-werkstatt-stadtblick');
        self::assertSelectorNotExists('.company-gallery');
        self::assertSelectorNotExists('.company-cover');
    }

    public function testImageFilesAreServedOnlyForActiveCompanies(): void
    {
        $this->loadImages();
        $logo = $this->company('Musterladen Hasebogen')->getLogoImage();
        self::assertNotNull($logo);
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen/bilder/'.$logo->getFileName());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        // Wrong company slug, unknown names and path tricks never reach the file system.
        $this->client->request('GET', '/unternehmen/beispielcafe-uferpause/bilder/'.$logo->getFileName());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen/bilder/'.str_repeat('0', 32).'.png');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen/bilder/..%2F..%2F.env');
        self::assertResponseStatusCodeSame(404);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(\App\Entity\Company::class, $this->company('Musterladen Hasebogen')->getId())->setActive(false);
        $em->flush();
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen/bilder/'.$logo->getFileName());
        self::assertResponseStatusCodeSame(404);
    }

    public function testInactiveContactsAreHiddenEvenWhenPrimary(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $company = $this->company('Beispielcafé Uferpause');
        foreach ($company->getContactPersons() as $contact) {
            $contact->setActive(false);
        }
        $em->flush();
        $this->client->request('GET', '/unternehmen/beispielcafe-uferpause');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#contacts-heading');
        self::assertSelectorNotExists('.contact-person');
    }
}
