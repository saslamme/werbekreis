<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\PortalSettings;
use App\Repository\PortalSettingsRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PortalSettingsExtension extends AbstractExtension
{
    public function __construct(
        private readonly PortalSettingsRepository $settings,
        private readonly RequestStack $requests,
    ) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('portal_settings', $this->getSettings(...))];
    }

    public function getSettings(): PortalSettings
    {
        // Store on the main request so subrequests share the same read, without
        // keeping stale settings in long-running workers or writing during rendering.
        $request = $this->requests->getMainRequest();
        if ($request?->attributes->has('_portal_settings')) {
            return $request->attributes->get('_portal_settings');
        }
        $settings = $this->settings->getSettings();
        $request?->attributes->set('_portal_settings', $settings);
        return $settings;
    }
}
