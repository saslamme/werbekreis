<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
final class PageBuilderMigrationTest extends KernelTestCase
{
    public function testEditedLiveContentIsPreservedAndHomeIsSeededOnce(): void {
        self::bootKernel(); $admin = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $source = $admin->getDatabase(); self::assertStringContainsString('_test', $source);
        $name = $source.'_page_builder'; $quoted = $admin->getDatabasePlatform()->quoteIdentifier($name);
        $admin->executeStatement('CREATE DATABASE '.$quoted.' CHARACTER SET utf8mb4');
        $params = $admin->getParams(); unset($params['url']); $params['dbname'] = $name; $db = DriverManager::getConnection($params);
        try {
            $files = glob(dirname(__DIR__,2).'/migrations/Version*.php'); sort($files); $latest = array_pop($files);
            foreach ($files as $file) { $this->migrate($db, $file); }
            $db->executeStatement("UPDATE ContentPage SET title='Individueller Titel', lead='Lead geändert',metaTitle='SEO geändert',metaDescription='Beschreibung geändert' WHERE code='about'");
            $db->executeStatement("UPDATE ContentPageSection SET heading='Redaktionell geändert',body='Individueller Inhalt {{ 7 * 7 }}',position=77,style='feature' WHERE page_id=(SELECT id FROM ContentPage WHERE code='about') LIMIT 1");
            $before = $db->fetchAllAssociative('SELECT * FROM ContentPageSection ORDER BY id');
            $this->migrate($db, $latest);
            $page = $db->fetchAssociative("SELECT * FROM ContentPage WHERE code='about'");
            self::assertSame('Individueller Titel', $page['title']); self::assertSame('Lead geändert', $page['lead']); self::assertSame('SEO geändert', $page['metaTitle']); self::assertSame('Beschreibung geändert', $page['metaDescription']);
            self::assertSame($before, $db->fetchAllAssociative('SELECT * FROM ContentPageSection ORDER BY id'));
            self::assertSame(count($before), (int)$db->fetchOne("SELECT COUNT(*) FROM ContentElement WHERE type='text'"));
            foreach ($before as $section) {
                $matches = $db->fetchAllAssociative("SELECT * FROM ContentElement WHERE page_id=? AND type='text' AND position=?", [$section['page_id'], $section['position']]);
                $match = array_filter($matches, fn($row) => json_decode($row['data'],true)['body'] === $section['body']);
                self::assertCount(1, $match); $data = json_decode(array_values($match)[0]['data'],true);
                self::assertSame($section['heading'] ?? '', $data['heading']); self::assertSame(['default'=>'default','feature'=>'card','notice'=>'notice'][$section['style']], $data['variant']);
            }
            self::assertSame(1, (int)$db->fetchOne("SELECT COUNT(*) FROM ContentPage WHERE code='home' AND showDefaultHeader=0"));
            self::assertSame(['hero','categories','offers','news','event_list','company_list','voucher','jobs','cta'], $db->fetchFirstColumn("SELECT e.type FROM ContentElement e JOIN ContentPage p ON p.id=e.page_id WHERE p.code='home' ORDER BY e.position,e.id"));
            self::assertSame(9, (int)$db->fetchOne("SELECT COUNT(*) FROM ContentElement e JOIN ContentPage p ON p.id=e.page_id WHERE p.code='home'"));
            self::assertSame([], $db->fetchAllAssociative('SELECT page_id,anchor,COUNT(*) n FROM ContentElement WHERE anchor IS NOT NULL GROUP BY page_id,anchor HAVING n>1'));
        } finally { $db->close(); $admin->executeStatement('DROP DATABASE '.$quoted); }
    }
    private function migrate(Connection $db, string $file): void {
        require_once $file; $class = 'DoctrineMigrations\\'.basename($file,'.php'); $migration = new $class($db,new NullLogger()); $migration->up(new Schema());
        foreach ($migration->getSql() as $query) { $db->executeStatement($query->getStatement(),$query->getParameters(),$query->getTypes()); }
    }
}
