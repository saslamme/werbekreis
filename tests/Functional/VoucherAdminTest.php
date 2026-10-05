<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\{Voucher, VoucherProduct, VoucherRedemption};
use App\Enum\VoucherStatus;

final class VoucherAdminTest extends VoucherDatabaseTestCase
{
    public function testEditorCanEditContentButNoFinancialFieldsOrRoutes(): void
    {
        $this->login('editor'); $id = $this->voucher(1)->getProduct()->getId(); $crawler = $this->client->request('GET', '/admin/voucher-products/'.$id.'/edit'); self::assertResponseIsSuccessful();
        foreach (['active','validityMonths','minimumAmount','maximumAmount','fixedAmount','acceptingCompanies','sellingCompanies'] as $field) { self::assertSelectorNotExists('[name^="voucher_product['.$field.']"]'); }
        $form = $crawler->filter('form[name="voucher_product"]')->form(['voucher_product[name]' => 'TEST neuer Text']); $this->client->submit($form); self::assertResponseRedirects('/admin/voucher-products', 303);
        $crawler = $this->client->request('GET', '/admin/voucher-products/'.$id.'/edit'); $values = $crawler->filter('form[name="voucher_product"]')->form()->getPhpValues(); $values['voucher_product']['fixedAmount'] = '1';
        $this->client->request('POST', '/admin/voucher-products/'.$id.'/edit', $values); self::assertResponseStatusCodeSame(422);
        foreach (['/admin/vouchers', '/admin/vouchers/new', '/admin/vouchers/redeem', '/admin/voucher-products/new'] as $path) { $this->client->request('GET', $path); self::assertResponseStatusCodeSame(403); }
        $this->client->request('GET', '/admin'); self::assertResponseIsSuccessful(); self::assertStringNotContainsString('Offenes Gutschein-Guthaben', $this->client->getResponse()->getContent());
    }
    public function testAdminProductCreationAndSlugValidation(): void
    {
        $this->login('admin'); $crawler = $this->client->request('GET', '/admin/voucher-products/new');
        $form = $crawler->filter('form[name="voucher_product"]')->form(['voucher_product[name]' => 'TEST neues Produkt', 'voucher_product[description]' => 'TEST Beschreibung', 'voucher_product[position]' => '3', 'voucher_product[fixedAmount]' => '25,00', 'voucher_product[active]' => '1']);
        $this->client->submit($form); self::assertResponseRedirects('/admin/voucher-products', 303);
        $product = static::getContainer()->get('doctrine')->getRepository(VoucherProduct::class)->findOneBy(['name' => 'TEST neues Produkt']); self::assertNotNull($product); self::assertSame('25.00', $product->getFixedAmount()); self::assertSame('test-neues-produkt', $product->getSlug());
        $crawler = $this->client->request('GET', '/admin/voucher-products/new'); $form = $crawler->filter('form[name="voucher_product"]')->form(['voucher_product[name]' => 'TEST invalid', 'voucher_product[description]' => 'TEST', 'voucher_product[position]' => '0', 'voucher_product[fixedAmount]' => '25', 'voucher_product[minimumAmount]' => '10', 'voucher_product[maximumAmount]' => '50']);
        $this->client->submit($form); self::assertResponseStatusCodeSame(422);
    }
    public function testAdminIssuanceLifecycleCsrfAndBalanceMassAssignmentProtection(): void
    {
        $this->login('admin'); $fixedId = $this->voucher(8)->getProduct()->getId(); $crawler = $this->client->request('GET', '/admin/vouchers/new');
        $this->client->submit($crawler->selectButton('Gutschein erstellen')->form(['voucher_issue[product]' => (string) $fixedId, 'voucher_issue[note]' => 'TEST manual issuance'])); self::assertResponseStatusCodeSame(303); $location = $this->client->getResponse()->headers->get('Location');
        $crawler = $this->client->followRedirect(); self::assertResponseIsSuccessful(); $id = (int) basename($location); $voucher = static::getContainer()->get('doctrine')->getRepository(Voucher::class)->find($id); self::assertSame('25.00', $voucher->getInitialAmount()); self::assertSame(VoucherStatus::Created, $voucher->getStatus());
        $this->client->request('GET', $location.'/activate'); self::assertResponseStatusCodeSame(405);
        $this->client->request('POST', $location.'/activate', ['_token' => 'invalid']); self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', $location); $this->client->submit($crawler->filter('form[action$="/activate"]')->form()); self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->request('GET', $location); $this->client->submit($crawler->filter('form[action$="/block"]')->form()); self::assertResponseStatusCodeSame(303);
        self::assertSame(VoucherStatus::Blocked, static::getContainer()->get('doctrine')->getRepository(Voucher::class)->find($id)->getStatus());
        $crawler = $this->client->request('GET', $location.'/edit'); $values = $crawler->filter('form[name="form"]')->form()->getPhpValues(); $values['form']['remainingAmount'] = '9999'; $this->client->request('POST', $location.'/edit', $values); self::assertResponseStatusCodeSame(422);
        self::assertSame('25.00', static::getContainer()->get('doctrine')->getRepository(Voucher::class)->find($id)->getRemainingAmount());
    }
    public function testDedicatedRedeemerCannotAccessOtherAdministrationAndLoginRedirects(): void
    {
        $this->login('redeemer'); $this->client->request('GET', '/admin/vouchers/redeem'); self::assertResponseIsSuccessful();
        foreach (['/admin', '/admin/users', '/admin/vouchers', '/admin/voucher-products', '/member'] as $path) { $this->client->request('GET', $path); self::assertResponseStatusCodeSame(403); }
        $this->login('member'); $this->client->request('GET', '/admin/vouchers/redeem'); self::assertResponseStatusCodeSame(403);
    }
    public function testConfirmationIsExplicitCsrfProtectedAndDuplicatePostReturnsSameReceipt(): void
    {
        $actor = $this->login('redeemer'); $voucher = $this->voucher(1); $id = $voucher->getId();
        $crawler = $this->client->request('GET', '/admin/vouchers/redeem'); $this->client->submit($crawler->selectButton('Gutschein suchen')->form(['voucher_lookup[code]' => $voucher->getCode()])); self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '#voucher_redemption_company option[value!=""]');
        $crawler = $this->client->getCrawler(); $this->client->submit($crawler->selectButton('Einlösung prüfen')->form(['voucher_redemption[amount]' => '12,50'])); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', 'Einlösung bestätigen');
        self::assertSame('50.00', static::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT remainingAmount FROM Voucher WHERE id = ?', [$id]));
        $crawler = $this->client->getCrawler(); $unconfirmed = $crawler->selectButton('Verbindlich einlösen')->form(); $this->client->submit($unconfirmed); self::assertResponseStatusCodeSame(422); self::assertSelectorExists('#voucher_confirmation_confirm');
        $confirmed = $this->client->getCrawler()->selectButton('Verbindlich einlösen')->form(['voucher_confirmation[confirm]' => '1']); $values = $confirmed->getPhpValues(); $this->client->submit($confirmed); self::assertResponseStatusCodeSame(303); $receipt = $this->client->getResponse()->headers->get('Location');
        $this->client->followRedirect(); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', '37,50 €');
        $this->client->request('GET', $receipt); self::assertResponseIsSuccessful();
        $this->client->request('POST', '/admin/vouchers/redeem', $values); self::assertResponseRedirects($receipt, 303); self::assertSame(5, static::getContainer()->get('doctrine')->getRepository(VoucherRedemption::class)->count([]));
        $other = static::getContainer()->get('doctrine')->getRepository(VoucherRedemption::class)->findOneBy(['reference' => 'TEST-REF-2']); $this->client->request('GET', '/admin/vouchers/redemptions/'.$other->getId()); self::assertResponseStatusCodeSame(403);
    }
    public function testProductAndCompanyWithHistoryCannotBeDeleted(): void
    {
        $this->login('admin'); $productId = $this->voucher(1)->getProduct()->getId(); $crawler = $this->client->request('GET', '/admin/voucher-products/'.$productId.'/edit');
        $this->client->submit($crawler->filter('form[action="/admin/voucher-products/'.$productId.'/delete"]')->form()); self::assertResponseStatusCodeSame(303); self::assertNotNull(static::getContainer()->get('doctrine')->getRepository(VoucherProduct::class)->find($productId));
        $company = $this->firstCompany(); $crawler = $this->client->request('GET', '/admin/companies/'.$company->getId().'/edit');
        $this->client->submit($crawler->filter('form[action$="/delete"]')->form()); self::assertResponseStatusCodeSame(303); self::assertNotNull(static::getContainer()->get('doctrine')->getRepository(\App\Entity\Company::class)->find($company->getId()));
    }
    public function testAdminSeesHistoryAndThereAreNoLedgerMutationRoutes(): void
    {
        $this->login('admin'); $id = $this->voucher(7)->getId(); $this->client->request('GET', '/admin/vouchers/'.$id); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'TEST-REF-7'); self::assertSelectorTextContains('body', 'admin@example.local'); self::assertSelectorTextContains('body', '25,00 €');
        $entry = static::getContainer()->get('doctrine')->getRepository(VoucherRedemption::class)->findOneBy([]);
        foreach (['edit', 'delete'] as $action) { $this->client->request('POST', '/admin/vouchers/redemptions/'.$entry->getId().'/'.$action); self::assertResponseStatusCodeSame(404); }
        $this->client->request('GET', '/admin'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', '747,50 €');
    }

    public function testUserAdministrationAssignsDedicatedRoleAndCompany(): void
    {
        $actor = $this->login('redeemer'); $actorId = $actor->getId(); $companyId = $this->company('Beispielcafé Uferpause')->getId(); $this->login('admin');
        $crawler = $this->client->request('GET', '/admin/users/'.$actorId.'/edit'); self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Speichern')->form(); $values = $form->getPhpValues(); $values['user']['roles'] = ['ROLE_VOUCHER_REDEEMER']; $values['user']['company'] = (string) $companyId;
        $this->client->request('POST', '/admin/users/'.$actorId.'/edit', $values); self::assertResponseStatusCodeSame(303);
        $user = static::getContainer()->get('doctrine')->getRepository(\App\Entity\User::class)->find($actorId); self::assertContains('ROLE_VOUCHER_REDEEMER', $user->getRoles()); self::assertSame($companyId, $user->getCompany()->getId());
    }

}
