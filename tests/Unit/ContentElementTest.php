<?php
declare(strict_types=1);
namespace App\Tests\Unit;
use App\Content\ElementTypes;
use App\Entity\{ContentElement, ContentPage, ContentMedia};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
final class ContentElementTest extends TestCase
{
    public static function types(): iterable { foreach (ElementTypes::types() as $type) { yield $type => [$type]; } }
    #[DataProvider('types')]
    public function testEveryTypeHasTypedConfigurationAndRejectsHtml(string $type): void {
        $element = new ContentElement($type); self::assertSame($type, $element->getType());
        $data = $element->getData(); if ($type === 'video') { $data['videoUrl'] = 'https://youtu.be/dQw4w9WgXcQ'; }
        self::assertSame([], ElementTypes::errors($type, $data));
        foreach (ElementTypes::fields($type) as $key => $field) {
            $invalid = $data; $invalid[$key] = '<script>alert(1)</script>';
            self::assertArrayHasKey($key, ElementTypes::errors($type, $invalid));
        }
        self::assertArrayHasKey('html', ElementTypes::errors($type, $data + ['html' => '<b>untrusted</b>']));
    }
    public static function urls(): iterable {
        foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.test', '/\\evil.test', " /kontakt", "https://example.test/\n"] as $url) { yield [$url, false]; }
        foreach (['/mitglied-werden', '/#jobs', 'https://example.test/path', 'http://example.test'] as $url) { yield [$url, true]; }
    }
    #[DataProvider('urls')]
    public function testSafeLinks(string $url, bool $valid): void { self::assertSame($valid, ElementTypes::safeUrl($url)); }
    public function testVideoProvidersAreStrictlyAllowlisted(): void {
        foreach (['https://evil.test/embed/123', 'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ', 'javascript:alert(1)', 'https://youtube.com/watch?v[]=abc', 'https://evil@youtube.com/watch?v=dQw4w9WgXcQ'] as $url) { self::assertNull(ElementTypes::videoEmbed($url)); }
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', ElementTypes::videoEmbed('https://youtu.be/dQw4w9WgXcQ'));
        self::assertSame('https://player.vimeo.com/video/123456?dnt=1', ElementTypes::videoEmbed('https://vimeo.com/123456'));
    }
    public function testRelationsOrderAnchorsAndMediaAltText(): void {
        $page = new ContentPage('home'); self::assertSame('app_home', $page->getRoute()); self::assertFalse($page->isShowDefaultHeader());
        $a = (new ContentElement())->setPosition(2)->setAnchor('angebote'); $b = (new ContentElement())->setPosition(1);
        $page->addElement($a)->addElement($b); self::assertSame([$b, $a], array_values($page->getElements()->toArray()));
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $a->setAnchor('Invalid ID'); self::assertCount(1, $validator->validateProperty($a, 'anchor'));
        $media = new ContentMedia(); self::assertNotCount(0, $validator->validate($media));
        $media->setFileName(str_repeat('a',32).'.png')->setMimeType('image/png')->setDecorative(true);
        self::assertCount(0, $validator->validate($media));
    }
}
