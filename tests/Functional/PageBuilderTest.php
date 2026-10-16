<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use App\Content\ElementTypes;
use App\Entity\{ContentElement, ContentPage};
use Doctrine\ORM\EntityManagerInterface;
final class PageBuilderTest extends DatabaseTestCase
{
    protected function tearDown(): void {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($em->createQuery("SELECT e FROM App\\Entity\\ContentElement e WHERE e.anchor LIKE 'builder-test-%'")->getResult() as $element) {
            static::getContainer()->get(\App\Service\ContentMediaStorage::class)->delete($element);
        }
        parent::tearDown();
    }
    public function testAllTypesRenderWithSharedLayoutAndEscapeImportedText(): void {
        $em = static::getContainer()->get(EntityManagerInterface::class); $page = $em->getRepository(ContentPage::class)->findOneBy(['code'=>'about']);
        foreach (ElementTypes::types() as $position => $type) {
            $element = (new ContentElement($type))->setPage($page)->setPosition(100+$position)->setAnchor('builder-test-'.$type);
            $data = $element->getData();
            if (isset($data['heading'])) { $data['heading'] = 'Builder '.$type; }
            if ($type === 'text') { $data['body'] = '<script>alert(1)</script> {{ 7 * 7 }} {organization}'; }
            if ($type === 'video') { $data['videoUrl'] = 'https://youtu.be/dQw4w9WgXcQ'; }
            $element->setData($data); $em->persist($element);
        }
        $em->flush(); $this->client->request('GET','/werbekreis/ueber-uns'); self::assertResponseIsSuccessful();
        foreach (ElementTypes::types() as $type) {
            if (in_array($type,['event','company'],true)) { self::assertSelectorNotExists('#builder-test-'.$type); }
            else { self::assertSelectorExists('#builder-test-'.$type); }
        }
        self::assertSelectorTextContains('#builder-test-text','<script>alert(1)</script> {{ 7 * 7 }} Werbekreis Haselünne e.V.');
        self::assertSelectorNotExists('#builder-test-text script, #builder-test-video iframe'); self::assertSelectorExists('#builder-test-video [data-load-video]');
    }
    public function testActionsReorderToggleDuplicateDeleteAndCsrf(): void {
        $this->login('editor'); $em = static::getContainer()->get(EntityManagerInterface::class); $page = $em->getRepository(ContentPage::class)->findOneBy(['code'=>'about']);
        $ids=[]; foreach (['a','b','c'] as $i=>$letter) { $e=(new ContentElement())->setPage($page)->setPosition(100+$i)->setAnchor('builder-test-'.$letter)->setData(['heading'=>$letter,'body'=>'Builder content','variant'=>'default']);$em->persist($e);$ids[]=$e; } $em->flush(); $ids=array_map(fn($e)=>$e->getId(),$ids);
        $firstId=(int)$em->getConnection()->fetchOne('SELECT id FROM ContentElement WHERE page_id=? ORDER BY position,id LIMIT 1',[$page->getId()]);
        $this->action($firstId,'move-up'); $this->action($ids[2],'move-down'); $this->assertOrder($ids);
        $this->action($ids[1],'move-up'); $this->assertOrder([$ids[1],$ids[0],$ids[2]]);
        $this->action($ids[1],'move-down'); $this->assertOrder($ids);
        $this->client->request('POST','/admin/pages/about/elements/'.$ids[0].'/toggle',['_token'=>'bad']); self::assertResponseStatusCodeSame(403);
        $this->action($ids[0],'toggle'); $this->client->request('GET','/werbekreis/ueber-uns'); self::assertSelectorNotExists('#builder-test-a');
        $this->client->request('GET','/admin/pages/about/edit'); self::assertSelectorTextContains('main','Inaktiv');
        $this->action($ids[1],'duplicate');
        $db=static::getContainer()->get(EntityManagerInterface::class)->getConnection(); $copy=(int)$db->fetchOne('SELECT MAX(id) FROM ContentElement');
        self::assertNull($db->fetchOne('SELECT anchor FROM ContentElement WHERE id=?',[$copy]));
        $this->action($copy,'delete'); $this->action($ids[2],'delete');
        self::assertFalse($db->fetchOne('SELECT id FROM ContentElement WHERE id=?',[$ids[2]]));
        $this->client->request('GET','/admin/pages/about/elements/'.$ids[0].'/delete'); self::assertResponseStatusCodeSame(405);
    }
    public function testLegalRoutesAndCrossPageIdsAreProtected(): void {
        $this->login('editor');
        foreach (['imprint','privacy'] as $code) {
            foreach (['new','new/text','1/edit'] as $path) { $this->client->request('GET','/admin/pages/'.$code.'/elements/'.$path); self::assertResponseStatusCodeSame(403); }
            foreach (['toggle','delete','duplicate','move-up','move-down'] as $action) { $this->client->request('POST','/admin/pages/'.$code.'/elements/1/'.$action); self::assertResponseStatusCodeSame(403); }
        }
        $em=static::getContainer()->get(EntityManagerInterface::class);$id=$em->getConnection()->fetchOne("SELECT e.id FROM ContentElement e JOIN ContentPage p ON p.id=e.page_id WHERE p.code='privacy' LIMIT 1");
        $this->client->request('GET','/admin/pages/about/elements/'.$id.'/edit'); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/admin/pages/home/elements/new'); self::assertResponseIsSuccessful();
        foreach (ElementTypes::ALL as $info) { self::assertSelectorTextContains('main',$info[0]); }
        $this->client->request('GET','/admin/pages/home/elements/new/hero'); self::assertResponseIsSuccessful();
        $this->client->request('GET','/admin/pages/home/elements/new/gallery'); self::assertResponseIsSuccessful();
    }
    public function testGalleryUploadMetadataReplacementAndMediaDelivery(): void {
        $this->login('editor'); $this->client->request('GET','/admin/pages/about/elements/new/gallery');
        $values = $this->client->getCrawler()->selectButton('Inhaltselement speichern')->form()->getPhpValues();
        $values['content_element']['anchor'] = 'builder-test-upload'; $values['content_element']['uploadAlt'] = 'Galeriebild';
        $image=imagecreatetruecolor(2,2); $file=tempnam(sys_get_temp_dir(),'gallery-'); imagepng($image,$file);
        try {
            $upload=new \Symfony\Component\HttpFoundation\File\UploadedFile($file,'../../photo.png','image/png',test:true);
            $this->client->request('POST','/admin/pages/about/elements/new/gallery',$values,['content_element'=>['uploads'=>[$upload]]]);
            self::assertResponseStatusCodeSame(303);
            $em=static::getContainer()->get(EntityManagerInterface::class);
            $element=$em->getRepository(ContentElement::class)->findOneBy(['anchor'=>'builder-test-upload']); $media=$element->getMedia()->first();
            self::assertSame('Galeriebild',$media->getAltText()); $mediaId=$media->getId(); $elementId=$element->getId();
            $this->client->request('GET','/media/content/'.$mediaId); self::assertResponseIsSuccessful(); self::assertResponseHeaderSame('X-Content-Type-Options','nosniff'); self::assertResponseHeaderSame('Content-Type','image/png'); self::assertResponseHasHeader('ETag');
            $this->client->request('GET','/admin/pages/about/elements/'.$elementId.'/edit');
            $this->client->submitForm('Inhaltselement speichern',['content_element[media][0][altText]'=>'Geänderter Alt-Text','content_element[media][0][caption]'=>'Bildunterschrift']); self::assertResponseStatusCodeSame(303);
            $this->client->request('GET','/werbekreis/ueber-uns'); self::assertSelectorExists('#builder-test-upload img[alt="Geänderter Alt-Text"]'); self::assertSelectorTextContains('#builder-test-upload figcaption','Bildunterschrift');
            $this->client->request('GET','/admin/pages/about/elements/'.$elementId.'/edit');
            $values=$this->client->getCrawler()->selectButton('Inhaltselement speichern')->form()->getPhpValues(); unset($values['content_element']['media']);
            $this->client->request('POST','/admin/pages/about/elements/'.$elementId.'/edit',$values); self::assertResponseStatusCodeSame(303);
            $this->client->request('GET','/media/content/'.$mediaId); self::assertResponseStatusCodeSame(404);
        } finally { if(is_file($file)){unlink($file);} }
    }
    public function testDuplicateAnchorsUnsafeLinksAndInvalidVideoAreRejectedByForms(): void {
        $this->login('editor');
        foreach ([['text','jobs',[]],['text','Invalid ID',[]],['cta','builder-test-invalid',['buttonUrl'=>'javascript:alert(1)']],['video','builder-test-video-invalid',['videoUrl'=>'https://evil.test/embed/123']]] as [$type,$anchor,$data]) {
            $this->client->request('GET','/admin/pages/home/elements/new/'.$type);
            $fields=['content_element[anchor]'=>$anchor]; foreach($data as $key=>$value){$fields['content_element[data]['.$key.']']=$value;}
            $this->client->submitForm('Inhaltselement speichern',$fields); self::assertResponseStatusCodeSame(422);
        }
    }
    private function action(int $id,string $action): void {
        $this->client->request('GET','/admin/pages/about/edit');
        $form=$this->client->getCrawler()->filter('form[action="/admin/pages/about/elements/'.$id.'/'.$action.'"]')->form(); $this->client->submit($form); self::assertResponseStatusCodeSame(303);
    }
    private function assertOrder(array $ids): void {
        $rows=static::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchFirstColumn("SELECT id FROM ContentElement WHERE anchor IN ('builder-test-a','builder-test-b','builder-test-c') ORDER BY position,id"); self::assertSame($ids,array_map('intval',$rows));
    }
}
