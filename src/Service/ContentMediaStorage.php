<?php
declare(strict_types=1);
namespace App\Service;
use App\Entity\{ContentElement, ContentMedia};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
final class ContentMediaStorage
{
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    public function __construct(private EntityManagerInterface $em, private ValidatorInterface $validator, private LoggerInterface $logger,
        #[Autowire('%env(resolve:CONTENT_UPLOAD_DIR)%')] private string $directory) {}
    public function path(string $name): string {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $name)) { throw new \InvalidArgumentException('Ungültiger Medienname.'); }
        $path = $this->directory.'/'.$name;
        if (is_link($path)) { throw new \InvalidArgumentException('Symbolische Links sind nicht erlaubt.'); }
        return $path;
    }
    public function verifiedPath(ContentMedia $media): string {
        $path = $this->path($media->getFileName());
        if (!is_file($path) || !is_readable($path)) { throw new \InvalidArgumentException('Bild fehlt.'); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($mime !== $media->getMimeType() || (self::MIMES[$mime] ?? null) !== pathinfo($path, PATHINFO_EXTENSION)) { throw new \InvalidArgumentException('Ungültiger Bildtyp.'); }
        return $path;
    }
    private function stage(UploadedFile $file): ContentMedia {
        $errors = $this->validator->validate($file, new Assert\Image(maxSize: 5_000_000, mimeTypes: array_keys(self::MIMES), maxWidth: 8000, maxHeight: 8000, detectCorrupted: true));
        if (!$file->isValid() || count($errors) !== 0) { throw new \InvalidArgumentException('Nur gültige JPEG-, PNG- oder WebP-Bilder bis 5 MB erlaubt.'); }
        $mime = $file->getMimeType(); $extension = self::MIMES[$mime] ?? throw new \InvalidArgumentException('Ungültiger Bildtyp.');
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) { throw new \RuntimeException('Upload-Verzeichnis nicht verfügbar.'); }
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $original = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?? '', 0, 255);
        try { $file->move($this->directory, $name); chmod($this->path($name), 0640); }
        catch (\Throwable $e) { $this->removeFile($name); throw $e; }
        return (new ContentMedia())->setFileName($name)->setOriginalName($original)->setMimeType($mime);
    }
    /** Form mutations are already bound; capture media before binding and pass them here. */
    public function save(ContentElement $element, array $before, array $uploads = [], string $alt = '', bool $decorative = false): void {
        $staged = [];
        try {
            if ($uploads && !$decorative && trim($alt) === '') { throw new \InvalidArgumentException('Alt-Text für neue Bilder angeben oder als dekorativ markieren.'); }
            if (!in_array($element->getType(), ['gallery', 'image_slider'], true) && count($uploads) + $element->getMedia()->count() > 1) { throw new \InvalidArgumentException('Nur ein Bild erlaubt. Entfernen Sie zuerst das bisherige Bild.'); }
            foreach ($uploads as $file) {
                $media = $this->stage($file); $staged[] = $media;
                $media->setAltText($alt)->setDecorative($decorative)->setPosition($element->getMedia()->count()); $element->addMedia($media);
            }
            foreach (array_values($element->getPage()?->getElements()->toArray() ?? []) as $position => $item) { $item->setPosition($position); }
            $this->em->persist($element); $element->getPage()?->touch();
            $this->em->wrapInTransaction(fn() => $this->em->flush());
        } catch (\Throwable $e) {
            foreach ($staged as $media) { $element->removeMedia($media); $this->removeFile($media->getFileName()); }
            // A failed Doctrine transaction closes the manager; never continue using a partially saved unit of work.
            if ($this->em->isOpen()) { $this->em->clear(); }
            throw $e;
        }
        $remaining = array_map(fn(ContentMedia $m) => $m->getFileName(), $element->getMedia()->toArray());
        foreach ($before as $media) { if (!in_array($media->getFileName(), $remaining, true)) { $this->removeFile($media->getFileName()); } }
    }
    public function duplicate(ContentElement $source): ContentElement {
        $copy = (new ContentElement($source->getType()))->setPage($source->getPage())->setData($source->getData())->setActive($source->isActive())->setLinkedEvent($source->getLinkedEvent())->setLinkedCompany($source->getLinkedCompany());
        $copy->setPosition(1 + max(-1, ...array_map(static fn(ContentElement $e) => $e->getPosition(), $source->getPage()->getElements()->toArray())));
        foreach (array_values($source->getPage()->getElements()->toArray()) as $position => $item) { $item->setPosition($position); }
        $source->getPage()->touch();
        $staged = [];
        try {
            foreach ($source->getMedia() as $original) {
                $path = $this->verifiedPath($original); $name = bin2hex(random_bytes(16)).'.'.pathinfo($path, PATHINFO_EXTENSION); $staged[] = $name;
                if (!copy($path, $this->path($name))) { throw new \RuntimeException('Bild konnte nicht kopiert werden.'); } chmod($this->path($name), 0640);
                $copy->addMedia((new ContentMedia())->setFileName($name)->setOriginalName($original->getOriginalName())->setMimeType($original->getMimeType())->setAltText($original->getAltText())->setDecorative($original->isDecorative())->setCaption($original->getCaption())->setPosition($original->getPosition()));
            }
            $this->em->persist($copy);
            $this->em->wrapInTransaction(fn() => $this->em->flush());
        } catch (\Throwable $e) { foreach ($staged as $name) { $this->removeFile($name); } throw $e; }
        return $copy;
    }
    public function delete(ContentElement $element): void {
        $names = array_map(fn(ContentMedia $m) => $m->getFileName(), $element->getMedia()->toArray());
        $page = $element->getPage(); $page?->removeElement($element); $page?->touch(); $this->em->remove($element);
        foreach (array_values($page?->getElements()->toArray() ?? []) as $position => $item) { $item->setPosition($position); }
        $this->em->wrapInTransaction(fn() => $this->em->flush());
        foreach ($names as $name) { $this->removeFile($name); }
    }
    private function removeFile(string $name): void {
        try { $path = $this->path($name); if (is_file($path) && !unlink($path)) { throw new \RuntimeException('unlink failed'); } }
        catch (\Throwable $e) { $this->logger->warning('Content image cleanup failed.', ['fileName' => $name, 'exception' => $e]); }
    }
}
