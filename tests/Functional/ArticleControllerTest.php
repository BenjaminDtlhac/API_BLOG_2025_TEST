<?php

namespace App\Tests\Functional;

use App\Dto\Article\UpdateArticleDto;
use App\Entity\User;
use App\Entity\Article;
use App\Repository\UserRepository;
use App\Repository\ArticleRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;

class ArticleControllerTest extends WebTestCase
{
    //Prorpiété qui va stocker notre client léger (pour envoyer des requêtes)
    private KernelBrowser $client;

    private AbstractDatabaseTool $databaseTool;

    public function setUp(): void
    {
        // Création du client léger
        $this->client = self::createClient(server: [
            'HTTP_ACCEPT' => 'application/json',
            "CONTENT_TYPE" => 'application/json'
        ]);

        $this->databaseTool = self::getContainer()->get(DatabaseToolCollection::class)->get();
    }

    private function getUser(string $username = 'admin'): ?User
    {
        // On load les fixtures
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/UserFixtures.yaml'
        ]);

        // On récupère l'utilisateur par son nom d'utilisateur 
        $user = self::getContainer()->get(UserRepository::class)
            ->findOneBy(['username' => $username]);

        // On le renvois
        return $user;
    }

    public function testIndexEndPointWithNoConnectedUser(): void
    {
        $this->client->request('GET', '/api/admin/articles');

        // $this->assertResponsesStatusCodeSame(401);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testIndexEndPointWithConnectedUser(): void
    {
        $this->client->loginUser(
            $this->getUser('user'),
            'login'
        );

        $this->client->request('GET', '/api/admin/articles');

        // $this->assertResponsesStatusCodeSame(403);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIndexEndPointWithConnectedAdmin(): void
    {
        $this->client->loginUser(
            $this->getUser('admin'),
            'login'
        );

        $this->client->request('GET', '/api/admin/articles');

        // $this->assertResponsesStatusCodeSame(403);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testIndexEndPointValidateStructureJsonResponse(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );
        $this->client->request('GET', '/api/admin/articles');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('items', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertArrayHasKey('pages', $response['meta']);
        $this->assertArrayHasKey('total', $response['meta']);
    }

    public function TestIndexEndPointValidateNumberOfItemsDefault(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        //On charge les fixtures
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);
        $this->client->request('GET', '/api/admin/articles');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(6, $response['items']);
    }

    public function testIndexEndPointValideNumberOfItemsWithLimitParamter(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles?limit=1');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(1, $response['items']);
        $this->assertEquals(12, $response['meta']['pages']);
    }

    public function testIndexEndPointValideErrorWhenLimitIsNotPositive(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles?limit=-1');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertEquals("limit: This value should be positive.", $response['detail']);
    }

    public function testIndexEndPointValideErrorWhenpAGEIsNotPositive(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles?page=-1');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertEquals("page: This value should be positive.", $response['detail']);
    }

    public function testIndexEndPointFirstItemWhenPageIsChange(): void
    {
        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles?page=2');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('Article 7', $response['items'][0]['title']);
    }

    public function testCreateEndPointWithNoConnectedUser(): void
    {
        $this->client->request('POST', '/api/admin/articles');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateEndPointWithConnectedUser(): void
    {
        $this->client->loginUser(
            $this->getUser('user'),
            'login'
        );

        $this->client->request('POST', '/api/admin/articles');

        // $this->assertResponsesStatusCodeSame(403);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateEndPointWithConnectedAdmin(): void
    {
        $user = $this->getUser();

        $this->client->loginUser(
            $this->getUser(),
            'login'
        );


        $this->client->request('POST', '/api/admin/articles', [
            'title' => 'Article Test',
            'content' => 'Article Test',
            'shortContent' => 'Article Test',
            'user' => $user->getId(),
        ]);

        // $this->assertResponsesStatusCodeSame(403);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testCreateEndPointValidateCreationBDD(): void
    {
        $user = $this->getUser();

        $this->client->loginUser(
            $this->getUser(),
            'login'
        );


        $this->client->request('POST', '/api/admin/articles', [
            'title' => 'Article Test',
            'content' => 'Article Test',
            'shortContent' => 'Article Test',
            'user' => $user->getId(),
        ]);

        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['title' => 'Article Test']);

        $this->assertInstanceOf(Article::class, $article);
    }





    /*************** UPDATE ***************/


    public function testUpdateEndPointWithNoConnectedUser(): void
    {
        $this->client->request('PATCH', '/api/admin/articles/1');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUpdateEndPointWithConnectedUser(): void
    {
        $this->client->loginUser(
            $this->getUser('user'),
            'login'
        );

        $this->client->request('PATCH', '/api/admin/articles/1');

        // $this->assertResponsesStatusCodeSame(403);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }


    public function testUpdateEndPointWithConnectedAdmin(): void
    {
       

        $this->client->loginUser(
            $this->getUser(),
            'login'
        );

        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixtures.yaml'
        ]);

        $this->client->request('PATCH', '/api/admin/articles/1', [
            'title' => 'Article Test',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }



    // public function testUpdateEndPointValidateUpdateBDD(): void
    // {
    //     $user = $this->getUser();

    //     $this->client->loginUser(
    //         $this->getUser(),
    //         'login'
    //     );


    //     $this->client->request('POST', '/api/admin/articles', [
    //         'title' => 'Article Test',
    //         'content' => 'Article Test',
    //         'shortContent' => 'Article Test',
    //         'user' => $user->getId(),
    //     ]);

    //     $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['title' => 'Article Test']);

    //     $this->assertInstanceOf(Article::class, $article);
    // }

}
