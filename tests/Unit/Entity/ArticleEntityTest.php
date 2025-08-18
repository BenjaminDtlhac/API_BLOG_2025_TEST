<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\Article;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;

class ArticleEntityTest extends KernelTestCase
{

    private EntityManagerInterface $entityManager;

    private AbstractDatabaseTool $databaseTool;

    public function setUp(): void
    {
        // initialisation le kernel de symfony
        self::bootKernel();

        // On récupère l'entity manager qu'on stock dans la propriété
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->databaseTool = self::getContainer()->get(DatabaseToolCollection::class)->get();

        $this->databaseTool->loadFixtures();
    }

    private function getUser(): User
    {
        return (new User)
            ->setUsername('TestUser')
            ->setPassword('password');
    }

    public function getArticle(): Article
    {
        return (new Article)
            ->setTitle('Titre de test')
            ->setContent('Contenu de l\'article')
            ->setShortContent('Résumé de l\'article')
            ->setUser($this->getUser())
            ->setEnabled(true);
    }

    public function persistData(Article $article, User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->persist($article);

        $this->entityManager->flush();
    }

    public function testGenerationSlugByTitle(): void
    {
        $article = $this->getArticle();
        $this->persistData($article, $article->getUser());
        $this->assertEquals('titre-de-test', $article->getSlug());
    }

    public function testGenerationCreatedAtOnPersist(): void
    {
        $article = $this->getArticle();
        $this->persistData($article, $article->getUser());
        $expected = (new \DateTimeImmutable)->format('Y-m-d H:i');
        $this->assertEquals($expected, $article->getCreatedAt()->format('Y-m-d H:i'));
    }

    public function testGenerationCreatedAtOnPersistWithExistingCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $article = $this->getArticle()
            ->setCreatedAt($createdAt);

        $this->persistData($article, $article->getUser());
        $this->assertEquals($createdAt->format('Y-m-d H:i'), $article->getCreatedAt()->format('Y-m-d H:i'));
    }

    public function testGenerationUpdateAtOnUpdate(): void
    {
        $article = $this->getArticle();

        $this->persistData($article, $article->getUser());

        $this->assertNull($article->getUpdatedAt());

        $article->setTitle('Nouveau titre');

        $this->entityManager->flush();

        $expected = (new \DateTimeImmutable)->format('Y-m-d H:i');

        $this->assertEquals($expected, $article->getUpdatedAt()->format('Y-m-d H:i'));
    }

    public function testExceptionWhenNonUniqueTitle(): void
    {
        $this->databaseTool->loadAliceFixture(
            [
                \dirname(__DIR__) . '/Fixtures/ArticleFixtures.yaml'
            ]

        );

        $article = $this->getArticle()->setTitle('Article Test');
        $this->expectException(UniqueConstraintViolationException::class);
        $this->persistData($article, $article->getUser());
    }
}
