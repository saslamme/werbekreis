<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Content\ElementTypes;
use App\Entity\{ContentPage, ContentElement};
use App\Form\ContentElementType;
use App\Repository\ContentPageRepository;
use App\Service\ContentMediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/pages/{code}/elements', requirements: ['code' => 'home|about|board|membership|contact|imprint|privacy'])]
final class ContentElementController extends AbstractController
{
    public function __construct(private ContentPageRepository $pages, private EntityManagerInterface $em, private ContentMediaStorage $storage) {}
    private function page(string $code): ContentPage {
        if (in_array($code, ['imprint', 'privacy'], true)) { $this->denyAccessUnlessGranted('ROLE_ADMIN'); }
        return $this->pages->findOneByCode($code) ?? throw $this->createNotFoundException();
    }
    private function element(ContentPage $page, int $id): ContentElement {
        $element = $this->em->find(ContentElement::class, $id);
        if (!$element || $element->getPage() !== $page) { throw $this->createNotFoundException(); }
        return $element;
    }
    #[Route('/new', name: 'admin_elements_choose', methods: ['GET'])]
    public function choose(string $code): Response { return $this->render('admin/page/choose.html.twig', ['page' => $this->page($code), 'types' => ElementTypes::ALL]); }
    #[Route('/new/{type}', name: 'admin_elements_new', methods: ['GET', 'POST'])]
    public function create(string $code, string $type, Request $request): Response {
        $page = $this->page($code);
        if (!isset(ElementTypes::ALL[$type])) { throw $this->createNotFoundException(); }
        $element = (new ContentElement($type))->setPage($page)->setPosition(1 + max(-1, ...array_map(static fn(ContentElement $e) => $e->getPosition(), $page->getElements()->toArray())));
        return $this->form($page, $element, $request);
    }
    #[Route('/{id}/edit', name: 'admin_elements_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(string $code, int $id, Request $request): Response {
        $page = $this->page($code); return $this->form($page, $this->element($page, $id), $request);
    }
    private function form(ContentPage $page, ContentElement $element, Request $request): Response {
        $before = $element->getMedia()->toArray();
        $form = $this->createForm(ContentElementType::class, $element); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->storage->save($element, $before, $form->has('uploads') ? $form->get('uploads')->getData() : [], $form->has('uploadAlt') ? (string) $form->get('uploadAlt')->getData() : '', $form->has('uploadDecorative') && $form->get('uploadDecorative')->getData());
                $this->addFlash('success', 'Inhaltselement gespeichert.');
                return $this->redirectToRoute('admin_pages_edit', ['code' => $page->getCode()], 303);
            } catch (\InvalidArgumentException $e) { $form->addError(new FormError($e->getMessage())); }
        }
        return $this->render('admin/page/element.html.twig', ['page' => $page, 'element' => $element, 'form' => $form]);
    }
    #[Route('/{id}/{action}', name: 'admin_elements_action', requirements: ['id' => '\d+', 'action' => 'move-up|move-down|toggle|duplicate|delete'], methods: ['POST'])]
    public function action(string $code, int $id, string $action, Request $request): Response {
        $page = $this->page($code); $element = $this->element($page, $id);
        if (!$this->isCsrfTokenValid('element_'.$action.'_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $items = array_values($page->getElements()->toArray());
        if ($action === 'delete') { $this->storage->delete($element); }
        elseif ($action === 'duplicate') {
            $this->storage->duplicate($element);
        } else {
            if ($action === 'toggle') { $element->setActive(!$element->isActive()); }
            else {
                $index = array_search($element, $items, true); $target = $index + ($action === 'move-up' ? -1 : 1);
                if (isset($items[$target])) { [$items[$index], $items[$target]] = [$items[$target], $items[$index]]; }
            }
            foreach ($items as $position => $item) { $item->setPosition($position); }
            $page->touch(); $this->em->flush();
        }
        return $this->redirectToRoute('admin_pages_edit', ['code' => $code], 303);
    }
}
