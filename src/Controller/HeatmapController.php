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
        #[CurrentUser] ?User $user = null,
    ): Response {
        // The activity count is no longer rendered server-side: it now reflects
        // the visible map area and is filled in by the Stimulus controller once
        // the map reports its bounds (the template shows a placeholder until then).
        return $this->render('heatmap/index.html.twig', [
            'hasActive' => $user !== null && $pingService->hasActivePing($user),
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
     * Anonymous coordinates of active pings for the heatmap overlay.
     *
     * When the client supplies a valid lat/lng bounding box (minLat, maxLat,
     * minLng, maxLng) only pings inside the current viewport are returned, which
     * keeps the payload small and the count meaningful for the area on screen.
     * Missing or nonsensical bounds fall back to every active ping. The returned
     * "count" always equals the number of points (single source of truth for the
     * "active in this area" label).
     */
    #[Route('/points', name: 'app_heatmap_points', methods: ['GET'])]
    public function points(Request $request, ActivityPingRepository $pingRepository): JsonResponse
    {
        $bounds = $this->parseBounds($request);
        $points = $bounds === null
            ? $pingRepository->findActiveCoordinates()
            : $pingRepository->findActiveCoordinatesInBounds(...$bounds);

        return $this->json(['points' => $points, 'count' => \count($points)]);
    }

    /**
     * Reads and validates the viewport bounding box from the query string.
     * Returns [minLat, maxLat, minLng, maxLng] clamped to valid coordinate
     * ranges, or null when any value is missing/non-numeric or the box is
     * degenerate (min > max) — e.g. a world-wrapped view when zoomed far out.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    private function parseBounds(Request $request): ?array
    {
        foreach (['minLat', 'maxLat', 'minLng', 'maxLng'] as $key) {
            if (!is_numeric($request->query->get($key))) {
                return null;
            }
        }

        $minLat = max(-90.0, (float) $request->query->get('minLat'));
        $maxLat = min(90.0, (float) $request->query->get('maxLat'));
        $minLng = max(-180.0, (float) $request->query->get('minLng'));
        $maxLng = min(180.0, (float) $request->query->get('maxLng'));

        if ($minLat > $maxLat || $minLng > $maxLng) {
            return null;
        }

        return [$minLat, $maxLat, $minLng, $maxLng];
    }
}
