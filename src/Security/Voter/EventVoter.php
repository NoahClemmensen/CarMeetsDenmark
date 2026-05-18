<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\ParticipationStatus;
use App\Repository\ParticipationRepository;
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

    private const array SUPPORTED_ATTRIBUTES = [
        self::VIEW,
        self::SAVE,
        self::DELETE,
        self::INTERACT,
        self::CREATE,
        self::POST_TO_FEED,
    ];

    private const array FEED_ELIGIBLE_ROLES = ['ROLE_PHOTOGRAPHER', 'ROLE_INFLUENCER'];

    public function __construct(
        private readonly ParticipationRepository $participationRepository,
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

        if ($subject->isPrivate() && (!$user instanceof User || $subject->getHost() !== $user)) {
            return false;
        }

        return match ($attribute) {
            self::VIEW, self::INTERACT => true,
            self::SAVE, self::DELETE => $user instanceof User && $subject->getHost() === $user,
            self::POST_TO_FEED => $this->canPostToFeed($subject, $user instanceof User ? $user : null),
            default => false,
        };
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
