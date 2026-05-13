<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SavePostDTO;
use App\Entity\User;
use App\Enum\ToastTypes;
use App\Form\PostType;
use App\Http\TurboStreamHelper;
use App\Repository\EventRepository;
use App\Repository\PostReactionRepository;
use App\Repository\PostRepository;
use App\Security\Voter\EventVoter;
use App\Security\Voter\PostVoter;
use App\Service\PostService;
use App\Service\ReactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    private const int FEED_PAGE_SIZE = 20;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly PostRepository $postRepository,
    ) {
    }

    #[Route('/event/{uuid}/post', name: 'app_event_post_create', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        #[CurrentUser] User $user,
        string $uuid,
        Request $request,
        PostService $postService,
        PostReactionRepository $postReactions,
        TurboStreamHelper $turbo,
    ): Response {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($event === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::POST_TO_FEED, $event);

        $dto = new SavePostDTO();
        $form = $this->createForm(PostType::class, $dto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $message = 'Invalid form submission.';
            foreach ($form->getErrors(true) as $error) {
                $message = $error->getMessage();
                break;
            }
            return $turbo
                ->addToast($message, ToastTypes::error->name)
                ->setCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->makeResponse();
        }

        $post = $postService->createFromDto($event, $user, $dto);

        return $turbo
            ->prepend('event-feed-posts-' . $event->getUuid(), 'web/event/feed/_post.html.twig', [
                'event' => $event,
                'post' => $post,
                'hypedPostIds' => [],
                'isHypedByCurrent' => false,
            ])
            ->hideModal()
            ->addToast('Posted!', ToastTypes::success->name)
            ->makeResponse();
    }

    #[Route('/event/{uuid}/post/new', name: 'app_event_post_new', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function newPost(string $uuid): Response
    {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($event === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::POST_TO_FEED, $event);

        $form = $this->createForm(PostType::class, new SavePostDTO());

        return $this->render('web/event/feed/_post_compose_modal.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/event/{uuid}/feed', name: 'app_event_feed_page', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    public function feedPage(
        string $uuid,
        Request $request,
        PostReactionRepository $postReactions,
        #[CurrentUser] ?User $user = null,
    ): Response {
        $event = $this->eventRepository->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
        if ($event === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::VIEW, $event);

        $before = $request->query->getInt('before');
        $before = $before > 0 ? $before : null;

        $posts = $this->postRepository->findFeedPage($event, self::FEED_PAGE_SIZE, $before);
        $hypedPostIds = $user
            ? $postReactions->findPostIdsHypedBy($user, array_map(fn($p) => $p->getId(), $posts))
            : [];

        $lastUnpinned = null;
        foreach (array_reverse($posts) as $p) {
            if (!$p->isPinned()) {
                $lastUnpinned = $p;
                break;
            }
        }
        $nextCursor = $lastUnpinned?->getCreatedAt();

        return $this->render('web/event/feed/_page.html.twig', [
            'event' => $event,
            'posts' => $posts,
            'hypedPostIds' => $hypedPostIds,
            'before' => $before,
            'nextCursor' => $nextCursor,
        ]);
    }

    #[Route('/post/{uuid}/edit-form', name: 'app_post_edit_form', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function editForm(string $uuid): Response
    {
        $post = $this->postRepository->findByUuid($uuid);
        if ($post === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(PostVoter::EDIT, $post);

        return $this->render('web/event/feed/_post_edit_modal.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/post/{uuid}/edit', name: 'app_post_edit', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        string $uuid,
        Request $request,
        PostService $postService,
        TurboStreamHelper $turbo,
    ): Response {
        $post = $this->postRepository->findByUuid($uuid);
        if ($post === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(PostVoter::EDIT, $post);

        if (!$this->isCsrfTokenValid('edit-post-' . $post->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $body = (string) $request->request->get('body');
        if (mb_strlen($body) > 2000) {
            return $turbo->addToast('Body too long.', ToastTypes::error->name)
                ->setCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->makeResponse();
        }

        $postService->editBody($post, $body);

        return $turbo
            ->replace('feed-post-' . $post->getUuid(), 'web/event/feed/_post.html.twig', [
                'event' => $post->getEvent(),
                'post' => $post,
                'hypedPostIds' => [],
                'isHypedByCurrent' => false,
            ])
            ->hideModal()
            ->addToast('Post updated.', ToastTypes::success->name)
            ->makeResponse();
    }

    #[Route('/post/{uuid}/delete-confirm', name: 'app_post_delete_confirm', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function deleteConfirm(string $uuid): Response
    {
        $post = $this->postRepository->findByUuid($uuid);
        if ($post === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(PostVoter::DELETE, $post);

        return $this->render('web/event/feed/_post_delete_modal.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/post/{uuid}/delete', name: 'app_post_delete', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        string $uuid,
        Request $request,
        PostService $postService,
        TurboStreamHelper $turbo,
    ): Response {
        $post = $this->postRepository->findByUuid($uuid);
        if ($post === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(PostVoter::DELETE, $post);

        if (!$this->isCsrfTokenValid('delete-post-' . $post->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $postService->softDelete($post);

        return $turbo
            ->remove('feed-post-' . $post->getUuid())
            ->hideModal()
            ->addToast('Post deleted.', ToastTypes::success->name)
            ->makeResponse();
    }

    #[Route('/post/{uuid}/pin', name: 'app_post_pin', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function pin(
        string $uuid,
        Request $request,
        PostService $postService,
        PostReactionRepository $postReactions,
        TurboStreamHelper $turbo,
        #[CurrentUser] ?User $user = null,
    ): Response {
        $post = $this->postRepository->findByUuid($uuid);
        if ($post === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(PostVoter::PIN, $post);

        if (!$this->isCsrfTokenValid('pin-post-' . $post->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $nowPinned = $postService->togglePin($post);

        $event = $post->getEvent();
        $posts = $this->postRepository->findFeedPage($event, self::FEED_PAGE_SIZE);
        $hypedPostIds = $user
            ? $postReactions->findPostIdsHypedBy($user, array_map(fn($p) => $p->getId(), $posts))
            : [];

        return $turbo
            ->replace('event-feed-posts-' . $event->getUuid(), 'web/event/feed/_posts_list.html.twig', [
                'event' => $event,
                'posts' => $posts,
                'hypedPostIds' => $hypedPostIds,
            ])
            ->addToast($nowPinned ? 'Pinned.' : 'Unpinned.', ToastTypes::success->name)
            ->makeResponse();
    }

    #[Route('/post/{uuid}/hype', name: 'app_post_hype', requirements: ['uuid' => self::UUID_REQUIREMENT], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function hype(
        #[CurrentUser] User $user,
        string $uuid,
        Request $request,
        ReactionService $reactionService,
        PostReactionRepository $postReactions,
        TurboStreamHelper $turbo,
    ): Response {
        $post = $this->postRepository->findByUuid($uuid);
        if ($post === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(EventVoter::VIEW, $post->getEvent());

        if (!$this->isCsrfTokenValid('hype-post-' . $post->getUuid(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $reactionService->togglePostHype($post, $user);
        $isHyped = $postReactions->findOneForPostAndUser($post, $user) !== null;

        return $turbo
            ->replace('post-hype-' . $post->getUuid(), 'web/_hype_button.html.twig', [
                'count' => $post->getHypeCount(),
                'isHyped' => $isHyped,
                'formAction' => $this->generateUrl('app_post_hype', ['uuid' => $post->getUuid()]),
                'csrfTokenName' => 'hype-post-' . $post->getUuid(),
                'targetId' => 'post-hype-' . $post->getUuid(),
            ])
            ->makeResponse();
    }
}
