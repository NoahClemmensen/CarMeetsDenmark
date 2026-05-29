<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Team;
use App\Entity\User;
use App\Enum\TeamRole;
use App\Repository\TeamMemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TeamVoter extends Voter
{
    public const string VIEW = 'TEAM_VIEW';
    public const string EDIT = 'TEAM_EDIT';
    public const string INVITE = 'TEAM_INVITE';
    public const string REMOVE_MEMBER = 'TEAM_REMOVE_MEMBER';
    public const string LEAVE = 'TEAM_LEAVE';
    public const string DELETE = 'TEAM_DELETE';

    private const array SUPPORTED = [
        self::VIEW, self::EDIT, self::INVITE, self::REMOVE_MEMBER, self::LEAVE, self::DELETE,
    ];

    public function __construct(
        private readonly TeamMemberRepository $teamMemberRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof Team) {
            return false;
        }

        if ($attribute === self::VIEW) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupport()) {
            return true;
        }

        $member = $this->teamMemberRepository->findOneFor($subject, $user);
        $isOwner = $member !== null && $member->getRole() === TeamRole::Owner;
        $isMember = $member !== null && $member->getRole() === TeamRole::Member;

        return match ($attribute) {
            self::EDIT, self::INVITE, self::REMOVE_MEMBER, self::DELETE => $isOwner,
            self::LEAVE => $isMember,
            default => false,
        };
    }
}
