<?php

declare(strict_types=1);
namespace App\Controller;
use App\Repository\ContentRevisionRepository;
use App\Service\MemberContentQuery;
use App\Enum\{ContentType, ModerationStatus};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class MemberController extends AbstractController
{
    #[Route('/member', name: 'member_dashboard', methods: ['GET'])]
    public function index(MemberContentQuery $content, ContentRevisionRepository $revisions): Response
    {
        $counts = $content->counts($this->getUser()); $states = ['Veröffentlicht'=>$content->publishedCount($this->getUser())];
        foreach ($revisions->counts($this->getUser()) as $row) { $label = $row['status']->label(); $states[$label] = ($states[$label] ?? 0) + (int) $row['amount']; }
        return $this->render('member/dashboard.html.twig',['counts'=>$counts,'states'=>$states,'latest'=>$revisions->latest($this->getUser()),'types'=>ContentType::cases()]);
    }
}
