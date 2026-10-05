<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Company;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

final class DirectorySlugger
{
    public function __construct(private readonly SluggerInterface $slugger, private readonly CompanyRepository $companies, private readonly CategoryRepository $categories)
    {
    }

    public function assign(Category|Company $entity): void
    {
        if ($entity->getSlug() !== '') {
            return;
        }
        $name = strtr($entity->getName(), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
        $base = rtrim(substr($this->slugger->slug($name)->lower()->toString(), 0, 170), '-') ?: 'eintrag';
        $used = $entity instanceof Company ? $this->companies->existingSlugs($base, $entity->getId()) : $this->categories->existingSlugs($base, $entity->getId());
        $slug = $base;
        for ($suffix = 2; in_array($slug, $used, true); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }
        $entity->setSlug($slug);
    }
}
