<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Company;
use App\Entity\CompanyImage;
use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CompanyImageStorage
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Filesystem $files,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(resolve:COMPANY_UPLOAD_DIR)%')] private readonly string $directory,
    ) {
    }

    public function path(string $fileName): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D', $fileName)) {
            throw new \InvalidArgumentException('Invalid stored image name.');
        }

        return $this->directory.DIRECTORY_SEPARATOR.$fileName;
    }

    public function save(CompanyImage|Offer $image, ?UploadedFile $file, bool $removeImage = false): void
    {
        $oldName = $image->getFileName();
        $newName = null;
        if ($file !== null) {
            $extension = match ($file->getMimeType()) {
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                default => throw new \InvalidArgumentException('Unsupported image type.'),
            };
            $this->files->mkdir($this->directory, 0750);
            $newName = bin2hex(random_bytes(16)).'.'.$extension;
            $file->move($this->directory, $newName);
            $image->setFileName($newName);
        }
        if ($file === null && $removeImage) {
            $image->setFileName('');
        }
        try {
            $image->getCompany()?->touch();
            $this->em->persist($image);
            $this->em->flush();
        } catch (\Throwable $exception) {
            if ($newName !== null) {
                $this->removeFile($newName);
            }
            $image->setFileName($oldName);
            throw $exception;
        }
        if (($newName !== null || $removeImage) && $oldName !== '') {
            $this->removeFile($oldName);
        }
    }

    public function deleteImage(CompanyImage|Offer $image): void
    {
        $fileName = $image->getFileName();
        $image->getCompany()?->touch();
        $this->em->remove($image);
        $this->em->flush();
        $this->removeFile($fileName);
    }

    public function deleteCompany(Company $company): void
    {
        $fileNames = array_map(static fn (CompanyImage $image): string => $image->getFileName(), $company->getImages()->toArray());
        foreach ($company->getOffers() as $offer) {
            if ($offer->getImagePath() !== null) {
                $fileNames[] = $offer->getImagePath();
            }
        }
        $this->em->remove($company);
        $this->em->flush();
        foreach ($fileNames as $name) {
            $this->removeFile($name);
        }
    }

    private function removeFile(string $fileName): void
    {
        if ($fileName === '') {
            return;
        }
        try {
            $this->files->remove($this->path($fileName));
        } catch (IOExceptionInterface $exception) {
            // DB state is already committed. A maintenance cleanup can retry safely.
            $this->logger->warning('Company image could not be removed; retry image cleanup.', ['fileName' => $fileName, 'exception' => $exception]);
        }
    }

    /** @return list<string> */
    public function cleanup(bool $delete = false): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }
        $used = array_fill_keys(array_column($this->em->createQueryBuilder()->select('image.fileName')->from(CompanyImage::class, 'image')->getQuery()->getScalarResult(), 'fileName'), true);
        foreach ($this->em->createQueryBuilder()->select('offer.imagePath')->from(Offer::class, 'offer')->where('offer.imagePath IS NOT NULL')->getQuery()->getScalarResult() as $row) {
            $used[$row['imagePath']] = true;
        }
        $unused = [];
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if (!$file->isFile() || $file->isLink() || $file->getMTime() > time() - 3600 || !preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/D', $file->getFilename()) || isset($used[$file->getFilename()])) {
                continue;
            }
            $unused[] = $file->getFilename();
            if ($delete) {
                $this->removeFile($file->getFilename());
            }
        }

        return $unused;
    }
}
