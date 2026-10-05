<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Company;
use App\Entity\Offer;
use App\Entity\Event;
use App\Entity\EventCategory;
use App\Entity\JobPosting;
use App\Entity\NewsArticle;
use App\Entity\NewsCategory;
use App\Service\DirectorySlugger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class DirectorySlugSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly DirectorySlugger $slugger)
    {
    }
    public static function getSubscribedEvents(): array
    {
        return [FormEvents::POST_SUBMIT => ['assign', 10]];
    }
    public function assign(FormEvent $event): void
    {
        $data = $event->getForm()->getData();
        if ($event->getForm()->isSynchronized() && ($data instanceof Company || $data instanceof Category || $data instanceof Offer || $data instanceof Event || $data instanceof EventCategory || $data instanceof NewsArticle || $data instanceof NewsCategory || $data instanceof JobPosting)) {
            $this->slugger->assign($data);
        }
    }
}
