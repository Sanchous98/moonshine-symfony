<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Unit;

use MoonShine\Symfony\Validation\LaravelValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

final class LaravelValidatorTest extends TestCase
{
    public function testStringRulesUseMoonShineTranslations(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource('php', \dirname(__DIR__, 2) . '/translations/moonshine.en.php', 'en', 'moonshine');

        $errors = (new LaravelValidator($translator))->validate(
            ['title' => 'ab', 'email' => 'nope'],
            ['title' => 'required|min:3', 'email' => ['email']],
            [],
            ['title' => 'Title'],
        );

        self::assertSame([
            'title' => ['The Title must be at least 3 characters.'],
            'email' => ['The email must be a valid email address.'],
        ], $errors);
    }
}
