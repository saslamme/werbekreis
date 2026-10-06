<?php

declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\ContentRevision;
use App\Enum\{ContentType, ModerationStatus};
use App\Form\ReviewDecisionType;
use App\Repository\ContentRevisionRepository;
use App\Security\ContentOwnershipVoter as Permission;
use App\Service\{ContentDraftMapper, ContentModerationService, CompanyImageStorage};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\{FormError, FormFactoryInterface};
use Symfony\Component\HttpFoundation\{BinaryFileResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/reviews')]
final class ReviewController extends AbstractController
{
    #[Route('',name:'admin_reviews',methods:['GET'])]
    public function index(Request $request, ContentRevisionRepository $revisions): Response
    {
        $filters=[]; foreach (['type','company','submitter','from','until'] as $field) { $value=$request->query->all()[$field]??''; $filters[$field]=is_scalar($value)?trim((string)$value):''; }
        $page=max(1,$request->query->getInt('page',1)); $rows=$revisions->queue($filters,$page); $pages=max(1,(int)ceil(count($rows)/25)); if ($page>$pages) { throw $this->createNotFoundException(); }
        return $this->render('admin/review/index.html.twig',['rows'=>$rows,'filters'=>$filters,'types'=>ContentType::cases(),'page'=>$page,'pages'=>$pages,'parameters'=>array_filter($filters)]);
    }
    #[Route('/{id}',name:'admin_review_show',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    public function show(ContentRevision $revision, Request $request, ContentDraftMapper $mapper, ContentModerationService $service, FormFactoryInterface $forms): Response
    {
        $this->denyAccessUnlessGranted(Permission::REVIEW,$revision); $target=$revision->getTarget(); $form=$this->createForm(ReviewDecisionType::class,['version'=>(string)$revision->getVersion()]); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try { $data=$form->getData(); $reviewed=$service->review($revision->getId(),$this->getUser()->getId(),(int)$data['version'],$data['decision'],$data['note']); $this->addFlash('success',$reviewed->getModerationStatus()->label().'.'); return $this->redirectToRoute('admin_reviews',status:303); }
            catch (\DomainException $exception) { $form->addError(new FormError($exception->getMessage())); }
            catch (\Doctrine\DBAL\Exception|\Doctrine\ORM\OptimisticLockException) { $form->addError(new FormError('Die Entscheidung konnte nicht gespeichert werden. Bitte neu laden.')); }
        }
        $proposal=$mapper->materialize($target,$revision->getPayload()); $current=$mapper->materialize($target,$mapper->capture($target));
        return $this->render('admin/review/show.html.twig',['revision'=>$revision,'form'=>$form,
            'proposal'=>$forms->createNamed('proposal',$revision->getType()->formClass(),$proposal,['disabled'=>true]),
            'current'=>$forms->createNamed('current',$revision->getType()->formClass(),$current,['disabled'=>true]),'pending'=>$revision->getModerationStatus()===ModerationStatus::PendingReview]);
    }
    #[Route('/{id}/images/{fileName}',name:'admin_review_image',requirements:['id'=>'\d+','fileName'=>'[a-f0-9]{32}\.(?:jpg|png|webp)'],methods:['GET'])]
    public function image(ContentRevision $revision, string $fileName, CompanyImageStorage $storage): Response
    {
        $this->denyAccessUnlessGranted(Permission::REVIEW,$revision); if (!in_array($fileName,$revision->getImageNames(),true) || !is_file($storage->path($fileName))) { throw $this->createNotFoundException(); }
        $response=new BinaryFileResponse($storage->path($fileName));
        $response->headers->set('Cache-Control','no-store');
        $response->headers->set('X-Robots-Tag','noindex, nofollow');

        return $response;
    }
}
