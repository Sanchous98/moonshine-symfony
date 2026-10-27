<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient(['environment' => 'auth']);
    }

    private function login(string $username, string $password): void
    {
        $crawler = $this->client->request('GET', '/admin/login');
        $form = $crawler->filter('form.authentication-form')->form([
            'username' => $username,
            'password' => $password,
        ]);

        $this->client->submit($form);
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/login');
    }

    public function testGuestGetsJsonForAsyncRequests(): void
    {
        $this->client->xmlHttpRequest('GET', '/admin/component/index-page/post-resource');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginPageIsPublic(): void
    {
        $this->client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.authentication-form input[name="_token"]');
    }

    public function testSuccessfulLogin(): void
    {
        $this->login('admin', 'secret');

        self::assertResponseRedirects('/admin');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.profile', 'admin');
        self::assertSelectorExists('form[action="/admin/logout"]');
    }

    public function testFailedLoginShowsError(): void
    {
        $this->login('admin', 'wrong');

        self::assertResponseRedirects('/admin/login');

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'These credentials do not match our records.');

        $this->client->request('GET', '/admin/login');
        self::assertSelectorTextNotContains('body', 'These credentials do not match our records.');
    }

    public function testLoginWithoutCsrfTokenFails(): void
    {
        $this->client->request('POST', '/admin/login', ['username' => 'admin', 'password' => 'secret']);
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/login');
    }

    public function testUserWithoutRoleIsDenied(): void
    {
        $this->login('editor', 'secret');
        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAuthenticatedUserIsRedirectedFromLogin(): void
    {
        $this->login('admin', 'secret');
        $this->client->request('GET', '/admin/login');

        self::assertResponseRedirects('/admin');
    }

    public function testLogout(): void
    {
        $this->login('admin', 'secret');
        $crawler = $this->client->request('GET', '/admin');

        $this->client->submit($crawler->filter('form[action="/admin/logout"]')->form());
        self::assertResponseRedirects('/admin/login');

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');
    }
}
