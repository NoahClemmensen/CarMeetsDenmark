<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Resolves the request locale once per main request, in priority order:
 *
 *   1. the authenticated user's stored {@see User::getLanguage()}
 *   2. a `_locale` value already in the session (forward-compat for a future
 *      guest switcher; never written here)
 *   3. the best `Accept-Language` match among enabled locales
 *   4. the configured default locale
 *
 * Stored values may be full BCP-47 tags (e.g. `da-DK`); they are normalized to
 * a bare enabled locale (`da`) and rejected if not enabled. Runs at priority 7,
 * after the firewall (8) so {@see Security::getUser()} is populated, and before
 * {@see WebRouteSubscriber} (6). Never starts a session.
 *
 * Because the firewall must run first, this subscriber runs *after* Symfony's
 * LocaleAwareListener (priority 15) has already synced the translator to the
 * default locale. Setting the request locale alone would leave the translator
 * on the wrong locale, so we also push the resolved locale to every
 * locale-aware service via {@see LocaleSwitcher}.
 */
readonly class LocaleSubscriber implements EventSubscriberInterface
{
    /** @param list<string> $enabledLocales */
    public function __construct(
        private array $enabledLocales,
        private string $defaultLocale,
        private Security $security,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $this->resolveLocale($request);

        $request->setLocale($locale);
        $this->localeSwitcher->setLocale($locale);
    }

    private function resolveLocale(Request $request): string
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $locale = $this->normalize($user->getLanguage());
            if ($locale !== null) {
                return $locale;
            }
        }

        if ($request->hasPreviousSession()) {
            $locale = $this->normalize($request->getSession()->get('_locale'));
            if ($locale !== null) {
                return $locale;
            }
        }

        $preferred = $this->normalize($request->getPreferredLanguage($this->enabledLocales));
        if ($preferred !== null) {
            return $preferred;
        }

        return $this->defaultLocale;
    }

    /**
     * Reduces a raw tag to its primary subtag and returns it only when enabled,
     * e.g. `da-DK` -> `da`, `en_US` -> `en`, `fr` -> null, null -> null.
     */
    private function normalize(mixed $raw): ?string
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $primary = strtolower(preg_split('/[-_]/', $raw)[0]);

        return in_array($primary, $this->enabledLocales, true) ? $primary : null;
    }
}
