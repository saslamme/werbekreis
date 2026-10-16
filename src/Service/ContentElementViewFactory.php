<?php
declare(strict_types=1);
namespace App\Service;
use App\Entity\{ContentPage, ContentElement};
use App\Content\ElementTypes;
use App\Repository\{CategoryRepository, OfferRepository, NewsArticleRepository, EventRepository, CompanyRepository, JobPostingRepository, VoucherProductRepository, BoardMemberRepository};
final class ContentElementViewFactory
{
    public function __construct(private CategoryRepository $categories, private OfferRepository $offers, private NewsArticleRepository $news, private EventRepository $events, private CompanyRepository $companies, private JobPostingRepository $jobs, private VoucherProductRepository $vouchers, private BoardMemberRepository $board) {}
    public function forPage(ContentPage $page): array {
        $views = [];
        foreach ($page->getElements() as $element) { if ($element->isActive() && ($view = $this->create($element)) !== null) { $views[] = $view; } }
        return $views;
    }
    public function create(ContentElement $element): ?array {
        if (!$element->isActive()) { return null; }
        $type = $element->getType(); $data = $element->getData() + ElementTypes::defaults($type);
        // Also guard historical/imported configuration at the rendering boundary.
        foreach (['buttonUrl', 'linkUrl'] as $key) { if (isset($data[$key]) && (!is_string($data[$key]) || !ElementTypes::safeUrl($data[$key]))) { $data[$key] = ''; } }
        $limit = max(1, min(12, (int) ($data['limit'] ?? 3)));
        $extra = match ($type) {
            'categories' => ['categories' => ($data['limit'] ?? 0) > 0 ? array_slice($this->categories->findActiveWithPublicCompanyCount(), 0, (int) $data['limit']) : $this->categories->findActiveWithPublicCompanyCount()],
            'hero' => ['categories' => ($data['showPopularCategories'] ?? false) ? array_slice($this->categories->findActiveWithPublicCompanyCount(), 0, 4) : []],
            'offers' => ['items' => $this->offers->findCurrentFeatured($limit)],
            'news' => ['items' => $this->news->findFeaturedPublic($limit)],
            'event_list' => ['items' => $this->events->findUpcomingFeatured($limit)],
            'company_list' => ['items' => $this->companies->findFeaturedPublic($limit)],
            'jobs' => ['items' => $this->jobs->findFeaturedPublic($limit)],
            'voucher' => ['voucher_acceptance_count' => $this->vouchers->acceptanceCount()],
            'board_list' => ['members' => $this->board->findPublic()],
            'video' => ['embedUrl' => ElementTypes::videoEmbed((string) ($data['videoUrl'] ?? ''))],
            default => [],
        };
        if ($type === 'company') {
            $company = $element->getLinkedCompany();
            if (!$company || $this->companies->findPublicBySlug($company->getSlug()) === null) { return null; }
            $extra['company'] = $company;
        }
        if ($type === 'event') {
            $event = $element->getLinkedEvent();
            if (!$event || $this->events->findPublicBySlug($event->getSlug()) === null || $event->getOccurrences()->isEmpty()) { return null; }
            $extra['occurrence'] = $event->getOccurrences()->first();
        }
        return ['element' => $element, 'data' => $data] + $extra;
    }
}
