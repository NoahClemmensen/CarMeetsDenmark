<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\ParticipationStatus;
use App\Repository\ParticipationRepository;
use App\Repository\TeamMemberRepository;
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
    public const string POST_TO_FEED = 'POST_TO_FEED';
    public const string HYPE = 'HYPE';

    private const array SUPPORTED_ATTRIBUTES = [
        self::VIEW,
        self::SAVE,
        self::DELETE,
        self::INTERACT,
        self::CREATE,
        self::POST_TO_FEED,
        self::HYPE,
    ];

    private const array FEED_ELIGIBLE_ROLES = ['ROLE_PHOTOGRAPHER', 'ROLE_INFLUENCER'];

    public function __construct(
        private readonly ParticipationRepository $participationRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof Event) {
            return false;
        }
        $user = $token->getUser();

        if ($user instanceof User && ($user->isAdmin() || $user->isSupport())) {
            return true;
        }

        if ($subject->isPrivate()) {
            if (!$user instanceof User) {
                return false;
            }
            if ($subject->getHost() !== $user) {
                $team = $subject->getTeam();
                if ($team === null) {
                    return false;
                }
                $membership = $this->teamMemberRepository->findOneFor($team, $user);
                if ($membership === null) {
                    return false;
                }
            }
        }

        return match ($attribute) {
            self::VIEW => true,
            self::INTERACT => $user instanceof User,
            self::SAVE => $user instanceof User && $subject->getHost() === $user && $this->isActionable($subject),
            self::DELETE => $user instanceof User && $subject->getHost() === $user,
            self::HYPE => $user instanceof User && $this->isActionable($subject),
            self::POST_TO_FEED => $this->canPostToFeed($subject, $user instanceof User ? $user : null),
            default => false,
        };
    }

    private function isActionable(Event $event): bool
    {
        return !$event->isDeleted() && !$event->isArchived() && !$event->hasStarted();
    }

    protected function canPostToFeed(Event $event, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($event->getHost() === $user) {
            return true;
        }

        $userRoles = $user->getRoles();
        $hasEligibleRole = array_any(
            self::FEED_ELIGIBLE_ROLES,
            static fn (string $role) => in_array($role, $userRoles, true),
        );

        if (!$hasEligibleRole) {
            return false;
        }

        $participation = $this->participationRepository->findForEventAndUser($event, $user);
        if ($participation === null) {
            return false;
        }

        return in_array(
            $participation->getStatus(),
            [ParticipationStatus::Going, ParticipationStatus::Maybe],
            true,
        );
    }
}
