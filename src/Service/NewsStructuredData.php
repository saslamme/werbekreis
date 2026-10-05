<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewsArticle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class NewsStructuredData
{
    public function __construct(private UrlGeneratorInterface $urls, #[Autowire('%portal.name%')] private string $publisherName) {}
    public function forArticle(NewsArticle $article): array
    {
        $url = $this->urls->generate('news_show', ['slug' => $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        $data = ['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $article->getTitle(), 'url' => $url,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url], 'datePublished' => $article->getPublicationDate()->format(DATE_ATOM), 'dateModified' => $article->getUpdatedAt()->format(DATE_ATOM),
            'publisher' => ['@type' => 'Organization', 'name' => $this->publisherName]];
        if ($article->getTeaser() !== '') { $data['description'] = $article->getTeaser(); }
        if ($article->getAuthorName()) { $data['author'] = ['@type' => 'Person', 'name' => $article->getAuthorName()]; }
        if ($article->getImagePath()) { $data['image'] = $this->urls->generate('news_image', ['slug' => $article->getSlug(), 'fileName' => $article->getImagePath()], UrlGeneratorInterface::ABSOLUTE_URL); }
        return $data;
    }
}
