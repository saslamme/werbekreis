<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users')]
final class UserController extends AbstractController
{
    #[Route('', name: 'admin_users', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/user/index.html.twig', ['users' => $users->findBy([], ['id' => 'DESC'])]);
    }
    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        return $this->save(new User(), $request, $em, $hasher, true);
    }
    #[Route('/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        return $this->save($user, $request, $em, $hasher, false);
    }
    private function save(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, bool $new): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_new' => $new]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($user->getId() === $this->getUser()?->getId()) {
                if (!$user->isActive()) { $form->get('active')->addError(new FormError('Sie können Ihr eigenes Konto nicht deaktivieren.')); }
                if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) { $form->get('roles')->addError(new FormError('Sie können Ihre eigene Admin-Rolle nicht entfernen.')); }
            }
            if ($form->isValid()) {
                $password = $form->get('plainPassword')->getData();
                if (is_string($password) && $password !== '') { $user->setPassword($hasher->hashPassword($user, $password)); }
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Benutzer gespeichert.');
                return $this->redirectToRoute('admin_users', status: Response::HTTP_SEE_OTHER);
            }
        }
        return $this->render('admin/user/form.html.twig', ['form' => $form, 'is_new' => $new]);
    }
}
