<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Notification;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class NotificationVoter extends Voter
{
    public const string DELETE = 'NOTIFICATION_DELETE';
    public const string MARK_READ = 'NOTIFICATION_MARK_READ';

    private const array SUPPORTED = [self::DELETE, self::MARK_READ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof Notification) {
            return false;
        }
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($user->isAdmin() || $user->isSupport()) {
            return true;
        }
        $recipient = $subject->getRecipient();
        if ($recipient->getId() !== null && $user->getId() !== null) {
            return $recipient->getId() === $user->getId();
        }
        return $recipient === $user;
    }
}
