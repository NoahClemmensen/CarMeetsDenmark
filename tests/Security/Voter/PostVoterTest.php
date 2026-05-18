<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Event;
use App\Entity\Post;
use App\Entity\User;
use App\Security\Voter\PostVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PostVoterTest extends TestCase
{
    public function testAdminIsGrantedEveryAttribute(): void
    {
        $admin = $this->makeUser(['ROLE_ADMIN']);
        $post = $this->makePost($this->makeUser([]), $this->makeUser([]));

        $voter = $this->makeVoter(viewDecision: false);

        foreach ([PostVoter::VIEW, PostVoter::EDIT, PostVoter::DELETE, PostVoter::PIN] as $attr) {
            self::assertSame(
                Voter::ACCESS_GRANTED,
                $voter->vote($this->tokenFor($admin), $post, [$attr]),
                "Admin should be granted $attr",
            );
        }
    }

    public function testSupportIsGrantedEveryAttribute(): void
    {
        $support = $this->makeUser(['ROLE_SUPPORT']);
        $post = $this->makePost($this->makeUser([]), $this->makeUser([]));

        $voter = $this->makeVoter(viewDecision: false);

        foreach ([PostVoter::VIEW, PostVoter::EDIT, PostVoter::DELETE, PostVoter::PIN] as $attr) {
            self::assertSame(
                Voter::ACCESS_GRANTED,
                $voter->vote($this->tokenFor($support), $post, [$attr]),
            );
        }
    }

    public function testViewDelegatesToAccessDecisionManagerGranted(): void
    {
        $user = $this->makeUser([]);
        $post = $this->makePost($this->makeUser([]), $this->makeUser([]));

        $voter = $this->makeVoter(viewDecision: true);
        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($user), $post, [PostVoter::VIEW]));
    }

    public function testViewDelegatesToAccessDecisionManagerDenied(): void
    {
        $user = $this->makeUser([]);
        $post = $this->makePost($this->makeUser([]), $this->makeUser([]));

        $voter = $this->makeVoter(viewDecision: false);
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor($user), $post, [PostVoter::VIEW]));
    }

    public function testAuthorCanEdit(): void
    {
        $author = $this->makeUser([]);
        $post = $this->makePost($this->makeUser([]), $author);

        $voter = $this->makeVoter();
        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($author), $post, [PostVoter::EDIT]));
    }

    public function testNonAuthorCannotEdit(): void
    {
        $post = $this->makePost($this->makeUser([]), $this->makeUser([]));
        $other = $this->makeUser([]);

        $voter = $this->makeVoter();
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor($other), $post, [PostVoter::EDIT]));
    }

    public function testHostCannotEditAnotherUsersPost(): void
    {
        $host = $this->makeUser([]);
        $post = $this->makePost($host, $this->makeUser([]));

        $voter = $this->makeVoter();
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor($host), $post, [PostVoter::EDIT]));
    }

    public function testAuthorCanDelete(): void
    {
        $author = $this->makeUser([]);
        $post = $this->makePost($this->makeUser([]), $author);

        $voter = $this->makeVoter();
        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($author), $post, [PostVoter::DELETE]));
    }

    public function testHostCanDeleteAnyPost(): void
    {
        $host = $this->makeUser([]);
        $post = $this->makePost($host, $this->makeUser([]));

        $voter = $this->makeVoter();
        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($host), $post, [PostVoter::DELETE]));
    }

    public function testStrangerCannotDelete(): void
    {
        $post = $this->makePost($this->makeUser([]), $this->makeUser([]));
        $stranger = $this->makeUser([]);

        $voter = $this->makeVoter();
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor($stranger), $post, [PostVoter::DELETE]));
    }

    public function testHostCanPin(): void
    {
        $host = $this->makeUser([]);
        $post = $this->makePost($host, $this->makeUser([]));

        $voter = $this->makeVoter();
        self::assertSame(Voter::ACCESS_GRANTED, $voter->vote($this->tokenFor($host), $post, [PostVoter::PIN]));
    }

    public function testAuthorCannotPinTheirOwnPost(): void
    {
        $author = $this->makeUser([]);
        $post = $this->makePost($this->makeUser([]), $author);

        $voter = $this->makeVoter();
        self::assertSame(Voter::ACCESS_DENIED, $voter->vote($this->tokenFor($author), $post, [PostVoter::PIN]));
    }

    public function testAbstainsOnNonPostSubject(): void
    {
        $user = $this->makeUser([]);
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor($user), new \stdClass(), [PostVoter::VIEW]),
        );
    }

    public function testAbstainsOnUnknownAttribute(): void
    {
        $user = $this->makeUser([]);
        $post = $this->makePost($this->makeUser([]), $this->makeUser([]));
        $voter = $this->makeVoter();

        self::assertSame(
            Voter::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor($user), $post, ['UNKNOWN_ATTRIBUTE']),
        );
    }

    private function makeVoter(bool $viewDecision = true): PostVoter
    {
        $adm = $this->createMock(AccessDecisionManagerInterface::class);
        $adm->method('decide')->willReturn($viewDecision);
        return new PostVoter($adm);
    }

    private function tokenFor(?User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }

    private function makePost(User $host, ?User $author): Post
    {
        $event = new Event($host);
        return new Post($event, $author);
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
