<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\VoucherRepository;

final class VoucherCodeGenerator implements VoucherCodeGeneratorInterface
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const PATTERN = '/^(?:TEST-)?WK-(?:[2-9A-HJKMNP-Z]{4}-){3}[2-9A-HJKMNP-Z]{4}$/D';
    public function __construct(private readonly VoucherRepository $vouchers) {}
    public static function normalize(string $value): string
    {
        return strtoupper(preg_replace('/\s+/u', '', trim($value)) ?? '');
    }
    public function generate(): string
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $parts = [];
            for ($group = 0; $group < 4; ++$group) {
                $part = ''; for ($i = 0; $i < 4; ++$i) { $part .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)]; } $parts[] = $part;
            }
            $code = 'WK-'.implode('-', $parts);
            if ($this->vouchers->findByCode($code) === null) { return $code; }
        }
        throw new VoucherException('Der Gutscheincode konnte nicht vergeben werden. Bitte erneut versuchen.');
    }
}
