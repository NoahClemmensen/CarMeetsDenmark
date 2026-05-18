<?php

namespace App\Security\Voter;

use App\Entity\Post;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PostVoter extends Voter
{
    public const string VIEW = 'VIEW';
    public const string EDIT = 'EDIT';
    public const string DELETE = 'DELETE';
    public const string PIN = 'PIN';

    private const array SUPPORTED_ATTRIBUTES = [self::VIEW, self::EDIT, self::DELETE, self::PIN];

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ATTRIBUTES, true) && $subject instanceof Post;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof Post) {
            return false;
        }
        $user = $token->getUser();
        $event = $subject->getEvent();

        // Admin/support bypass via the parent event's voter
        if ($user instanceof User && ($user->isAdmin() || $user->isSupport())) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->accessDecisionManager->decide($token, [EventVoter::VIEW], $event),
            self::EDIT => $user instanceof User && $subject->getAuthor() === $user,
            self::DELETE => $user instanceof User && (
                $subject->getAuthor() === $user
                || $event->getHost() === $user
            ),
            self::PIN => $user instanceof User && $event->getHost() === $user,
            default => false,
        };
    }
}
