<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use PHPUnit\Framework\Attributes\DataProvider;
final class SecurityTest extends DatabaseTestCase
{
    public function testLoginPage(): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_csrf_token"]');
    }
    #[DataProvider('protectedRoutes')]
    public function testAnonymousRedirect(string $path): void
    {
        $this->client->request('GET', $path);
        self::assertResponseRedirects('/login');
    }
    public static function protectedRoutes(): array { return [['/admin'], ['/member']]; }
    #[DataProvider('accessRules')]
    public function testRoleAccess(string $name, string $path, int $status): void
    {
        $this->login($name);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame($status);
    }
    public static function accessRules(): array
    {
        return [['admin', '/admin', 200], ['editor', '/admin', 200], ['member', '/admin', 403], ['member', '/member', 200], ['editor', '/member', 403], ['editor', '/admin/users', 403], ['member', '/admin/users', 403]];
    }
    #[DataProvider('loginDestinations')]
    public function testRealLogin(string $name, string $destination): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Anmelden', ['_username' => $name.'@example.local', '_password' => $name.'123']);
        self::assertResponseRedirects($destination);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }
    public static function loginDestinations(): array { return [['admin', '/admin'], ['editor', '/admin'], ['member', '/member']]; }
    public function testInactiveUserCannotLogin(): void
    {
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'member@example.local']);
        $user->setActive(false);
        $em->flush();
        $this->client->request('GET', '/login');
        $this->client->submitForm('Anmelden', ['_username' => 'member@example.local', '_password' => 'member123']);
        self::assertResponseRedirects('/login');
    }
    public function testLoginRejectsInvalidCsrf(): void
    {
        $this->client->request('POST', '/login', ['_username' => 'admin@example.local', '_password' => 'admin123', '_csrf_token' => 'invalid']);
        self::assertResponseRedirects('/login');
    }
}
