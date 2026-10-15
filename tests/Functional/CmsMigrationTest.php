<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\CmsPreservingPurgerFactory;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CmsMigrationTest extends KernelTestCase
{
    public function testUpgradePreservesExistingDataSeedsCorePagesAndFixturesDoNotPurgeThem(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        $source = $em->getConnection()->getDatabase();
        self::assertStringContainsString('_test', $source);
        $database = $source.'_cms_migration';
        $admin = $em->getConnection();
        $quoted = $admin->getDatabasePlatform()->quoteIdentifier($database);
        $admin->executeStatement('CREATE DATABASE '.$quoted.' CHARACTER SET utf8mb4');
        $params['dbname'] = $database;
        unset($params['url']);
        $connection = DriverManager::getConnection($params);
        try {
            $files = glob(dirname(__DIR__, 2).'/migrations/Version*.php');
            $files = array_filter($files, static fn (string $file): bool => basename($file) <= 'Version20261015090000.php');
            sort($files);
            $cms = array_pop($files);
            foreach ($files as $file) { $this->executeMigration($connection, $file, 'up'); }
            // A pre-existing production-shaped user must survive the upgrade.
            $connection->insert('portal_user', ['email' => 'preserve@example.test', 'password' => 'test', 'roles' => '[]', 'firstName' => 'Existing', 'lastName' => 'Record', 'active' => 1, 'createdAt' => '2026-01-01 00:00:00', 'updatedAt' => '2026-01-01 00:00:00']);
            $this->executeMigration($connection, $cms, 'up');
            self::assertSame('preserve@example.test', $connection->fetchOne('SELECT email FROM portal_user'));
            $this->assertSeed($connection);
            $connection->executeStatement("UPDATE ContentPage SET title = 'Edited core content' WHERE code = 'about'");
            $isolatedEm = new EntityManager($connection, $em->getConfiguration());
            $factory = static::getContainer()->get('doctrine.fixtures.purger.orm_purger_factory');
            self::assertInstanceOf(CmsPreservingPurgerFactory::class, $factory);
            $factory->createForEntityManager(null, $isolatedEm)->purge();
            self::assertSame('Edited core content', $connection->fetchOne("SELECT title FROM ContentPage WHERE code = 'about'"));
            $this->assertSeed($connection);
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM portal_user'));
            // Reversible schema/data migration; no dependence on runtime entity constructors.
            $this->executeMigration($connection, $cms, 'down');
            $this->executeMigration($connection, $cms, 'up');
            $this->assertSeed($connection);
        } finally {
            $connection->close();
            $admin->executeStatement('DROP DATABASE '.$quoted);
        }
    }

    private function executeMigration(Connection $connection, string $file, string $direction): void
    {
        require_once $file;
        $class = 'DoctrineMigrations\\'.basename($file, '.php');
        $migration = new $class($connection, new NullLogger());
        $migration->$direction(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }

    private function assertSeed(Connection $connection): void
    {
        self::assertSame(['about', 'board', 'contact', 'imprint', 'membership', 'privacy'], $connection->fetchFirstColumn('SELECT code FROM ContentPage ORDER BY code'));
        self::assertSame(6, (int) $connection->fetchOne("SELECT COUNT(*) FROM ContentPage WHERE title <> '' AND metaTitle <> '' AND metaDescription <> ''"));
        self::assertSame(9, (int) $connection->fetchOne('SELECT COUNT(*) FROM BoardMember WHERE active = 1'));
        self::assertSame(['Andreas Walburg', 'Norbert Schnakenberg', 'Daniel Pesch', 'Cornelia Sturm', 'Christopher Jansen', 'Philipp Langen', 'Robert Münster', 'Ingo Hoffmeister', 'Tanja Kramer'], $connection->fetchFirstColumn('SELECT name FROM BoardMember ORDER BY position, name'));
        self::assertSame(28, (int) $connection->fetchOne('SELECT COUNT(*) FROM ContentPageSection'));
        $cookie = $connection->fetchOne("SELECT body FROM ContentPageSection WHERE heading = '3. Technisch notwendige Cookies und Sessions'");
        self::assertStringContainsString('wk_cookie_notice', $cookie);
        self::assertStringContainsString('180 Tage', $cookie);
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM ContentPageSection WHERE style = 'notice' AND body LIKE '%Registerangaben werden vor dem produktiven Start ergänzt.%'"));
        // Contact tokens reference configuration rather than copying addresses into every row.
        self::assertSame(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM ContentPageSection WHERE body LIKE '%info@werbekreis-haseluenne.de%'"));
    }
}
