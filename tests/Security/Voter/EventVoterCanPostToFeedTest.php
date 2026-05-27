<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\User;
use App\Enum\ParticipationStatus;
use App\Repository\ParticipationRepository;
use App\Repository\TeamMemberRepository;
use App\Security\Voter\EventVoter;
use PHPUnit\Framework\TestCase;

final class EventVoterCanPostToFeedTest extends TestCase
{
    public function testNullUserReturnsFalse(): void
    {
        $voter = $this->makeVoter(null);
        self::assertFalse($voter->canPostToFeedPublic($this->makeEvent(), null));
    }

    public function testHostReturnsTrue(): void
    {
        $host = $this->makeUserWithRoles([]);
        $event = $this->makeEvent($host);

        $voter = $this->makeVoter(null);
        self::assertTrue($voter->canPostToFeedPublic($event, $host));
    }

    public function testPhotographerGoingReturnsTrue(): void
    {
        $user = $this->makeUserWithRoles(['ROLE_PHOTOGRAPHER']);
        $event = $this->makeEvent();
        $participation = $this->makeParticipation($event, $user, ParticipationStatus::Going);

        $voter = $this->makeVoter($participation);
        self::assertTrue($voter->canPostToFeedPublic($event, $user));
    }

    public function testInfluencerMaybeReturnsTrue(): void
    {
        $user = $this->makeUserWithRoles(['ROLE_INFLUENCER']);
        $event = $this->makeEvent();
        $participation = $this->makeParticipation($event, $user, ParticipationStatus::Maybe);

        $voter = $this->makeVoter($participation);
        self::assertTrue($voter->canPostToFeedPublic($event, $user));
    }

    public function testPhotographerDeclinedReturnsFalse(): void
    {
        $user = $this->makeUserWithRoles(['ROLE_PHOTOGRAPHER']);
        $event = $this->makeEvent();
        $participation = $this->makeParticipation($event, $user, ParticipationStatus::Declined);

        $voter = $this->makeVoter($participation);
        self::assertFalse($voter->canPostToFeedPublic($event, $user));
    }

    public function testPhotographerNoParticipationReturnsFalse(): void
    {
        $user = $this->makeUserWithRoles(['ROLE_PHOTOGRAPHER']);
        $event = $this->makeEvent();

        $voter = $this->makeVoter(null);
        self::assertFalse($voter->canPostToFeedPublic($event, $user));
    }

    public function testPlainUserReturnsFalse(): void
    {
        $user = $this->makeUserWithRoles([]);
        $event = $this->makeEvent();
        $participation = $this->makeParticipation($event, $user, ParticipationStatus::Going);

        $voter = $this->makeVoter($participation);
        self::assertFalse($voter->canPostToFeedPublic($event, $user));
    }

    private function makeVoter(?Participation $participation): TestableEventVoter
    {
        $repo = $this->createMock(ParticipationRepository::class);
        $repo->method('findForEventAndUser')->willReturn($participation);
        $teamRepo = $this->createMock(TeamMemberRepository::class);
        return new TestableEventVoter($repo, $teamRepo);
    }

    private function makeEvent(?User $host = null): Event
    {
        $host ??= $this->makeUserWithRoles([]);
        $event = new Event($host);
        $event->setTeam(new \App\Entity\Team());
        return $event;
    }

    private function makeUserWithRoles(array $roles): User
    {
        $user = new User();
        $user->setEmail('u' . uniqid('', true) . '@example.com');
        $user->setPassword('x');
        $user->setRoles($roles);
        return $user;
    }

    private function makeParticipation(Event $event, User $user, ParticipationStatus $status): Participation
    {
        return new Participation($event, $user, $status);
    }
}

/**
 * Exposes the protected canPostToFeed helper as a public method for unit testing
 * without going through the Security infrastructure.
 */
class TestableEventVoter extends EventVoter
{
    public function canPostToFeedPublic(Event $event, ?User $user): bool
    {
        return $this->canPostToFeed($event, $user);
    }
}
