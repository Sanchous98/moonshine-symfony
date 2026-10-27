<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Unit;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use MoonShine\Symfony\Storage\FlysystemStorage;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class FlysystemStorageTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'https://files.test']);
    }

    public function testStoreListAndDelete(): void
    {
        $storage = new FlysystemStorage($this->filesystem);
        $file = new UploadedFile((new Psr17Factory())->createStream('hello'), 5, \UPLOAD_ERR_OK, 'Report.PDF');

        $path = $storage->store('reports', $file);

        self::assertIsString($path);
        self::assertMatchesRegularExpression('#^reports/[a-f0-9]{40}\.pdf$#', $path);
        self::assertSame('hello', $this->filesystem->read($path));
        self::assertTrue($storage->exists($path));
        self::assertTrue($storage->exists('reports'));
        self::assertSame([$path], $storage->getFiles('reports'));
        self::assertSame(['reports'], $storage->getDirectories(''));

        $another = new UploadedFile((new Psr17Factory())->createStream('again'), 5, \UPLOAD_ERR_OK, 'x.pdf');
        self::assertSame('reports/custom.pdf', $storage->storeAs('reports', $another, '../custom.pdf'));

        self::assertTrue($storage->delete([$path]));
        self::assertFalse($storage->delete('missing.txt'));
        self::assertTrue($storage->deleteDirectory('reports'));
        self::assertFalse($storage->exists('reports'));
    }

    public function testUrls(): void
    {
        self::assertSame('https://files.test/a/b.png', (new FlysystemStorage($this->filesystem))->getUrl('a/b.png'));
        self::assertSame('/storage/a/b.png', (new FlysystemStorage($this->filesystem, '/storage/'))->getUrl('/a/b.png'));
        self::assertSame('https://other.test/x.png', (new FlysystemStorage($this->filesystem))->getUrl('https://other.test/x.png'));
    }
}
