<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Company;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class DirectoryAdminTest extends DirectoryDatabaseTestCase
{
    #[DataProvider('accessRules')]
    public function testRoleAccess(string $login, string $route, int $status): void
    {
        $this->login($login);
        $this->client->request('GET', $route);
        self::assertResponseStatusCodeSame($status);
    }
    public static function accessRules(): array
    {
        return [['admin', '/admin/companies', 200], ['editor', '/admin/companies', 200], ['editor', '/admin/companies/new', 200], ['member', '/admin/companies', 403], ['member', '/admin/companies/new', 403], ['admin', '/admin/categories', 200], ['editor', '/admin/categories', 200], ['member', '/admin/categories', 403], ['editor', '/admin/users', 403]];
    }
    public function testAnonymousAccessRedirects(): void
    {
        $this->client->request('GET', '/admin/companies');
        self::assertResponseRedirects('/login');
    }
    #[DataProvider('managers')]
    public function testCreateEditEmbeddedCollectionsAndShow(string $login): void
    {
        $this->login($login);
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['slug' => 'handel']);
        $this->client->request('GET', '/admin/companies/new');
        self::assertInputValueSame('company[city]', 'Haselünne');
        $form = $this->client->getCrawler()->selectButton('Speichern')->form();
        $values = $form->getPhpValues();
        $values['company'] = array_merge($values['company'], ['name' => 'Neues Beispielunternehmen', 'categories' => [$category->getId()], 'active' => '1', 'openingHours' => [
            0 => ['dayOfWeek' => '1', 'opensAt' => '09:00', 'closesAt' => '12:30', 'position' => '0'],
            1 => ['dayOfWeek' => '1', 'opensAt' => '14:00', 'closesAt' => '18:00', 'position' => '1'],
        ], 'contactPersons' => [0 => ['firstName' => 'Test', 'lastName' => 'Person', 'email' => 'test@example.local', 'sortOrder' => '0', 'active' => '1', 'primaryContact' => '1']]]);
        $this->client->request('POST', '/admin/companies/new', $values);
        self::assertResponseStatusCodeSame(303);
        $company = static::getContainer()->get(CompanyRepository::class)->findOneBy(['name' => 'Neues Beispielunternehmen']);
        self::assertInstanceOf(Company::class, $company);
        self::assertCount(2, $company->getOpeningHours());
        self::assertCount(1, $company->getContactPersons());
        $id = $company->getId();
        $slug = $company->getSlug();
        $this->client->request('GET', '/admin/companies/'.$id.'/edit');
        $form = $this->client->getCrawler()->selectButton('Speichern')->form();
        $values = $form->getPhpValues();
        $values['company']['name'] = 'Geänderter Unternehmensname';
        unset($values['company']['openingHours'][0]);
        $values['company']['contactPersons'] = [];
        $this->client->request('POST', '/admin/companies/'.$id.'/edit', $values);
        self::assertResponseStatusCodeSame(303);
        $saved = static::getContainer()->get(CompanyRepository::class)->find($id);
        self::assertSame($slug, $saved->getSlug());
        self::assertCount(1, $saved->getOpeningHours());
        self::assertCount(0, $saved->getContactPersons());
        $this->client->request('GET', '/admin/companies/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Geänderter Unternehmensname');
    }
    public static function managers(): array
    {
        return [['admin'], ['editor']];
    }
    public function testCategoryCreateEditAndSlugCollision(): void
    {
        $this->login('editor');
        $this->client->request('GET', '/admin/categories/new');
        $this->client->submitForm('Speichern', ['category[name]' => 'Handel']);
        self::assertResponseRedirects('/admin/categories', 303);
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['slug' => 'handel-2']);
        self::assertInstanceOf(Category::class, $category);
        $this->client->request('GET', '/admin/categories/'.$category->getId().'/edit');
        $this->client->submitForm('Speichern', ['category[name]' => 'Neuer Name', 'category[active]' => false]);
        self::assertResponseRedirects('/admin/categories', 303);
    }
    public function testExplicitDuplicateSlugReturnsValidationError(): void
    {
        $this->login('editor');
        $this->client->request('GET', '/admin/categories/new');
        $this->client->submitForm('Speichern', ['category[name]' => 'Neue Kategorie', 'category[slug]' => 'handel']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="category"]', 'Dieser Slug ist bereits vergeben.');
    }
    public function testCompanyFiltersAndDashboardStatistics(): void
    {
        $this->login('editor');
        $this->client->request('GET', '/admin/companies?name=Hasebogen&active=1&featured=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '[data-company-list] tbody tr');
        self::assertSelectorTextContains('[data-company-list]', 'Musterladen Hasebogen');
        self::assertSame(['total' => 5, 'active' => 4, 'featured' => 2], static::getContainer()->get(CompanyRepository::class)->statistics());
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Aktive Unternehmen');
    }
    public function testPaginationHasNoDuplicateCompaniesWithMultipleCategories(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $categories = static::getContainer()->get(CategoryRepository::class)->findAll();
        for ($i = 0; $i < 30; ++$i) {
            $company = (new Company())->setName(sprintf('Test %02d', $i))->setSlug('test-'.$i)->addCategory($categories[0])->addCategory($categories[1]);
            $em->persist($company);
        }
        $em->flush();
        $em->clear();
        $repository = static::getContainer()->get(CompanyRepository::class);
        $first = iterator_to_array($repository->adminPage('Test ', null, null, null, 1, 'name'));
        $second = iterator_to_array($repository->adminPage('Test ', null, null, null, 2, 'name'));
        self::assertCount(25, $first);
        self::assertCount(5, $second);
        $ids = array_map(static fn (Company $company): int => $company->getId(), array_merge($first, $second));
        self::assertCount(30, array_unique($ids));
    }
    public function testCompanyDeletionRequiresCsrfAndCascadesChildren(): void
    {
        $this->login('editor');
        $company = $this->firstCompany();
        $id = $company->getId();
        $this->client->request('POST', '/admin/companies/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/companies/'.$id.'/edit');
        $this->client->submitForm('Unternehmen endgültig löschen');
        self::assertResponseRedirects('/admin/companies', 303);
        self::assertNull(static::getContainer()->get(CompanyRepository::class)->find($id));
    }
    public function testAssignedCategoryCannotBeDeleted(): void
    {
        $this->login('editor');
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['slug' => 'handel']);
        $id = $category->getId();
        $this->client->request('GET', '/admin/categories/'.$id.'/edit');
        $this->client->submitForm('Kategorie löschen');
        self::assertResponseRedirects('/admin/categories', 303);
        self::assertNotNull(static::getContainer()->get(CategoryRepository::class)->find($id));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Zugeordnete Kategorien');
    }
    public function testUnusedCategoryDeletionAndCsrf(): void
    {
        $this->login('editor');
        $this->client->request('GET', '/admin/categories/new');
        $this->client->submitForm('Speichern', ['category[name]' => 'Unbenutzte Kategorie']);
        self::assertResponseStatusCodeSame(303);
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['slug' => 'unbenutzte-kategorie']);
        $id = $category->getId();
        $this->client->request('POST', '/admin/categories/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/categories/'.$id.'/edit');
        $this->client->submitForm('Kategorie löschen');
        self::assertResponseStatusCodeSame(303);
        self::assertNull(static::getContainer()->get(CategoryRepository::class)->find($id));
    }

    public function testEditorCanDeactivateCompany(): void
    {
        $this->login('editor');
        $id = $this->firstCompany()->getId();
        $this->client->request('GET', '/admin/companies/'.$id.'/edit');
        $this->client->submitForm('Speichern', ['company[active]' => false, 'company[featured]' => false]);
        self::assertResponseStatusCodeSame(303);
        $saved = static::getContainer()->get(CompanyRepository::class)->find($id);
        self::assertFalse($saved->isActive());
        self::assertFalse($saved->isFeatured());
    }

    public function testCompanyMutationRejectsCsrf(): void
    {
        $this->login('editor');
        $this->client->request('POST', '/admin/companies/new', ['company' => ['name' => 'Tampered', 'active' => '0', '_token' => 'invalid']]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull(static::getContainer()->get(CompanyRepository::class)->findOneBy(['name' => 'Tampered']));
    }
}
