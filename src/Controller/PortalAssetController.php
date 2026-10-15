<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PortalSettingsRepository;
use App\Service\PortalAssetStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, Request, ResponseHeaderBag};
use Symfony\Component\Routing\Attribute\Route;

final class PortalAssetController extends AbstractController
{
    #[Route('/media/portal/logo', name: 'portal_logo', methods: ['GET'])]
    public function logo(Request $request, PortalSettingsRepository $settings, PortalAssetStorage $assets): BinaryFileResponse
    {
        $name = $settings->getSettings()->getLogoFileName();
        if ($name === null) { throw $this->createNotFoundException(); }
        try {
            $path = $assets->path($name);
            $mime = $assets->mimeType($name);
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException();
        }
        if (!is_file($path) || !is_readable($path)) { throw $this->createNotFoundException(); }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'none'; sandbox");
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $name);
        // Every request revalidates; the random filename in ?v changes even within one second.
        $response->setPublic()->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setEtag($name);
        $response->isNotModified($request);
        return $response;
    }
}
