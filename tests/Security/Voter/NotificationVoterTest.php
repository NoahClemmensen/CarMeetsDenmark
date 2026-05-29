<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Security\Voter\NotificationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class NotificationVoterTest extends TestCase
{
    public function testRecipientCanDelete(): void
    {
        $user = $this->makeUser();
        $n = new Notification($user, NotificationType::TeamNewEvent);

        $voter = new NotificationVoter();
        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($user), $n, [NotificationVoter::DELETE]));
    }

    public function testNonRecipientCannotDelete(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $n = new Notification($owner, NotificationType::TeamNewEvent);

        $voter = new NotificationVoter();
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor($other), $n, [NotificationVoter::DELETE]));
    }

    public function testAnonymousCannotDelete(): void
    {
        $owner = $this->makeUser();
        $n = new Notification($owner, NotificationType::TeamNewEvent);

        $voter = new NotificationVoter();
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor(null), $n, [NotificationVoter::DELETE]));
    }

    public function testAdminIsGranted(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser(['ROLE_ADMIN']);
        $n = new Notification($owner, NotificationType::TeamNewEvent);

        $voter = new NotificationVoter();
        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($admin), $n, [NotificationVoter::DELETE]));
    }

    private function tokenFor(?User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }

    private function makeUser(array $roles = []): User
    {
        $u = new User();
        $u->setEmail('u' . uniqid('', true) . '@example.com');
        $u->setPassword('x');
        $u->setRoles($roles);
        return $u;
    }
}
