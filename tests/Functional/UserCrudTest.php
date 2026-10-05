<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
final class UserCrudTest extends DatabaseTestCase
{
    public function testCreateAndEditKeepsPasswordWhenBlank(): void
    {
        $this->login('admin');
        $this->client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Speichern', ['user[firstName]' => 'Test', 'user[lastName]' => 'Person', 'user[email]' => 'test@example.local', 'user[plainPassword]' => 'new-password-123', 'user[roles][2]' => 'ROLE_MEMBER', 'user[active]' => true]);
        self::assertResponseRedirects('/admin/users', 303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.local']);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'new-password-123'));
        $hash = $user->getPassword();
        $id = $user->getId();
        $this->client->request('GET', '/admin/users/'.$id.'/edit');
        $this->client->submitForm('Speichern', ['user[firstName]' => 'Updated', 'user[plainPassword]' => '']);
        self::assertResponseRedirects('/admin/users', 303);
        $saved = static::getContainer()->get(EntityManagerInterface::class)->find(User::class, $id);
        self::assertSame($hash, $saved->getPassword());
        self::assertSame('Updated', $saved->getFirstName());
    }
    public function testAdminCannotDeactivateSelf(): void
    {
        $admin = $this->login('admin');
        $this->client->request('GET', '/admin/users/'.$admin->getId().'/edit');
        $this->client->submitForm('Speichern', ['user[active]' => false]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="user"]', 'Sie können Ihr eigenes Konto nicht deaktivieren.');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $saved = $em->find(User::class, $admin->getId());
        self::assertTrue($saved->isActive());
    }
    public function testNewUserRequiresPasswordAndRole(): void
    {
        $this->login('admin');
        $this->client->request('GET', '/admin/users/new');
        $this->client->submitForm('Speichern', ['user[firstName]' => 'Test', 'user[lastName]' => 'Person', 'user[email]' => 'invalid@example.local', 'user[plainPassword]' => '', 'user[roles]' => []]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull(static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'invalid@example.local']));
    }
}
