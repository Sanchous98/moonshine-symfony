<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Validation;

use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Feeds Laravel validator messages from the "moonshine" Symfony translation domain.
 * Placeholders are replaced by the validator itself.
 */
final class TranslatorBridge implements TranslatorContract
{
    private ?string $locale = null;

    public function __construct(private readonly TranslatorInterface $translator) {}

    /**
     * @param array<string, mixed> $replace
     */
    public function get($key, array $replace = [], $locale = null): mixed
    {
        return $this->translator->trans((string) $key, [], 'moonshine', $locale ?? $this->locale);
    }

    /**
     * @param \Countable|int|float|array<array-key, mixed> $number
     * @param array<string, mixed> $replace
     */
    public function choice($key, $number, array $replace = [], $locale = null): string
    {
        $line = $this->get($key, $replace, $locale);

        return \is_string($line) ? $line : (string) $key;
    }

    public function getLocale(): string
    {
        return $this->locale ?? ($this->translator instanceof LocaleAwareInterface ? $this->translator->getLocale() : 'en');
    }

    public function setLocale($locale): void
    {
        $this->locale = (string) $locale;
    }
}
