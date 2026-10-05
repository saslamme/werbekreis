<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use App\Repository\UserRepository;
final class UserRepositoryTest extends DatabaseTestCase
{
    public function testDashboardQueries(): void
    {
        $users = static::getContainer()->get(UserRepository::class);
        self::assertSame(3, $users->countAll());
        foreach (['ROLE_ADMIN','ROLE_EDITOR','ROLE_MEMBER'] as $role) { self::assertSame(1, $users->countByRole($role)); }
        self::assertCount(2, $users->findLatest(2));
    }
}
