<?php

declare(strict_types=1);
namespace App\Tests\Functional;

use App\DataFixtures\CmsPreservingPurgerFactory;
use App\Repository\PortalSettingsRepository;
use App\Service\PortalAssetStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FooterLogoTest extends DirectoryDatabaseTestCase
{
    private array $temporary = [];
    protected function setUp(): void { parent::setUp(); $this->resetLogos(); }
    protected function tearDown(): void
    {
        $this->resetLogos();
        (new Filesystem())->remove($this->temporary);
        parent::tearDown();
    }
    private function resetLogos(): void
    {
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $settings->setLogoMaxWidth(null)->setLogoMaxHeight(null)->setFooterLogoMaxWidth(null)->setFooterLogoMaxHeight(null)->setFacebookUrl(null)->setInstagramUrl(null);
        static::getContainer()->get(PortalAssetStorage::class)->save($settings, null, true, null, true);
    }
    private function upload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'footer-functional-');
        $this->temporary[] = $path;
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="300"><rect width="1200" height="300" fill="white"/></svg>');
        return new UploadedFile($path, 'footer.svg', test: true);
    }
    public static function logos(): iterable
    {
        yield [false, false]; yield [true, false]; yield [true, true]; yield [false, true];
    }
    #[DataProvider('logos')]
    public function testFallbackMatrixAndIndependentDimensions(bool $website, bool $footer): void
    {
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $settings->setLogoMaxWidth(300)->setLogoMaxHeight(80)->setFooterLogoMaxWidth(180)->setFooterLogoMaxHeight(55);
        static::getContainer()->get(PortalAssetStorage::class)->save($settings, $website ? $this->upload() : null, footerFile: $footer ? $this->upload() : null);
        foreach (['/', '/unternehmen', '/werbekreis/ueber-uns', '/impressum'] as $url) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            foreach (['header' => $website, '.site-footer' => $website || $footer] as $selector => $image) {
                if (!$image) {
                    self::assertSelectorNotExists($selector.' .brand img');
                    self::assertSelectorTextContains($selector.' .brand', 'Werbekreis');
                    self::assertSelectorTextContains($selector.' .brand', 'Haselünne');
                    continue;
                }
                $logo = $crawler->filter($selector.' .brand img');
                $isFooter = $selector === '.site-footer';
                self::assertStringStartsWith($isFooter && $footer ? '/media/portal/footer-logo?' : '/media/portal/logo?', $logo->attr('src'));
                self::assertSame($isFooter ? '--portal-footer-logo-max-width: 180px; --portal-footer-logo-max-height: 55px;' : '--portal-logo-max-width: 300px; --portal-logo-max-height: 80px;', $logo->attr('style'));
            }
        }
    }
    private function submit(array $values = [], array $uploads = []): void
    {
        $this->client->request('GET', '/admin/settings');
        $data = $this->client->getCrawler()->selectButton('Speichern')->form()->getPhpValues();
        $data['portal_settings'] = array_replace($data['portal_settings'], $values);
        $this->client->request('POST', '/admin/settings', $data, $uploads ? ['portal_settings' => $uploads] : []);
    }
    public function testAdminUploadsReplacesRemovesAndPreservesIndependentSettings(): void
    {
        $this->login('admin');
        $this->submit(['logoMaxWidth' => '300', 'logoMaxHeight' => '80', 'footerLogoMaxWidth' => '180', 'footerLogoMaxHeight' => '55',
            'facebookUrl' => 'https://facebook.com/test', 'instagramUrl' => 'https://instagram.com/test'], ['logoFile' => $this->upload(), 'footerLogoFile' => $this->upload()]);
        self::assertResponseRedirects('/admin/settings', 303);
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $website = $settings->getLogoFileName();
        $footer = $settings->getFooterLogoFileName();
        $storage = static::getContainer()->get(PortalAssetStorage::class);
        $this->client->followRedirect();
        self::assertSelectorExists('.admin-footer-logo-preview-container img[src="/media/portal/footer-logo?v='.$footer.'"]');
        self::assertSelectorExists('input[name="portal_settings[removeFooterLogo]"]');
        $this->submit(['removeFooterLogo' => '1'], ['footerLogoFile' => $this->upload()]);
        self::assertResponseRedirects('/admin/settings', 303);
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $replacement = $settings->getFooterLogoFileName();
        self::assertNotSame($footer, $replacement);
        self::assertFileDoesNotExist($storage->path($footer));
        self::assertFileExists($storage->path($website));
        $this->submit(['removeFooterLogo' => '1']);
        self::assertResponseRedirects('/admin/settings', 303);
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        self::assertNull($settings->getFooterLogoFileName());
        self::assertNull($settings->getFooterLogoOriginalName());
        self::assertSame(180, $settings->getFooterLogoMaxWidth());
        self::assertSame(55, $settings->getFooterLogoMaxHeight());
        self::assertSame(300, $settings->getLogoMaxWidth());
        self::assertSame(80, $settings->getLogoMaxHeight());
        self::assertSame('https://facebook.com/test', $settings->getFacebookUrl());
        self::assertSame('https://instagram.com/test', $settings->getInstagramUrl());
        self::assertFileDoesNotExist($storage->path($replacement));
        self::assertFileExists($storage->path($website));
        $this->client->request('GET', '/');
        self::assertSelectorExists('.site-footer img[src="/media/portal/logo?v='.$website.'"]');
        $this->submit(['footerLogoMaxWidth' => '', 'footerLogoMaxHeight' => '']);
        self::assertResponseRedirects('/admin/settings', 303);
        $this->client->request('GET', '/');
        self::assertSelectorExists('.site-footer img[style="--portal-footer-logo-max-width: 220px; --portal-footer-logo-max-height: 70px;"]');
    }
    public function testMediaIsControlledRevalidatedAndHasNoFallback(): void
    {
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $storage = static::getContainer()->get(PortalAssetStorage::class);
        $storage->save($settings, $this->upload());
        $this->client->request('GET', '/media/portal/footer-logo');
        self::assertResponseStatusCodeSame(404);
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $storage->save($settings, null, footerFile: $this->upload());
        $this->client->request('GET', '/media/portal/footer-logo?filename=../../.env');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; style-src 'none'; sandbox");
        $etag = $this->client->getResponse()->headers->get('ETag');
        $this->client->request('GET', '/media/portal/footer-logo', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement('UPDATE PortalSettings SET footerLogoFileName = ? WHERE id = 1', ['../../.env']);
        $this->client->request('GET', '/media/portal/footer-logo');
        self::assertResponseStatusCodeSame(404);
        $em->getConnection()->executeStatement('UPDATE PortalSettings SET footerLogoFileName = NULL WHERE id = 1');
        $em->clear();
    }
    public static function invalidDimensions(): iterable
    {
        foreach (LogoDimensionsTest::invalidDimensions() as [$field, $value]) { yield ['footer'.ucfirst($field), $value]; }
    }
    #[DataProvider('invalidDimensions')]
    public function testStrictFooterDimensions(string $field, string $value): void
    {
        $this->login('admin');
        $this->submit([$field => $value]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#portal_settings_'.$field.'.is-invalid');
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        self::assertNull($settings->getFooterLogoMaxWidth());
        self::assertNull($settings->getFooterLogoMaxHeight());
    }
    public function testEditorCannotEditBranding(): void
    {
        $this->login('editor');
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/admin/settings');
            self::assertResponseStatusCodeSame(403);
        }
    }
    public function testFixturePurgerPreservesBothLogosAndAllDimensions(): void
    {
        $settings = static::getContainer()->get(PortalSettingsRepository::class)->getSettings();
        $settings->setLogoMaxWidth(300)->setLogoMaxHeight(80)->setFooterLogoMaxWidth(180)->setFooterLogoMaxHeight(55)
            ->setFacebookUrl('https://facebook.com/test')->setInstagramUrl('https://instagram.com/test');
        $storage = static::getContainer()->get(PortalAssetStorage::class);
        $storage->save($settings, $this->upload(), footerFile: $this->upload());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $before = $em->getConnection()->fetchAssociative('SELECT * FROM PortalSettings WHERE id = 1');
        (new CmsPreservingPurgerFactory())->createForEntityManager(null, $em)->purge();
        self::assertSame($before, $em->getConnection()->fetchAssociative('SELECT * FROM PortalSettings WHERE id = 1'));
        self::assertFileExists($storage->path($settings->getLogoFileName()));
        self::assertFileExists($storage->path($settings->getFooterLogoFileName()));
    }
}
