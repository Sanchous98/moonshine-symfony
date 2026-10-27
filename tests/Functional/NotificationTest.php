<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use MoonShine\Crud\Contracts\Notifications\MoonShineNotificationContract;
use MoonShine\Crud\Notifications\NotificationButton;
use MoonShine\Symfony\Notifications\AdminRecipient;
use MoonShine\Symfony\Notifications\MoonShineNotification;
use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

final class NotificationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient(['environment' => 'auth']);

        /** @var CacheItemPoolInterface $pool */
        $pool = self::getContainer()->get('cache.app');
        $pool->clear();
    }

    private function login(): void
    {
        $crawler = $this->client->request('GET', '/admin/login');
        $this->client->submit($crawler->filter('form.authentication-form')->form([
            'username' => 'admin',
            'password' => 'secret',
        ]));
    }

    private function notifier(): NotifierInterface
    {
        /** @var NotifierInterface */
        return self::getContainer()->get('test.notifier');
    }

    public function testNotifierChannelDeliversToTheUser(): void
    {
        $this->notifier()->send(
            (new MoonShineNotification('Import finished'))
                ->button(new NotificationButton('Open', '/admin/resource/post-resource/index-page')),
            new AdminRecipient('admin'),
        );
        $this->notifier()->send(new MoonShineNotification('For somebody else'), new AdminRecipient('editor'));

        $this->login();
        $crawler = $this->client->request('GET', '/admin');

        self::assertSelectorTextContains('.notifications', 'Import finished');
        self::assertSelectorTextNotContains('.notifications', 'For somebody else');
        self::assertCount(1, $crawler->filter('.notifications-more a[href="/admin/resource/post-resource/index-page"]'));
    }

    public function testBroadcastAndMarkAsRead(): void
    {
        $this->notifier()->send(
            (new Notification('Server restarted', ['moonshine']))->importance(Notification::IMPORTANCE_URGENT),
            new AdminRecipient(),
        );

        $this->login();
        $crawler = $this->client->request('GET', '/admin');

        self::assertSelectorTextContains('.notifications', 'Server restarted');
        self::assertSelectorExists('.notifications-category.text-red');

        $this->client->submit($crawler->filter('.notifications-remove')->form());
        self::assertResponseRedirects();

        $this->client->request('GET', '/admin');
        self::assertSelectorNotExists('.notifications');
    }

    public function testReadAll(): void
    {
        /** @var MoonShineNotificationContract $notifications */
        $notifications = self::getContainer()->get(MoonShineNotificationContract::class);
        $notifications->notify('First', ids: ['admin']);
        $notifications->notify('Second');

        $this->login();
        $crawler = $this->client->request('GET', '/admin');
        self::assertCount(2, $crawler->filter('.notifications-item'));

        $this->client->submit($crawler->filter('form[action="/admin/notifications/read-all"]')->form());
        $this->client->request('GET', '/admin');

        self::assertSelectorNotExists('.notifications');
    }

    public function testReadRequiresCsrfToken(): void
    {
        $this->login();
        $this->client->request('POST', '/admin/notifications/read-all');

        self::assertResponseStatusCodeSame(403);
    }
}
