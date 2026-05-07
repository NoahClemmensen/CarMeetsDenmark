<?php

namespace App\Http;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;

class TurboStreamHelper implements ResetInterface
{
    private array $streams = [];
    private int $code = 200;

    public function __construct(private readonly Environment $twig)
    {
    }

    public function addStream(string $stream): self
    {
        $this->streams[] = $stream;

        return $this;
    }

    public function remove(string $targetId): self
    {
        return $this->addStream(sprintf(
            '<turbo-stream action="remove" target="%s"></turbo-stream>',
            htmlspecialchars($targetId, ENT_QUOTES)
        ));
    }

    public function addFlash(string $message, string $type, string $targetId = 'modal-flashes'): self
    {
        return $this->addStream($this->twig->render('web/_turbo/flash_stream.html.twig', [
            'message' => $message,
            'type' => $type,
            'frameId' => $targetId,
        ]));
    }

    public function addToast(string $message, string $type = 'success', bool $dismissable = true, int $fadeOutAfterMs = 5000): self
    {
        return $this->addStream($this->twig->render('web/_turbo/toast_stream.html.twig', [
            'message' => $message,
            'type' => $type,
            'dismissable' => $dismissable,
            'fadeOutAfterMs' => $fadeOutAfterMs,
        ]));
    }

    public function replace(string $targetId, string $template, array $context = []): self
    {
        return $this->addStream(sprintf(
            '<turbo-stream action="replace" target="%s"><template>%s</template></turbo-stream>',
            htmlspecialchars($targetId, ENT_QUOTES),
            $this->twig->render($template, $context)
        ));
    }

    /**
     * Emit a `copy-to-clipboard` turbo-stream instructing the client to write
     * $value into the system clipboard. Pure side effect — no DOM update.
     *
     * Paired with the `copy-to-clipboard` custom action registered in
     * assets/turbo-actions/copy_to_clipboard.js.
     */
    public function copyToClipboard(string $value): self
    {
        return $this->addStream(sprintf(
            '<turbo-stream action="copy-to-clipboard" value="%s"></turbo-stream>',
            htmlspecialchars($value, ENT_QUOTES)
        ));
    }

    public function addRedirect(string $url, ?string $toastMessage = null, string $toastType = 'success'): self
    {
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            throw new InvalidArgumentException('Redirect URL must be a relative path.');
        }

        $attrs = sprintf('url="%s"', htmlspecialchars($url, ENT_QUOTES));

        if ($toastMessage !== null) {
            $attrs .= sprintf(
                ' toast-message="%s" toast-type="%s"',
                htmlspecialchars($toastMessage, ENT_QUOTES),
                htmlspecialchars($toastType, ENT_QUOTES)
            );
        }

        return $this->addStream(sprintf(
            '<turbo-stream action="redirect" %s><template></template></turbo-stream>',
            $attrs
        ));
    }

    public function setCode(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function makeResponse(): Response
    {
        return new Response(
            implode("\n", $this->streams),
            $this->code,
            ['Content-Type' => 'text/vnd.turbo-stream.html; charset=utf-8']
        );
    }

    public function reset(): void
    {
        $this->streams = [];
        $this->code = 200;
    }
}
