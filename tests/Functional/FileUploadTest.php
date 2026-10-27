<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use MoonShine\Symfony\Tests\Fixtures\Resources\PostResource;
use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploadTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $storage;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        PostResource::seed();
        $this->client = self::createClient();

        /** @var string $cacheDir */
        $cacheDir = self::getContainer()->getParameter('kernel.cache_dir');
        $this->storage = $cacheDir . '/storage';
        (new Filesystem())->remove($this->storage);
    }

    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/plain', test: true);
    }

    private function token(): string
    {
        $this->client->request('GET', '/admin');

        return (string) $this->client->getCookieJar()->get('XSRF-TOKEN')?->getValue();
    }

    public function testFileIsStoredAndReplaced(): void
    {
        $token = $this->token();

        $this->client->request(
            'POST',
            '/admin/resource/post-resource/crud/1/update',
            ['_token' => $token, 'title' => 'With file'],
            ['cover' => $this->upload('first.txt', 'one')],
        );

        self::assertResponseRedirects();

        $first = PostResource::$items[1]['cover'] ?? null;
        self::assertIsString($first);
        self::assertMatchesRegularExpression('#^covers/[a-f0-9]{40}\.txt$#', $first);
        self::assertStringEqualsFile($this->storage . '/' . $first, 'one');

        // the form shows the stored file
        $this->client->request('GET', '/admin/resource/post-resource/form-page/1');
        self::assertStringContainsString('/storage/' . $first, (string) $this->client->getResponse()->getContent());

        // replacing removes the previous file
        $this->client->request(
            'POST',
            '/admin/resource/post-resource/crud/1/update',
            ['_token' => $token, 'title' => 'With file', 'hidden_cover' => $first],
            ['cover' => $this->upload('second.txt', 'two')],
        );

        $second = PostResource::$items[1]['cover'];
        self::assertNotSame($first, $second);
        self::assertStringEqualsFile($this->storage . '/' . $second, 'two');
        self::assertFileDoesNotExist($this->storage . '/' . $first);
    }

    public function testExistingFileIsKept(): void
    {
        PostResource::$items[1]['cover'] = 'covers/kept.txt';

        $this->client->request('POST', '/admin/resource/post-resource/crud/1/update', [
            '_token' => $this->token(),
            'title' => 'Keep',
            'hidden_cover' => 'covers/kept.txt',
        ]);

        self::assertSame('covers/kept.txt', PostResource::$items[1]['cover']);
    }

    public function testDisallowedExtensionIsRejected(): void
    {
        $this->client->xmlHttpRequest(
            'POST',
            '/admin/resource/post-resource/crud/1/update',
            ['_token' => $this->token(), 'title' => 'Bad'],
            ['cover' => $this->upload('script.php', '<?php')],
        );

        self::assertResponseStatusCodeSame(500);
        self::assertArrayNotHasKey('cover', PostResource::$items[1]);
    }

    public function testFlysystemServiceDisk(): void
    {
        // the in-memory filesystem lives in the container
        $this->client->disableReboot();
        $token = $this->token();

        $this->client->request(
            'POST',
            '/admin/resource/post-resource/crud/1/update',
            ['_token' => $token, 'title' => 'Docs'],
            ['attachments' => [$this->upload('a.txt', 'A'), $this->upload('b.txt', 'B')]],
        );

        self::assertResponseRedirects();

        /** @var \League\Flysystem\FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('test.memory_filesystem');
        $stored = PostResource::$items[1]['attachments'] ?? [];

        self::assertCount(2, $stored);
        self::assertSame('A', $filesystem->read($stored[0]));
        self::assertSame('B', $filesystem->read($stored[1]));

        $this->client->request('GET', '/admin/resource/post-resource/form-page/1');
        self::assertStringContainsString('https://cdn.example.com/' . $stored[0], (string) $this->client->getResponse()->getContent());

        // keep the first file only
        $this->client->request('POST', '/admin/resource/post-resource/crud/1/update', [
            '_token' => $token,
            'title' => 'Docs',
            'hidden_attachments' => [$stored[0]],
        ]);

        self::assertSame([$stored[0]], PostResource::$items[1]['attachments']);
        self::assertTrue($filesystem->fileExists($stored[0]));
        self::assertFalse($filesystem->fileExists($stored[1]));
    }
}
