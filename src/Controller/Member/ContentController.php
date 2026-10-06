<?php

declare(strict_types=1);
namespace App\Controller\Member;
use App\Entity\{Company, ContentRevision, User};
use App\Enum\{ContentType, ModerationStatus};
use App\Repository\ContentRevisionRepository;
use App\Service\{ContentDraftMapper, ContentModerationService, CompanyImageStorage, MemberContentQuery};
use App\Security\ContentOwnershipVoter as Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\{FormError, FormInterface};
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/member')]
final class ContentController extends AbstractController
{
    #[Route('/{type}', name:'member_content', requirements:['type'=>'companies|offers|events|news|jobs'], methods:['GET'])]
    public function index(string $type, Request $request, MemberContentQuery $query): Response
    {
        $kind = ContentType::from($type); $page = max(1,$request->query->getInt('page',1)); $rows = $query->rows($kind,$this->getUser(),$page); $pages = max(1,(int) ceil($rows['total']/25)); if ($page>$pages) { throw $this->createNotFoundException(); }
        $published=$query->publicIds($kind,array_column($rows['rows'],'id'));
        foreach ($rows['rows'] as &$row) { $status=$row['revisionStatus'] ?? $row['moderationStatus']; $row['statusLabel']=$status === ModerationStatus::Approved && in_array($row['id'],$published,true) ? 'Veröffentlicht' : $status->label(); } unset($row);
        return $this->render('member/content/index.html.twig',['kind'=>$kind,'rows'=>$rows['rows'],'page'=>$page,'pages'=>$pages,'parameters'=>['type'=>$type],'types'=>ContentType::cases()]);
    }
    #[Route('/{type}/new', name:'member_content_new', requirements:['type'=>'offers|events|news|jobs'], methods:['GET','POST'])]
    public function create(string $type, Request $request, EntityManagerInterface $em, ContentDraftMapper $mapper, ContentModerationService $service, ContentRevisionRepository $revisions, CompanyImageStorage $storage): Response
    {
        $kind = ContentType::from($type); $companyId = $request->query->getInt('company');
        if ($companyId === 0) { return $this->render('member/content/choose.html.twig',['kind'=>$kind,'companies'=>$this->getUser()->getCompanies(),'types'=>ContentType::cases()]); }
        $company = $em->find(Company::class,$companyId) ?? throw $this->createNotFoundException(); $this->denyAccessUnlessGranted(Permission::CREATE,$company);
        $class = $kind->entityClass(); $target = new $class(); $target->beginMemberDraft(); $em->getClassMetadata($class)->setFieldValue($target,'company',$company);
        if ($kind === ContentType::Job) { $target->copyLocationFromCompany($company); }
        return $this->form($target,$kind,$request,$mapper,$service,$revisions,$storage);
    }
    #[Route('/{type}/{id}/edit', name:'member_content_edit', requirements:['type'=>'companies|offers|events|news|jobs','id'=>'\d+'], methods:['GET','POST'])]
    public function edit(string $type, int $id, Request $request, EntityManagerInterface $em, ContentDraftMapper $mapper, ContentModerationService $service, ContentRevisionRepository $revisions, CompanyImageStorage $storage): Response
    {
        $kind = ContentType::from($type); $target = $em->find($kind->entityClass(),$id) ?? throw $this->createNotFoundException(); $this->denyAccessUnlessGranted(Permission::EDIT,$target);
        return $this->form($target,$kind,$request,$mapper,$service,$revisions,$storage);
    }
    private function form(object $target, ContentType $kind, Request $request, ContentDraftMapper $mapper, ContentModerationService $service, ContentRevisionRepository $revisions, CompanyImageStorage $storage): Response
    {
        $revision = $target->getId() !== null ? $revisions->forTarget($target) : null;
        $draft = $mapper->materialize($target,$revision !== null && $revision->getModerationStatus() !== ModerationStatus::Approved ? $revision->getPayload() : $mapper->capture($target));
        $editable = $revision === null || $revision->getModerationStatus()->isEditable() || $revision->getModerationStatus() === ModerationStatus::Approved;
        $form = $this->createForm($kind->formClass(),$draft,['disabled'=>!$editable]);
        $form->add('draftVersion',HiddenType::class,['mapped'=>false,'data'=>$revision !== null ? (string) $revision->getVersion() : '', 'required'=>false,'constraints'=>[new Assert\Regex('/^\d*$/D')]]); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->stageImages($draft,$form,$storage);
                $version = $form->get('draftVersion')->getData(); $saved = $service->save($target,$draft,$this->getUser()->getId(),$version !== '' && $version !== null ? (int) $version : null);
                $this->addFlash('success','Entwurf gespeichert.'); return $this->redirectToRoute('member_content_edit',['type'=>$kind->value,'id'=>$saved->getTarget()->getId()],303);
            } catch (\DomainException|\InvalidArgumentException $exception) { $form->addError(new FormError($exception->getMessage())); }
            catch (\Doctrine\DBAL\Exception|\Doctrine\ORM\OptimisticLockException) { $form->addError(new FormError('Der Entwurf konnte nicht gespeichert werden. Bitte neu laden.')); }
        }
        return $this->privateResponse($this->render('member/content/form.html.twig',['kind'=>$kind,'target'=>$target,'draft'=>$draft,'revision'=>$revision,'form'=>$form,'editable'=>$editable,'types'=>ContentType::cases()]));
    }
    private function stageImages(object $draft, FormInterface $form, CompanyImageStorage $storage): void
    {
        if ($form->has('file')) { $file = $form->get('file')->getData(); if ($file !== null) { $draft->setFileName($storage->stage($file)); } elseif ($form->has('removeImage') && $form->get('removeImage')->getData()) { $draft->setFileName(''); } }
        if ($draft instanceof Company && $form->has('images')) { foreach ($form->get('images') as $childForm) { $file = $childForm->get('file')->getData(); if ($file !== null) { $childForm->getData()->setFileName($storage->stage($file)); } elseif ($childForm->getData()->getFileName() === '') { throw new \DomainException('Für ein neues Bild bitte eine Datei auswählen.'); } } }
    }
    #[Route('/revisions/{id}/{action}', name:'member_revision_action', requirements:['id'=>'\d+','action'=>'submit|discard'], methods:['POST'])]
    public function action(ContentRevision $revision, string $action, Request $request, ContentModerationService $service): Response
    {
        $this->denyAccessUnlessGranted($action === 'submit' ? Permission::SUBMIT : Permission::EDIT,$revision);
        if (!$this->isCsrfTokenValid('revision_'.$action.'_'.$revision->getId(),$request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        $kind = $revision->getType(); $targetId = $revision->getTarget()->getId();
        try { $version = $request->request->getInt('version'); if ($action==='submit') { $service->submit($revision->getId(),$this->getUser()->getId(),$version); $this->addFlash('success','Zur Freigabe eingereicht.'); } else { $service->discard($revision->getId(),$this->getUser()->getId(),$version); $this->addFlash('success','Entwurf entfernt. Der freigegebene Stand bleibt erhalten.'); return $this->redirectToRoute('member_content',['type'=>$kind->value],303); } }
        catch (\DomainException $exception) { $this->addFlash('error',$exception->getMessage()); }
        catch (\Doctrine\DBAL\Exception|\Doctrine\ORM\OptimisticLockException) { $this->addFlash('error','Die Aktion konnte nicht abgeschlossen werden. Bitte neu laden.'); }
        return $this->redirectToRoute('member_content_edit',['type'=>$kind->value,'id'=>$targetId],303);
    }
    #[Route('/revisions/{id}/images/{fileName}', name:'member_revision_image', requirements:['id'=>'\d+','fileName'=>'[a-f0-9]{32}\.(?:jpg|png|webp)'], methods:['GET'])]
    public function image(ContentRevision $revision, string $fileName, CompanyImageStorage $storage): Response
    {
        $this->denyAccessUnlessGranted(Permission::VIEW,$revision); if (!in_array($fileName,$revision->getImageNames(),true) || !is_file($storage->path($fileName))) { throw $this->createNotFoundException(); }
        return $this->privateResponse(new BinaryFileResponse($storage->path($fileName)));
    }
    private function privateResponse(Response $response): Response { $response->headers->set('Cache-Control','no-store'); $response->headers->set('X-Robots-Tag','noindex, nofollow'); return $response; }
}
