<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ActivityPingRepository;
use App\Service\ActivityPingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/heatmap')]
class HeatmapController extends AbstractController
{
    /**
     * The Hotspots page: a live activity heatmap with the ping button.
     *
     * Viewable by guests (read-only): the map and combined activity are public,
     * but only authenticated users can drop a pin (see {@see self::toggle()}).
     */
    #[Route('', name: 'app_heatmap_index', methods: ['GET'])]
    public function index(
        ActivityPingService $pingService,
        ActivityPingRepository $pingRepository,
        #[CurrentUser] ?User $user = null,
    ): Response {
        return $this->render('heatmap/index.html.twig', [
            'hasActive' => $user !== null && $pingService->hasActivePing($user),
            'activeCount' => $pingRepository->countActive(),
        ]);
    }

    /**
     * Toggles the current user's activity ping: removes it if one is active,
     * otherwise creates a new one at the posted coordinates.
     */
    #[Route('/ping', name: 'app_heatmap_ping_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(
        #[CurrentUser] User $user,
        Request $request,
        ActivityPingService $pingService,
        RateLimiterFactoryInterface $activityPingLimiter,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('activity-ping', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($pingService->hasActivePing($user)) {
            $pingService->removeActivePing($user);

            return $this->json(['active' => false]);
        }

        if (!$activityPingLimiter->create($user->getUserIdentifier())->consume(1)->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        if (!$request->request->has('lat') || !$request->request->has('lng')) {
            return $this->json(['error' => 'missing_coordinates'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $pingService->createPing(
                $user,
                (float) $request->request->get('lat'),
                (float) $request->request->get('lng'),
            );
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['active' => true]);
    }

    /**
     * Anonymous coordinates of all active pings for the heatmap overlay.
     */
    #[Route('/points', name: 'app_heatmap_points', methods: ['GET'])]
    public function points(ActivityPingRepository $pingRepository): JsonResponse
    {
        return $this->json(['points' => $pingRepository->findActiveCoordinates()]);
    }
}
