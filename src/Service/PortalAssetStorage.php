<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PortalSettings;
use Doctrine\ORM\EntityManagerInterface;
use enshrined\svgSanitize\Sanitizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PortalAssetStorage
{
    public const UPLOAD_ERROR = 'Bitte eine SVG-, PNG- oder JPEG-Datei bis maximal 5 MB hochladen.';
    private const MIME_EXTENSIONS = ['image/svg+xml' => 'svg', 'image/png' => 'png', 'image/jpeg' => 'jpg'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Filesystem $files,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(resolve:PORTAL_UPLOAD_DIR)%')] private readonly string $directory,
    ) {}

    public function path(string $name): string
    {
        if (!preg_match(PortalSettings::LOGO_NAME_PATTERN, $name)) {
            throw new \InvalidArgumentException('Invalid stored portal logo name.');
        }
        $path = $this->directory.DIRECTORY_SEPARATOR.$name;
        if (is_link($path)) {
            throw new \InvalidArgumentException('Portal logo must not be a symbolic link.');
        }
        return $path;
    }

    public function mimeType(string $name): string
    {
        $this->path($name);
        return array_search(pathinfo($name, PATHINFO_EXTENSION), self::MIME_EXTENSIONS, true);
    }

    public function save(PortalSettings $settings, ?UploadedFile $file, bool $remove = false, ?UploadedFile $footerFile = null, bool $removeFooter = false): void
    {
        $slots = [
            'Logo' => [$file, $remove],
            'FooterLogo' => [$footerFile, $removeFooter],
        ];
        $old = [];
        $staged = [];
        foreach ($slots as $slot => $_) {
            $old[$slot] = [$settings->{'get'.$slot.'FileName'}(), $settings->{'get'.$slot.'OriginalName'}()];
        }
        try {
            // Stage both uploads before changing either slot's metadata.
            foreach ($slots as $slot => [$upload]) {
                if ($upload !== null) { $staged[$slot] = $this->stage($upload); }
            }
            foreach ($slots as $slot => [$upload, $removeSlot]) {
                // Upload takes precedence over removal, independently for each slot.
                if ($upload !== null) {
                    $original = basename(str_replace('\\', '/', $upload->getClientOriginalName()));
                    $original = preg_replace('/[\x00-\x1f\x7f]/u', '', $original) ?? '';
                    $settings->{'set'.$slot.'FileName'}($staged[$slot]);
                    $settings->{'set'.$slot.'OriginalName'}(mb_substr($original, 0, 255));
                } elseif ($removeSlot) {
                    $settings->{'set'.$slot.'FileName'}(null);
                    $settings->{'set'.$slot.'OriginalName'}(null);
                }
            }
            $this->em->flush();
        } catch (\Throwable $exception) {
            foreach ($staged as $name) { $this->removeFile($name); }
            foreach ($old as $slot => [$name, $original]) {
                $settings->{'set'.$slot.'FileName'}($name);
                $settings->{'set'.$slot.'OriginalName'}($original);
            }
            throw $exception;
        }
        // Delete only after commit, and never delete a file still used by either slot.
        $active = [$settings->getLogoFileName(), $settings->getFooterLogoFileName()];
        foreach (array_unique(array_column($old, 0)) as $name) {
            if ($name !== null && !in_array($name, $active, true)) { $this->removeFile($name); }
        }
    }

    private function stage(UploadedFile $file): string
    {
        $errors = $this->validator->validate($file, new Assert\File(maxSize: 5_000_000, mimeTypes: array_keys(self::MIME_EXTENSIONS)));
        if (count($errors) !== 0 || !$file->isValid()) {
            throw new \InvalidArgumentException(self::UPLOAD_ERROR);
        }
        $mime = $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mime] ?? throw new \InvalidArgumentException(self::UPLOAD_ERROR);
        $contents = null;
        if ($extension === 'svg') {
            $contents = $this->sanitizeSvg($file->getContent());
        } else {
            $errors = $this->validator->validate($file, new Assert\Image(
                maxWidth: 8000, maxHeight: 8000, detectCorrupted: true, mimeTypes: ['image/png', 'image/jpeg'],
            ));
            if (count($errors) !== 0) {
                throw new \InvalidArgumentException(self::UPLOAD_ERROR);
            }
        }
        $this->files->mkdir($this->directory, 0750);
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $path = $this->path($name);
        try {
            if ($contents !== null) {
                $this->files->dumpFile($path, $contents);
            } else {
                $file->move($this->directory, $name);
            }
            $this->files->chmod($path, 0640);
        } catch (\Throwable $exception) {
            $this->removeFile($name);
            throw $exception;
        }
        return $name;
    }

    private function sanitizeSvg(string $xml): string
    {
        $this->assertSafeSvg($xml);
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($xml);
        if ($clean === false || strlen($clean) > 5_000_000) {
            throw new \InvalidArgumentException(self::UPLOAD_ERROR);
        }
        // Validate the serialized result, not just the original input.
        $this->assertSafeSvg($clean);
        return $clean;
    }

    private function assertSafeSvg(string $xml): void
    {
        // Reject declarations before parsing; no entity substitution, DTD loading or network access.
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i', $xml)) {
            throw new \InvalidArgumentException(self::UPLOAD_ERROR);
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null
                || $document->documentElement?->localName !== 'svg'
                || $document->documentElement->namespaceURI !== 'http://www.w3.org/2000/svg') {
                throw new \InvalidArgumentException(self::UPLOAD_ERROR);
            }
            // Static SVG subset only: no HTML, scripts, CSS, animations or resource-bearing elements.
            $elements = ['svg', 'g', 'defs', 'title', 'desc', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'textPath', 'use', 'symbol', 'clipPath', 'mask', 'linearGradient', 'radialGradient', 'stop'];
            foreach ($document->getElementsByTagName('*') as $element) {
                if ($element->namespaceURI !== 'http://www.w3.org/2000/svg' || !in_array($element->localName, $elements, true)) {
                    throw new \InvalidArgumentException(self::UPLOAD_ERROR);
                }
                foreach ($element->attributes as $attribute) {
                    $name = strtolower($attribute->localName);
                    $value = $attribute->value;
                    if ($attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/') { continue; }
                    if (($attribute->namespaceURI !== null && !in_array($attribute->namespaceURI, ['http://www.w3.org/1999/xlink', 'http://www.w3.org/XML/1998/namespace'], true))
                        || str_starts_with($name, 'on') || in_array($name, ['style', 'base', 'src'], true)
                        || preg_match('/javascript\s*:|url\s*\(|@import|[\\\\\x00-\x08\x0b\x0c\x0e-\x1f]/i', $value)
                        || ($name === 'href' && !preg_match('/^#[a-zA-Z_][a-zA-Z0-9_.:-]*$/D', $value))) {
                        throw new \InvalidArgumentException(self::UPLOAD_ERROR);
                    }
                }
            }
            $xpath = new \DOMXPath($document);
            if ($xpath->query('//processing-instruction()')->length !== 0) {
                throw new \InvalidArgumentException(self::UPLOAD_ERROR);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function removeFile(string $name): void
    {
        try {
            $this->files->remove($this->path($name));
        } catch (IOExceptionInterface|\InvalidArgumentException $exception) {
            $this->logger->warning('Portal logo could not be removed.', ['fileName' => $name, 'exception' => $exception]);
        }
    }
}
