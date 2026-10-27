<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use Countable;
use MoonShine\Contracts\Core\DependencyInjection\TranslatorContract;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class Translator implements TranslatorContract
{
    private const DOMAIN = 'moonshine';

    public function __construct(private TranslatorInterface $translator) {}

    public function get(string $key, array $replace = [], ?string $locale = null): mixed
    {
        return $this->translator->trans(
            $this->normalizeKey($key),
            $this->normalizeReplace($replace),
            self::DOMAIN,
            $locale,
        );
    }

    public function choice(
        string $key,
        Countable|float|int|array $number,
        array $replace = [],
        ?string $locale = null,
    ): string {
        $count = match (true) {
            \is_array($number), $number instanceof Countable => \count($number),
            default => $number,
        };

        return $this->translator->trans(
            $this->normalizeKey($key),
            [...$this->normalizeReplace($replace), '%count%' => $count],
            self::DOMAIN,
            $locale,
        );
    }

    public function getLocale(): string
    {
        return $this->translator instanceof LocaleAwareInterface
            ? $this->translator->getLocale()
            : 'en';
    }

    public function all(?string $locale = null): array
    {
        if (! $this->translator instanceof TranslatorBagInterface) {
            return [];
        }

        return $this->translator->getCatalogue($locale)->all(self::DOMAIN);
    }

    private function normalizeKey(string $key): string
    {
        return str_replace('moonshine::', '', $key);
    }

    /**
     * Laravel style ":name" placeholders are used by MoonShine translations
     *
     * @param array<array-key, mixed> $replace
     * @return array<string, mixed>
     */
    private function normalizeReplace(array $replace): array
    {
        $result = [];

        foreach ($replace as $key => $value) {
            $key = (string) $key;
            $result[str_starts_with($key, ':') || str_starts_with($key, '%') ? $key : ":$key"] = $value;
        }

        return $result;
    }
}
