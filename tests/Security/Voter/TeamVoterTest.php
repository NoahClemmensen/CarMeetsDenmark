<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\TeamRole;
use App\Repository\TeamMemberRepository;
use App\Security\Voter\TeamVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TeamVoterTest extends TestCase
{
    public function testViewIsAlwaysGranted(): void
    {
        $voter = $this->makeVoter(memberRole: null);
        $team = new Team();

        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor(null), $team, [TeamVoter::VIEW]));
        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($this->makeUser()), $team, [TeamVoter::VIEW]));
    }

    public function testOwnerIsGrantedEditInviteRemoveDelete(): void
    {
        $voter = $this->makeVoter(memberRole: TeamRole::Owner);
        $team = new Team();
        $owner = $this->makeUser();

        foreach ([TeamVoter::EDIT, TeamVoter::INVITE, TeamVoter::REMOVE_MEMBER, TeamVoter::DELETE] as $attr) {
            self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($owner), $team, [$attr]), $attr);
        }
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor($owner), $team, [TeamVoter::LEAVE]));
    }

    public function testMemberIsGrantedLeaveButNotAdminActions(): void
    {
        $voter = $this->makeVoter(memberRole: TeamRole::Member);
        $team = new Team();
        $member = $this->makeUser();

        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($member), $team, [TeamVoter::LEAVE]));
        foreach ([TeamVoter::EDIT, TeamVoter::INVITE, TeamVoter::REMOVE_MEMBER, TeamVoter::DELETE] as $attr) {
            self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor($member), $team, [$attr]), $attr);
        }
    }

    public function testStrangerIsDeniedAllAdminActions(): void
    {
        $voter = $this->makeVoter(memberRole: null);
        $team = new Team();
        $stranger = $this->makeUser();

        foreach ([TeamVoter::EDIT, TeamVoter::INVITE, TeamVoter::REMOVE_MEMBER, TeamVoter::DELETE, TeamVoter::LEAVE] as $attr) {
            self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor($stranger), $team, [$attr]), $attr);
        }
    }

    public function testAnonymousIsDeniedEverythingExceptView(): void
    {
        $voter = $this->makeVoter(memberRole: null);
        $team = new Team();

        foreach ([TeamVoter::EDIT, TeamVoter::INVITE, TeamVoter::REMOVE_MEMBER, TeamVoter::DELETE, TeamVoter::LEAVE] as $attr) {
            self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor(null), $team, [$attr]), $attr);
        }
    }

    public function testAdminIsGrantedEveryAttribute(): void
    {
        $voter = $this->makeVoter(memberRole: null);
        $team = new Team();
        $admin = $this->makeUser(['ROLE_ADMIN']);

        foreach ([TeamVoter::EDIT, TeamVoter::INVITE, TeamVoter::REMOVE_MEMBER, TeamVoter::DELETE] as $attr) {
            self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($admin), $team, [$attr]), $attr);
        }
    }

    private function makeVoter(?TeamRole $memberRole): TeamVoter
    {
        $repo = $this->createMock(TeamMemberRepository::class);
        if ($memberRole === null) {
            $repo->method('findOneFor')->willReturn(null);
        } else {
            $member = $this->createMock(TeamMember::class);
            $member->method('getRole')->willReturn($memberRole);
            $member->method('isOwner')->willReturn($memberRole === TeamRole::Owner);
            $repo->method('findOneFor')->willReturn($member);
        }
        return new TeamVoter($repo);
    }

    private function tokenFor(?User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }

    private function makeUser(array $roles = []): User
    {
        $user = new User();
        $user->setEmail('u' . uniqid('', true) . '@example.com');
        $user->setPassword('x');
        $user->setRoles($roles);
        return $user;
    }
}
