<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Unit;

use MoonShine\Symfony\TypeCast\DoctrineCaster;
use PHPUnit\Framework\TestCase;

final class DoctrineDataWrapperTest extends TestCase
{
    public function testWrapsEntityWithPrivateProperties(): void
    {
        $entity = new class () {
            private ?int $id = 7;

            protected string $title = 'Hello';

            public string $uninitialized;

            public function getId(): ?int
            {
                return $this->id;
            }
        };

        $wrapper = (new DoctrineCaster($entity::class))->cast($entity);

        self::assertSame($entity, $wrapper->getOriginal());
        self::assertSame(7, $wrapper->getKey());
        self::assertSame(['id' => 7, 'title' => 'Hello'], $wrapper->toArray());
    }

    public function testCustomKeyName(): void
    {
        $entity = new class () {
            public string $uuid = 'abc';
        };

        self::assertSame('abc', (new DoctrineCaster($entity::class, 'uuid'))->cast($entity)->getKey());
    }
}
