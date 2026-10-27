<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use MoonShine\Symfony\Tests\Fixtures\Doctrine\Article;
use MoonShine\Symfony\Tests\Fixtures\Doctrine\ArticleEventSubscriber;
use MoonShine\Symfony\Tests\Fixtures\Doctrine\ArticleStatus;
use MoonShine\Symfony\Tests\Fixtures\Doctrine\Category;
use MoonShine\Symfony\Tests\Fixtures\Doctrine\Tag;
use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DoctrineResourceTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient(['environment' => 'doctrine']);
        $this->client->disableReboot();

        $em = $this->em();
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        $news = new Category('News');
        $em->persist($news);
        $em->persist(new Category('Blog'));
        $em->persist(new Tag('php'));
        $em->persist(new Tag('symfony'));

        foreach ([['Alpha', true, 10], ['Beta', false, 5], ['Gamma', true, null]] as [$title, $published, $views]) {
            $article = new Article($title, $published, $views);
            $article->setCategory($title === 'Alpha' ? $news : null);
            $em->persist($article);
        }

        $em->flush();
        $em->clear();

        ArticleEventSubscriber::$log = [];
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get('doctrine.orm.entity_manager');
    }

    private function token(): string
    {
        if ($this->client->getCookieJar()->get('XSRF-TOKEN') === null) {
            $this->client->request('GET', '/admin');
        }

        return (string) $this->client->getCookieJar()->get('XSRF-TOKEN')?->getValue();
    }

    private function article(int $id): ?Article
    {
        $this->em()->clear();

        return $this->em()->find(Article::class, $id);
    }

    private function content(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    public function testIndexIsPaginatedAndSortedByIdDesc(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/index-page');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Gamma', $this->content());
        self::assertStringContainsString('Beta', $this->content());
        self::assertStringNotContainsString('Alpha', $this->content());
        self::assertSelectorExists('a[href*="page=2"]');

        $this->client->request('GET', '/admin/resource/article-resource/index-page', ['page' => 2]);
        self::assertStringContainsString('Alpha', $this->content());
        self::assertStringNotContainsString('Gamma', $this->content());
    }

    public function testSorting(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/index-page', ['sort' => 'title']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Alpha', $this->content());
        self::assertStringContainsString('Beta', $this->content());
        self::assertStringNotContainsString('Gamma', $this->content());
    }

    public function testUnknownSortColumnIsIgnored(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/index-page', ['sort' => 'title; DROP TABLE articles']);

        self::assertResponseIsSuccessful();
    }

    public function testSearch(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/index-page', ['search' => 'bet']);

        self::assertStringContainsString('Beta', $this->content());
        self::assertStringNotContainsString('Gamma', $this->content());
        self::assertStringNotContainsString('Alpha', $this->content());
    }

    public function testFilters(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/index-page', [
            'filter' => ['published' => '1'],
            'sort' => 'title',
        ]);

        self::assertStringContainsString('Alpha', $this->content());
        self::assertStringContainsString('Gamma', $this->content());
        self::assertStringNotContainsString('Beta', $this->content());
    }

    public function testQueryTag(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/index-page', ['query-tag' => 'published']);

        self::assertStringContainsString('Gamma', $this->content());
        self::assertStringNotContainsString('Beta', $this->content());
    }

    public function testDetailAndFormPages(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/detail-page/2');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Beta', $this->content());

        $this->client->request('GET', '/admin/resource/article-resource/form-page/2');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="title"][value="Beta"]');

        $this->client->request('GET', '/admin/resource/article-resource/detail-page/999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testCreate(): void
    {
        $this->client->xmlHttpRequest('POST', '/admin/resource/article-resource/crud', [
            'title' => 'Delta',
            'published' => '1',
            'views' => '42',
        ], server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);

        self::assertResponseStatusCodeSame(201);

        $article = $this->article(4);
        self::assertNotNull($article);
        self::assertSame('Delta', $article->getTitle());
        self::assertTrue($article->isPublished());
        self::assertSame(42, $article->getViews());
    }

    public function testUpdate(): void
    {
        $this->client->request('POST', '/admin/resource/article-resource/crud/2/update', [
            '_token' => $this->token(),
            'title' => 'Beta updated',
            'published' => '1',
            'views' => '',
        ]);

        self::assertResponseRedirects();

        $article = $this->article(2);
        self::assertSame('Beta updated', $article?->getTitle());
        self::assertTrue($article->isPublished());
        self::assertNull($article->getViews());
    }

    public function testDelete(): void
    {
        $this->client->xmlHttpRequest('POST', '/admin/resource/article-resource/crud/1/delete', server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);

        self::assertResponseIsSuccessful();
        self::assertNull($this->article(1));
    }

    public function testMassDelete(): void
    {
        $this->client->xmlHttpRequest('POST', '/admin/resource/article-resource/crud/mass-delete', ['ids' => [1, 3]], server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);

        self::assertResponseIsSuccessful();
        self::assertNull($this->article(1));
        self::assertNotNull($this->article(2));
        self::assertNull($this->article(3));
    }

    public function testAssociations(): void
    {
        $this->client->xmlHttpRequest('POST', '/admin/resource/article-resource/crud', [
            'title' => 'Related',
            'category' => '2',
            'tags' => ['1', '2'],
        ], server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);

        self::assertResponseStatusCodeSame(201);

        $article = $this->article(4);
        self::assertSame('Blog', $article?->getCategory()?->getName());
        self::assertSame(['php', 'symfony'], $article->getTags()->map(static fn (Tag $tag): string => $tag->getName())->getValues());

        $this->client->request('GET', '/admin/resource/article-resource/form-page/4');
        self::assertSelectorExists('select[name="category"] option[value="2"][selected]');
        self::assertSelectorExists('select[name="tags[]"] option[value="1"][selected]');

        // clearing the relation
        $this->client->request('POST', '/admin/resource/article-resource/crud/4/update', [
            '_token' => $this->token(),
            'title' => 'Related',
            'category' => '',
        ]);

        self::assertNull($this->article(4)?->getCategory());
    }

    public function testFilterByAssociation(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/index-page', ['filter' => ['category' => '1']]);

        self::assertStringContainsString('Alpha', $this->content());
        self::assertStringNotContainsString('Gamma', $this->content());
    }

    public function testInlineUpdateFromTable(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/index-page');
        self::assertStringContainsString('/admin/update-field/column/article-resource/3', $this->content());

        $this->client->jsonRequest('PUT', '/admin/update-field/column/article-resource/3', [
            'field' => 'title',
            'value' => 'Gamma inline',
        ], server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);

        self::assertResponseStatusCodeSame(204);

        $article = $this->article(3);
        self::assertSame('Gamma inline', $article?->getTitle());
        // other fields are untouched
        self::assertTrue($article->isPublished());
    }

    public function testInlineUpdateUnknownField(): void
    {
        $this->client->jsonRequest('PUT', '/admin/update-field/column/article-resource/3', [
            'field' => 'unknown',
            'value' => 'x',
        ], server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);

        self::assertResponseStatusCodeSame(404);
    }

    private function ajax(string $method, string $uri, array $data = []): array
    {
        $this->client->xmlHttpRequest($method, $uri, $data, server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    public function testSymfonyFormConstraints(): void
    {
        $json = $this->ajax('POST', '/admin/resource/article-resource/crud', ['title' => 'ab', 'views' => '5000']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['This value is too short. It should have 3 characters or more.'], $json['errors']['title']);
        self::assertSame(['This value should be less than 1000.'], $json['errors']['views']);
        self::assertNull($this->article(4));
    }

    public function testFormTransformerErrors(): void
    {
        $json = $this->ajax('POST', '/admin/resource/article-resource/crud', ['title' => 'Valid', 'views' => 'abc']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('views', $json['errors']);
    }

    public function testUniqueEntity(): void
    {
        $json = $this->ajax('POST', '/admin/resource/article-resource/crud', ['title' => 'Alpha']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['This value is already used.'], $json['errors']['title']);
    }

    public function testUncheckedSwitcherIsFalse(): void
    {
        // MoonShine switchers submit "0" when unchecked
        $this->ajax('POST', '/admin/resource/article-resource/crud/1/update', ['title' => 'Alpha', 'published' => '0']);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->article(1)?->isPublished());

        $this->ajax('POST', '/admin/resource/article-resource/crud/1/update', ['title' => 'Alpha', 'published' => '1']);
        self::assertTrue($this->article(1)?->isPublished());
    }

    public function testEntityTypeUsesAddersAndRemovers(): void
    {
        $this->ajax('POST', '/admin/resource/article-resource/crud/1/update', ['title' => 'Alpha', 'tags' => ['1', '2']]);
        self::assertCount(2, $this->article(1)?->getTags() ?? []);

        $this->ajax('POST', '/admin/resource/article-resource/crud/1/update', ['title' => 'Alpha', 'tags' => ['2']]);
        self::assertSame(['symfony'], $this->article(1)?->getTags()->map(static fn (Tag $tag): string => $tag->getName())->getValues());
    }

    public function testRegularFormRedirectsBackWithErrors(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/form-page/2');
        $this->client->request('POST', '/admin/resource/article-resource/crud/2/update', [
            '_token' => $this->token(),
            '_component_name' => 'article-resource',
            'title' => '',
        ]);

        self::assertResponseRedirects('/admin/resource/article-resource/form-page/2');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'This value should not be blank.');
        self::assertSame('Beta', $this->article(2)?->getTitle());
    }

    public function testInlineUpdateIsValidatedByTheForm(): void
    {
        $json = [];
        $this->client->jsonRequest('PUT', '/admin/update-field/column/article-resource/3', [
            'field' => 'title',
            'value' => 'Al',
        ], server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Gamma', $this->article(3)?->getTitle());
    }

    public function testEventsOnCreateAndUpdate(): void
    {
        $this->ajax('POST', '/admin/resource/article-resource/crud', ['title' => 'Delta!!']);
        self::assertResponseStatusCodeSame(201);

        $this->ajax('POST', '/admin/resource/article-resource/crud/4/update', ['title' => 'Delta 2!']);
        self::assertResponseIsSuccessful();

        self::assertSame([
            'BeforeCreatingEvent:Delta!!',
            'AfterCreatedEvent:Delta',
            'BeforeUpdatingEvent:Delta 2!',
            'AfterUpdatedEvent:Delta 2',
        ], ArticleEventSubscriber::$log);

        self::assertSame('Delta 2', $this->article(4)?->getTitle());
    }

    public function testEventsOnDelete(): void
    {
        $this->ajax('POST', '/admin/resource/article-resource/crud/1/delete');
        $this->ajax('POST', '/admin/resource/article-resource/crud/mass-delete', ['ids' => [2, 3]]);

        self::assertSame([
            'BeforeDeletingEvent:Alpha',
            'AfterDeletedEvent:Alpha',
            'BeforeMassDeletingEvent:2,3',
            'AfterMassDeletedEvent:2,3',
        ], ArticleEventSubscriber::$log);
    }

    public function testListenerCanPreventDeletion(): void
    {
        $this->ajax('POST', '/admin/resource/article-resource/crud/1/update', ['title' => 'Protected']);

        $this->client->xmlHttpRequest('POST', '/admin/resource/article-resource/crud/1/delete', server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);

        self::assertResponseStatusCodeSame(500);
        self::assertSame('This article cannot be deleted', json_decode((string) $this->client->getResponse()->getContent(), true)['message'] ?? null);
        self::assertNotNull($this->article(1));
    }

    public function testFiltersAndBlankFormWorkWithConstructorArguments(): void
    {
        $this->client->request('GET', '/admin/resource/article-resource/index-page', [
            'filter' => ['title' => 'alp', 'status' => 'draft'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Alpha', $this->content());
        self::assertStringNotContainsString('Gamma', $this->content());
        // the filter form keeps the submitted values
        self::assertSelectorExists('input[name="filter[title]"][value="alp"]');

        $this->client->request('GET', '/admin/resource/article-resource/form-page');
        self::assertResponseIsSuccessful();
    }

    public function testEnumColumns(): void
    {
        $this->ajax('POST', '/admin/resource/article-resource/crud/1/update', ['title' => 'Alpha', 'status' => 'published']);
        self::assertResponseIsSuccessful();
        self::assertSame(ArticleStatus::Published, $this->article(1)?->getStatus());

        // inline edit from the table
        $this->client->jsonRequest('PUT', '/admin/update-field/column/article-resource/2', [
            'field' => 'status',
            'value' => 'published',
        ], server: ['HTTP_X_XSRF_TOKEN' => $this->token()]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(ArticleStatus::Published, $this->article(2)?->getStatus());

        $this->client->request('GET', '/admin/resource/article-resource/index-page', ['filter' => ['status' => 'published'], 'sort' => 'title']);
        self::assertStringContainsString('Alpha', $this->content());
        self::assertStringContainsString('Beta', $this->content());
        self::assertStringNotContainsString('Gamma', $this->content());
    }

    public function testSortingTriggersNoDoctrineDeprecation(): void
    {
        \Doctrine\Deprecations\Deprecation::enableTrackingDeprecations();
        $before = \Doctrine\Deprecations\Deprecation::getTriggeredDeprecations();

        $this->client->request('GET', '/admin/resource/article-resource/index-page', ['sort' => '-title']);
        self::assertResponseIsSuccessful();

        $new = array_diff_key(\Doctrine\Deprecations\Deprecation::getTriggeredDeprecations(), $before);
        $sorting = array_filter(array_keys($new), static fn (string $link): bool => str_contains($link, '12227') || str_contains($link, 'order'));

        self::assertSame([], array_values($sorting));
    }
}
