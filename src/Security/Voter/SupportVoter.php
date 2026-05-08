<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Votes on the elevated roles `ROLE_SUPPORT` and `ROLE_ADMIN`.
 *
 * Exists so the rest of the codebase can use the class constants
 * (`SupportVoter::ROLE_SUPPORT`) instead of spelling the magic strings.
 * The decision is straightforward: grant the attribute if it appears
 * verbatim in `User::getRoles()`. There is no role hierarchy
 */
class SupportVoter extends Voter
{
    public const ROLE_SUPPORT = 'ROLE_SUPPORT';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    private const SUPPORTED_ATTRIBUTES = [
        self::ROLE_SUPPORT,
        self::ROLE_ADMIN,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return in_array($attribute, $user->getRoles(), true);
    }
}
