<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\DecimalAmount;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class VoucherExtension extends AbstractExtension
{
    public function getFunctions(): array { return [new TwigFunction('voucher_money', [DecimalAmount::class, 'format'])]; }
}
