<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\ParticipationRepository;
use App\Security\Voter\EventVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class EventVoterTest extends TestCase
{
    public function testAdminIsGrantedEveryAttribute(): void
    {
        $admin = $this->makeUser(['ROLE_ADMIN']);
        $event = $this->makeEvent(host: $this->makeUser([]), private: true);

        $voter = $this->makeVoter();

        foreach ([EventVoter::VIEW, EventVoter::SAVE, EventVoter::DELETE, EventVoter::INTERACT, EventVoter::CREATE] as $attr) {
            self::assertSame(
                Voter::ACCESS_GRANTED,
                $voter->vote($this->tokenFor($admin), $event, [$attr]),
                "Admin should be granted $attr (even on private event)",
            );
        }
    }

    public function testSupportIsGrantedEveryAttribute(): void
    {
        $support = $this->makeUser(['ROLE_SUPPORT']);
        $event = $this->makeEvent(host: $this->makeUser([]), private: true);

        $voter = $this->makeVoter();

        foreach ([EventVoter::VIEW, EventVoter::SAVE, EventVoter::DELETE, EventVoter::INTERACT] as $attr) {
            self::assertSame(
                Voter::ACCESS_GRANTED,
                $voter->vote($this->tokenFor($support), $event, [$attr]),
            );
        }
    }

    public function testPrivateEventDeniesNonHost(): void
    {
        $host = $this->makeUser([]);
        $stranger = $this->makeUser([]);
        $event = $this->makeEvent(host: $host, private: true);

        $voter = $this->makeVoter();

        foreach ([EventVoter::VIEW, EventVoter::INTERACT, EventVoter::SAVE, EventVoter::DELETE] as $attr) {
            self::assertSame(
                Voter::ACCESS_DENIED,
                $voter->vote($this->tokenFor($stranger), $event, [$attr]),
                "Stranger should be denied $attr on private event",
            );
        }
    }

    public function testPrivateEventDeniesAnonymousUser(): void
    {
        $event = $this->makeEvent(host: $this->makeUser([]), private: true);
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_DENIED,
            $voter->vote($this->tokenFor(null), $event, [EventVoter::VIEW]),
        );
    }

    public function testPrivateEventHostCanView(): void
    {
        $host = $this->makeUser([]);
        $event = $this->makeEvent(host: $host, private: true);
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($host), $event, [EventVoter::VIEW]),
        );
    }

    public function testPublicEventViewIsGrantedForAnyone(): void
    {
        $event = $this->makeEvent(host: $this->makeUser([]));
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($this->tokenFor(null), $event, [EventVoter::VIEW]),
        );
        self::assertSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($this->makeUser([])), $event, [EventVoter::VIEW]),
        );
    }

    public function testPublicEventInteractIsGrantedForAnyone(): void
    {
        $event = $this->makeEvent(host: $this->makeUser([]));
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($this->makeUser([])), $event, [EventVoter::INTERACT]),
        );
    }

    public function testHostCanSave(): void
    {
        $host = $this->makeUser([]);
        $event = $this->makeEvent(host: $host);
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($host), $event, [EventVoter::SAVE]),
        );
    }

    public function testNonHostCannotSave(): void
    {
        $event = $this->makeEvent(host: $this->makeUser([]));
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_DENIED,
            $voter->vote($this->tokenFor($this->makeUser([])), $event, [EventVoter::SAVE]),
        );
    }

    public function testHostCanDelete(): void
    {
        $host = $this->makeUser([]);
        $event = $this->makeEvent(host: $host);
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($host), $event, [EventVoter::DELETE]),
        );
    }

    public function testNonHostCannotDelete(): void
    {
        $event = $this->makeEvent(host: $this->makeUser([]));
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_DENIED,
            $voter->vote($this->tokenFor($this->makeUser([])), $event, [EventVoter::DELETE]),
        );
    }

    public function testNonEventSubjectIsDenied(): void
    {
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_DENIED,
            $voter->vote($this->tokenFor($this->makeUser([])), new \stdClass(), [EventVoter::VIEW]),
        );
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $event = $this->makeEvent(host: $this->makeUser([]));
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor($this->makeUser([])), $event, ['UNKNOWN_ATTRIBUTE']),
        );
    }

    public function testPrivateEventGrantsViewToTeamMember(): void
    {
        $host = $this->makeUser([]);
        $teammate = $this->makeUser([]);

        $team = new \App\Entity\Team();
        $team->setName('T');
        $team->setDescription('D');

        $hostMembership = new \App\Entity\TeamMember($team, $host, \App\Enum\TeamRole::Owner);
        $teammateMembership = new \App\Entity\TeamMember($team, $teammate, \App\Enum\TeamRole::Member);

        $event = $this->makeEvent($host, private: true);
        $event->setTeam($team);

        $teamMemberRepo = $this->createMock(\App\Repository\TeamMemberRepository::class);
        $teamMemberRepo->method('findOneFor')->willReturnCallback(
            fn ($t, $u) => $u === $host ? $hostMembership : ($u === $teammate ? $teammateMembership : null)
        );

        $voter = $this->makeVoter($teamMemberRepo);

        self::assertSame(
            \Symfony\Component\Security\Core\Authorization\Voter\Voter::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($teammate), $event, [\App\Security\Voter\EventVoter::VIEW]),
        );
    }

    public function testPrivateEventDeniesViewToNonTeamMember(): void
    {
        $host = $this->makeUser([]);
        $outsider = $this->makeUser([]);

        $team = new \App\Entity\Team();
        $event = $this->makeEvent($host, private: true);
        $event->setTeam($team);

        $teamMemberRepo = $this->createMock(\App\Repository\TeamMemberRepository::class);
        $teamMemberRepo->method('findOneFor')->willReturn(null);

        $voter = $this->makeVoter($teamMemberRepo);

        self::assertSame(
            \Symfony\Component\Security\Core\Authorization\Voter\Voter::ACCESS_DENIED,
            $voter->vote($this->tokenFor($outsider), $event, [\App\Security\Voter\EventVoter::VIEW]),
        );
    }

    private function makeVoter(?\App\Repository\TeamMemberRepository $teamMemberRepo = null): EventVoter
    {
        $repo = $this->createMock(ParticipationRepository::class);
        $repo->method('findForEventAndUser')->willReturn(null);

        $teamMemberRepo ??= $this->createMock(\App\Repository\TeamMemberRepository::class);
        return new EventVoter($repo, $teamMemberRepo);
    }

    private function tokenFor(?User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }

    private function makeEvent(User $host, bool $private = false): Event
    {
        $event = new Event($host);
        $event->setPrivate($private);
        $event->setTeam(new \App\Entity\Team());
        return $event;
    }

    private function makeUser(array $roles): User
    {
        $user = new User();
        $user->setEmail('u' . uniqid('', true) . '@example.com');
        $user->setPassword('x');
        $user->setRoles($roles);
        return $user;
    }
}
