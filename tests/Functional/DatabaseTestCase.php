<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\User;
use App\DataFixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
abstract class DatabaseTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\User u')->execute();
        static::getContainer()->get(UserFixtures::class)->load($em);
    }
    protected function login(string $name): User
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => $name.'@example.local']);
        self::assertInstanceOf(User::class, $user);
        $this->client->loginUser($user);
        return $user;
    }
}
