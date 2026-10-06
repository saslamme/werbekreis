<?php

declare(strict_types=1);
namespace App\Security;
use App\Entity\{Company, ContentRevision, User};
use App\Moderation\ModeratedContent;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ContentOwnershipVoter extends Voter
{
    public const VIEW = 'CONTENT_VIEW'; public const EDIT = 'CONTENT_EDIT'; public const CREATE = 'CONTENT_CREATE'; public const SUBMIT = 'CONTENT_SUBMIT'; public const REVIEW = 'CONTENT_REVIEW';
    public function __construct(private readonly ContentAccess $access) {}
    protected function supports(string $attribute, mixed $subject): bool { return in_array($attribute, [self::VIEW,self::EDIT,self::CREATE,self::SUBMIT,self::REVIEW], true) && ($subject instanceof ModeratedContent || $subject instanceof ContentRevision); }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser(); if (!$user instanceof User || !$user->isActive()) { return false; }
        return $this->access->allows($user, $subject, $attribute);
    }
}
