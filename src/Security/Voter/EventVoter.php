<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EventVoter extends Voter
{
    public const string VIEW = 'VIEW';
    public const string SAVE = 'SAVE';
    public const string DELETE = 'DELETE';
    public const string CREATE = 'CREATE';
    public const string INTERACT = 'INTERACT';

    private const array SUPPORTED_ATTRIBUTES = [
        self::VIEW,
        self::SAVE,
        self::DELETE,
        self::INTERACT,
        self::CREATE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User && !$subject instanceof Event) {
            return false;
        }

        // If admin or support they can do everything
        if ($user->isAdmin() || $user->isSupport()) return true;

        // TODO:
        // - Private can only be VIEW and PARTICIPATE for group members and host (for now ill just check if it's the host that trying to access instead of a team)
        if ($subject->isPrivate()) {
            return $subject->getHost() === $user;
        }
        // - Only host can SAVE and DELETE
        // - Only team member can CREATE events

        return match ($attribute) {
            // PoC banned people from certain events can be enforced here later.
            self::VIEW => true,
            self::SAVE, self::DELETE => $subject instanceof Event && $subject->getHost() === $user,
            default => false,
        };
    }
}
