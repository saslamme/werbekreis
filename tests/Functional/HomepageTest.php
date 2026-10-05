<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
final class HomepageTest extends WebTestCase
{
    public function testHomepage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Haselünne hat mehr zu bieten.');
        self::assertSelectorExists('#portal-search');
    }
}
