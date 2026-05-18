<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Forces authenticated users without a profile (no `name` set) to complete
 * setup before they can reach any other web page.
 *
 * The original request URI is stashed in the session as `web_setup_target`
 * so {@see \App\Controller\UserSetupController} can return the user there
 * after submission.
 *
 * Bypass list: paths under /setup, /login, /logout, /register, /verify are
 * never redirected — otherwise the gate would loop on itself or block the
 * verify-email and login flows.
 *
 * Priority 6 on KernelEvents::REQUEST runs after the firewall (priority 8)
 * so `Security::getUser()` is populated.
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
            $url = $this->urlGenerator->generate('app_setup');
            $event->setResponse(new RedirectResponse($url));
        }
    }
}
