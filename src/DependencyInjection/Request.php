<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use MoonShine\Core\AbstractRequest;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

final class Request extends AbstractRequest
{
    public const OLD_INPUT_KEY = '_moonshine_old_input';

    public const ERRORS_KEY = '_moonshine_errors';

    private const FLASHES_ATTRIBUTE = '_moonshine_flashes';

    /**
     * Request attribute with values that override the submitted input
     */
    public const INPUT_ATTRIBUTE = '_moonshine_input';

    private const UNTRIMMED = ['password', 'password_confirmation', 'current_password'];

    /** @var Collection<array-key, mixed>|null */
    private ?Collection $normalized = null;

    public function __construct(
        ServerRequestInterface $request,
        private readonly SymfonyRequest $symfonyRequest,
    ) {
        parent::__construct($request);
    }

    /**
     * Request data normalized the way MoonShine fields expect it in Laravel
     * (TrimStrings and ConvertEmptyStringsToNull middlewares)
     *
     * @return Collection<array-key, mixed>
     */
    public function getAll(): Collection
    {
        $overrides = $this->symfonyRequest->attributes->get(self::INPUT_ATTRIBUTE, []);

        return $this->normalized ??= parent::getAll()
            ->replace(\is_array($overrides) ? $overrides : [])
            ->map(fn (mixed $value, int|string $key): mixed => $this->normalize($value, (string) $key));
    }

    private function normalize(mixed $value, string $key): mixed
    {
        if (\is_array($value)) {
            foreach ($value as $nestedKey => $nested) {
                $value[$nestedKey] = $this->normalize($nested, (string) $nestedKey);
            }

            return $value;
        }

        if (! \is_string($value)) {
            return $value;
        }

        if (! \in_array($key, self::UNTRIMMED, true)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
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
