<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Http\TurboStreamHelper;
use App\Pagination\Pagination;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/discover')]
class DiscoverController extends AbstractController
{
    private const int PER_PAGE = 12;

    /** Sort keys accepted from the request; the first is the default. */
    private const array SORTS = ['soonest', 'hype', 'attendees'];

    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {
    }

    /**
     * GET renders the full page; the filter-form posts back here and gets a
     * Turbo Stream that swaps just the results region (cards + pagination).
     */
    #[Route('', name: 'app_discover', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        TurboStreamHelper $turbo,
        #[CurrentUser] ?User $user = null,
    ): Response {
        // GET renders the full page; the filter-form posts params, so read from
        // the matching input bag (Request::get() was removed in Symfony 8).
        $params = $request->isMethod('POST') ? $request->request : $request->query;

        $sort = (string) $params->get('sort', self::SORTS[0]);
        if (!in_array($sort, self::SORTS, true)) {
            $sort = self::SORTS[0];
        }

        $search = trim((string) $params->get('q', ''));
        $page = max(1, $params->getInt('page', 1));

        $result = $this->eventRepository->findDiscoverPage(
            $user,
            $sort,
            $search !== '' ? $search : null,
            $page,
            self::PER_PAGE,
        );

        $context = [
            'events' => $result['events'],
            'goingCounts' => $this->eventRepository->goingCountsForEvents($result['events']),
            'pagination' => new Pagination($page, self::PER_PAGE, $result['total']),
            'sort' => $sort,
            'search' => $search,
        ];

        if ($request->isMethod('POST')) {
            return $turbo
                ->replace('discover-results', 'discover/_results.html.twig', $context)
                ->makeResponse();
        }

        return $this->render('discover/discover.html.twig', $context);
    }
}
