<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Repository\PostReactionRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    private const int POSTS_PAGE_SIZE = 20;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PostRepository $postRepository,
    ) {
    }

    #[Route('/{uuid}', name: 'app_user_show', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    public function show(
        string $uuid,
        PostReactionRepository $postReactions,
        #[CurrentUser] User $viewer,
    ): Response {
        $profileUser = $this->userRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($profileUser === null) {
            throw $this->createNotFoundException();
        }

        $posts = $this->postRepository->findByAuthorVisibleTo($profileUser, $viewer, self::POSTS_PAGE_SIZE);
        $hypedPostIds = $postReactions->findPostIdsHypedBy($viewer, array_map(fn (Post $p) => $p->getId(), $posts));

        return $this->render('user/show.html.twig', [
            'profileUser' => $profileUser,
            'posts' => $posts,
            'hypedPostIds' => $hypedPostIds,
            'nextCursor' => $this->nextCursor($posts),
        ]);
    }

    #[Route('/{uuid}/posts', name: 'app_user_posts', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    public function posts(
        string $uuid,
        Request $request,
        PostReactionRepository $postReactions,
        #[CurrentUser] User $viewer,
    ): Response {
        $profileUser = $this->userRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($profileUser === null) {
            throw $this->createNotFoundException();
        }

        $before = $request->query->getInt('before');
        $before = $before > 0 ? $before : null;

        $posts = $this->postRepository->findByAuthorVisibleTo($profileUser, $viewer, self::POSTS_PAGE_SIZE, $before);
        $hypedPostIds = $postReactions->findPostIdsHypedBy($viewer, array_map(fn (Post $p) => $p->getId(), $posts));

        return $this->render('user/_page.html.twig', [
            'profileUser' => $profileUser,
            'posts' => $posts,
            'hypedPostIds' => $hypedPostIds,
            'before' => $before,
            'nextCursor' => $this->nextCursor($posts),
        ]);
    }

    /**
     * Cursor for the next page: the createdAt of the last post, or null when
     * the page was not full (meaning there is nothing more to load).
     *
     * @param Post[] $posts
     */
    private function nextCursor(array $posts): ?int
    {
        if (count($posts) < self::POSTS_PAGE_SIZE) {
            return null;
        }

        $last = end($posts);

        return $last ? $last->getCreatedAt() : null;
    }
}
