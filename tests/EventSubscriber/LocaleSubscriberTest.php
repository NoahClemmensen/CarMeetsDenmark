<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\LocaleSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\LocaleSwitcher;

final class LocaleSubscriberTest extends TestCase
{
    private function event(Request $request): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function subscriber(?User $user): LocaleSubscriber
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        return new LocaleSubscriber(['en', 'da'], 'en', $security, $this->createMock(LocaleSwitcher::class));
    }

    public function testUserLanguageWinsAndIsNormalized(): void
    {
        $user = (new User())->setLanguage('da-DK');
        $request = new Request();

        $this->subscriber($user)->onKernelRequest($this->event($request));

        self::assertSame('da', $request->getLocale());
    }

    public function testUnknownUserLanguageFallsBackToAcceptLanguage(): void
    {
        $user = (new User())->setLanguage('fr-FR');
        $request = new Request();
        $request->headers->set('Accept-Language', 'da-DK,da;q=0.9,en;q=0.8');

        $this->subscriber($user)->onKernelRequest($this->event($request));

        self::assertSame('da', $request->getLocale());
    }

    public function testGuestUsesAcceptLanguage(): void
    {
        $request = new Request();
        $request->headers->set('Accept-Language', 'da-DK,da;q=0.9,en;q=0.8');

        $this->subscriber(null)->onKernelRequest($this->event($request));

        self::assertSame('da', $request->getLocale());
    }

    public function testGuestWithNoMatchFallsBackToDefault(): void
    {
        $request = new Request();
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9');

        $this->subscriber(null)->onKernelRequest($this->event($request));

        self::assertSame('en', $request->getLocale());
    }

    public function testResolvedLocaleIsPushedToLocaleSwitcher(): void
    {
        $user = (new User())->setLanguage('da');
        $request = new Request();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $switcher = $this->createMock(LocaleSwitcher::class);
        $switcher->expects(self::once())->method('setLocale')->with('da');

        $subscriber = new LocaleSubscriber(['en', 'da'], 'en', $security, $switcher);
        $subscriber->onKernelRequest($this->event($request));

        self::assertSame('da', $request->getLocale());
    }

    public function testSubRequestIsIgnored(): void
    {
        $request = new Request();
        $request->setLocale('da');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->subscriber(null)->onKernelRequest($event);

        self::assertSame('da', $request->getLocale());
    }
}
