<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\MobileDetectorService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles /web/* route interception:
 * - Redirects mobile users to the app download page
 * - Redirects authenticated users without a name to /web/setup
 */
readonly class WebRouteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 6],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $target = $request->getRequestUri();

        $bypassPrefixes = ['/setup', '/login', '/logout', '/register', '/verify'];
        foreach ($bypassPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return;
            }
        }

        // Redirect authenticated users without a name to setup
        $user = $this->security->getUser();

        if ($user instanceof User && !$user->getName()) {
            $request->getSession()->set('web_setup_target', $target);
            $url = $this->urlGenerator->generate('web_setup');
            $event->setResponse(new RedirectResponse($url));
        }
    }
}
