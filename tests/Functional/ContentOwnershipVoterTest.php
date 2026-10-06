<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\ContentType;
use App\Security\ContentOwnershipVoter as Permission;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ContentOwnershipVoterTest extends MemberDatabaseTestCase
{
    public function testMemberOwnershipAcrossEveryContentType(): void
    {
        $checker = static::getContainer()->get(AuthorizationCheckerInterface::class);
        $member = $this->login('membera');

        foreach ([ContentType::Offer, ContentType::Event, ContentType::News, ContentType::Job] as $type) {
            self::assertTrue($checker->isGranted(Permission::VIEW, $this->content($type)));
            self::assertTrue($checker->isGranted(Permission::EDIT, $this->content($type)));
            self::assertTrue($checker->isGranted(Permission::SUBMIT, $this->content($type)));

            $foreign = static::getContainer()->get('doctrine')->getRepository($type->entityClass())->findOneBy(['title' => 'TEST fremd '.$type->value]);
            self::assertFalse($checker->isGranted(Permission::VIEW, $foreign));
            self::assertFalse($checker->isGranted(Permission::EDIT, $foreign));
            self::assertFalse($checker->isGranted(Permission::SUBMIT, $foreign));
        }

        self::assertTrue($member->canManageCompany($this->company('Musterladen Hasebogen')));
        self::assertFalse($member->canManageCompany($this->company('Beispielcafé Uferpause')));
    }

    public function testEditorAndAdminCanReviewGloballyButMemberCannot(): void
    {
        $checker = static::getContainer()->get(AuthorizationCheckerInterface::class);
        $foreign = static::getContainer()->get('doctrine')->getRepository(ContentType::Offer->entityClass())->findOneBy(['title' => 'TEST fremd offers']);

        foreach (['editor', 'admin'] as $role) {
            $this->login($role);
            self::assertTrue($checker->isGranted(Permission::EDIT, $foreign));
            self::assertTrue($checker->isGranted(Permission::REVIEW, $foreign));
        }

        $this->login('membera');
        self::assertFalse($checker->isGranted(Permission::REVIEW, $this->content(ContentType::Offer)));
    }

    public function testRemovingCompanyAssignmentRevokesAccessImmediately(): void
    {
        $member = $this->login('membera');
        $offer = $this->content(ContentType::Offer);
        $checker = static::getContainer()->get(AuthorizationCheckerInterface::class);
        self::assertTrue($checker->isGranted(Permission::EDIT, $offer));

        static::getContainer()->get('doctrine')->getConnection()->executeStatement(
            'DELETE FROM user_company WHERE user_id = ? AND company_id = ?',
            [$member->getId(), $offer->getCompany()->getId()],
        );
        static::getContainer()->get('doctrine')->getManager()->clear();

        $member = static::getContainer()->get('doctrine')->getRepository(User::class)->find($member->getId());
        $this->client->loginUser($member);
        $offer = static::getContainer()->get('doctrine')->getRepository(ContentType::Offer->entityClass())->find($offer->getId());
        self::assertFalse($checker->isGranted(Permission::EDIT, $offer));
    }
}
