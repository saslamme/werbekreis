<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BoardMember;
use App\Form\BoardMemberType;
use App\Repository\BoardMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/pages/board/members')]
final class BoardMemberController extends AbstractController
{
    #[Route('', name: 'admin_pages_board_members', methods: ['GET'])]
    public function index(BoardMemberRepository $members): Response
    {
        return $this->render('admin/board_member/index.html.twig', ['members' => $members->findOrdered()]);
    }

    #[Route('/new', name: 'admin_pages_board_members_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        return $this->save(new BoardMember(), $request, $em);
    }

    #[Route('/{id}/edit', name: 'admin_pages_board_members_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(BoardMember $member, Request $request, EntityManagerInterface $em): Response
    {
        return $this->save($member, $request, $em);
    }

    private function save(BoardMember $member, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(BoardMemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member->touch();
            $em->persist($member);
            $em->flush();
            $this->addFlash('success', 'Vorstandsmitglied gespeichert.');

            return $this->redirectToRoute('admin_pages_board_members', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/board_member/form.html.twig', ['member' => $member, 'form' => $form]);
    }
}
