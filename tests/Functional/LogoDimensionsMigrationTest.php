<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LogoDimensionsMigrationTest extends KernelTestCase
{
    public function testMigrationPreservesSingletonAndExistingValues(): void
    {
        self::bootKernel();
        $admin = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $source = $admin->getDatabase();
        self::assertStringContainsString('_test', $source);
        $params = $admin->getParams();
        $params['dbname'] = $source.'_logo_dimensions';
        unset($params['url']);
        $quoted = $admin->getDatabasePlatform()->quoteIdentifier($params['dbname']);
        $admin->executeStatement('CREATE DATABASE '.$quoted.' CHARACTER SET utf8mb4');
        $connection = DriverManager::getConnection($params);
        try {
            $root = dirname(__DIR__, 2).'/migrations/';
            $files = glob($root.'Version*.php');
            sort($files);
            foreach ($files as $file) {
                if (basename($file) <= 'Version20261015120000.php') { $this->migrate($connection, $file, 'up'); }
            }
            $connection->executeStatement("UPDATE PortalSettings SET facebookUrl = 'https://facebook.com/preserve', instagramUrl = 'https://instagram.com/preserve', logoFileName = ?, logoOriginalName = 'Original.svg' WHERE id = 1", [str_repeat('a', 32).'.svg']);
            $before = $connection->fetchAssociative('SELECT * FROM PortalSettings WHERE id = 1');
            $migration = $root.'Version20261015130000.php';
            $this->migrate($connection, $migration, 'up');
            $after = $connection->fetchAssociative('SELECT * FROM PortalSettings WHERE id = 1');
            self::assertNull($after['logoMaxWidth']);
            self::assertNull($after['logoMaxHeight']);
            unset($after['logoMaxWidth'], $after['logoMaxHeight']);
            self::assertSame($before, $after);
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM PortalSettings'));
            $this->migrate($connection, $migration, 'down');
            self::assertSame($before, $connection->fetchAssociative('SELECT * FROM PortalSettings WHERE id = 1'));
        } finally {
            $connection->close();
            $admin->executeStatement('DROP DATABASE '.$quoted);
        }
    }

    private function migrate(Connection $connection, string $file, string $direction): void
    {
        require_once $file;
        $class = 'DoctrineMigrations\\'.basename($file, '.php');
        $migration = new $class($connection, new NullLogger());
        $migration->$direction(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }
}
