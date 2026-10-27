<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use Illuminate\Support\Arr;
use MoonShine\Core\AbstractRequest;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

final class Request extends AbstractRequest
{
    public const OLD_INPUT_KEY = '_moonshine_old_input';

    public const ERRORS_KEY = '_moonshine_errors';

    private const FLASHES_ATTRIBUTE = '_moonshine_flashes';

    public function __construct(
        ServerRequestInterface $request,
        private readonly SymfonyRequest $symfonyRequest,
    ) {
        parent::__construct($request);
    }

    public function getSymfonyRequest(): SymfonyRequest
    {
        return $this->symfonyRequest;
    }

    public function getSession(string $key, mixed $default = null): mixed
    {
        if (! $this->symfonyRequest->hasSession()) {
            return $default;
        }

        $session = $this->symfonyRequest->getSession();

        if ($session->has($key)) {
            return $session->get($key);
        }

        // flashes are consumed once and kept for the rest of the request
        $flashes = $this->symfonyRequest->attributes->get(self::FLASHES_ATTRIBUTE, []);

        if (! \array_key_exists($key, $flashes) && $session instanceof FlashBagAwareSessionInterface) {
            $messages = $session->getFlashBag()->get($key);
            $flashes[$key] = $messages === [] ? null : $messages[array_key_last($messages)];
            $this->symfonyRequest->attributes->set(self::FLASHES_ATTRIBUTE, $flashes);
        }

        return $flashes[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormErrors(?string $bag = null): array
    {
        $errors = $this->getSession(self::ERRORS_KEY, []);

        if (! \is_array($errors)) {
            return [];
        }

        if ($bag !== null) {
            $errors = $errors[$bag] ?? [];
        }

        return \is_array($errors) ? $errors : [];
    }

    public function getOld(string $key, mixed $default = null): mixed
    {
        $old = $this->getSession(self::OLD_INPUT_KEY, []);

        return \is_array($old) ? Arr::get($old, $key, $default) : $default;
    }

    public function getFile(string $key): mixed
    {
        return Arr::get($this->getRequest()->getUploadedFiles(), $key);
    }
}
