<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{ContentPage, ContentPageSection, BoardMember};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class ContentPageTest extends TestCase
{
    public static function codes(): iterable
    {
        foreach (ContentPage::SYSTEM_PAGES as $code => $definition) {
            yield $code => [$code, $definition['url']];
        }
    }

    #[DataProvider('codes')]
    public function testFixedCodesAndUrls(string $code, string $url): void
    {
        $page = new ContentPage($code);
        self::assertSame($code, $page->getCode());
        self::assertSame($url, $page->getPublicUrl());
        self::assertSame(in_array($code, ['imprint', 'privacy'], true), $page->isLegal());
        self::assertFalse(method_exists($page, 'setCode'));
        self::assertEquals($page->getCreatedAt(), $page->getUpdatedAt());
    }

    public function testArbitraryCodesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ContentPage('free-url');
    }

    public function testSectionRelationsAndOrderingWorkBeforeFlush(): void
    {
        $page = new ContentPage('about');
        $other = new ContentPage('contact');
        $late = (new ContentPageSection())->setBody('Später')->setPosition(20);
        $first = (new ContentPageSection())->setBody('Zuerst')->setPosition(10);
        $page->addSection($late)->addSection($first)->addSection($first);
        self::assertSame([$first, $late], array_values($page->getSections()->toArray()));
        self::assertSame($page, $first->getPage());
        $other->addSection($first);
        self::assertCount(1, $page->getSections());
        self::assertSame($other, $first->getPage());
        $other->removeSection($first);
        self::assertNull($first->getPage());
        self::assertCount(0, $other->getSections());
        $late->setPosition(0);
        self::assertSame($late, $page->getSections()->first());
    }

    public function testTitleSeoAndCascadedSectionValidation(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $page = (new ContentPage('about'))->setTitle('')->setMetaTitle(str_repeat('x', 181))->setMetaDescription(str_repeat('x', 301));
        foreach (['title', 'metaTitle', 'metaDescription'] as $property) {
            self::assertCount(1, $validator->validateProperty($page, $property));
        }
        $section = new ContentPageSection();
        $page->addSection($section);
        self::assertCount(1, $validator->validateProperty($page, 'sections'));
        $page->setTitle(str_repeat('x', 181));
        self::assertCount(1, $validator->validateProperty($page, 'title'));
    }

    public function testSectionRequiresBodyPageSupportedStyleAndNonnegativePosition(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $section = (new ContentPageSection())->setHeading(str_repeat('x', 181))->setStyle('html')->setPosition(-1);
        foreach (['page', 'heading', 'body', 'position', 'style'] as $property) {
            self::assertCount(1, $validator->validateProperty($section, $property));
        }
        $section->setPage(new ContentPage('about'))->setHeading(null)->setBody('Plain text')->setPosition(0);
        foreach (ContentPageSection::STYLES as $style) {
            $section->setStyle($style);
            self::assertCount(0, $validator->validate($section));
        }
    }

    public function testBoardMemberValidationAndActivation(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $member = (new BoardMember())->setPosition(-1);
        foreach (['name', 'role', 'position'] as $property) {
            self::assertCount(1, $validator->validateProperty($member, $property));
        }
        $member->setName(str_repeat('x', 181))->setRole(str_repeat('x', 181));
        self::assertCount(1, $validator->validateProperty($member, 'name'));
        self::assertCount(1, $validator->validateProperty($member, 'role'));
        self::assertTrue($member->isActive());
        $member->setActive(false)->setName('Testperson')->setRole('Funktion')->setPosition(0);
        self::assertFalse($member->isActive());
        self::assertCount(0, $validator->validate($member));
    }
}
