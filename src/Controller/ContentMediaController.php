<?php
declare(strict_types=1);
namespace App\Controller;
use App\Entity\ContentMedia;
use App\Service\ContentMediaStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, BinaryFileResponse};
use Symfony\Component\Routing\Attribute\Route;
final class ContentMediaController extends AbstractController
{
    #[Route('/media/content/{id}', name: 'content_media', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function image(ContentMedia $media, Request $request, ContentMediaStorage $storage): Response {
        $element = $media->getElement();
        if (!$element?->isActive()) {
            $this->denyAccessUnlessGranted($element?->getPage()?->isLegal() ? 'ROLE_ADMIN' : 'ROLE_EDITOR');
        }
        try { $path = $storage->verifiedPath($media); } catch (\InvalidArgumentException) { throw $this->createNotFoundException(); }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $media->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($element?->isActive()) { $response->setPublic()->setMaxAge(3600); }
        else { $response->setPrivate()->setMaxAge(0); }
        $response->setAutoEtag()->setAutoLastModified(); $response->isNotModified($request);
        return $response;
    }
}
