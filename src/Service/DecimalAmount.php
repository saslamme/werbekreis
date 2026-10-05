<?php

declare(strict_types=1);

namespace App\Service;

/** Existing Offer/Job decimal-string pattern shared by financial voucher operations. */
final class DecimalAmount
{
    public const PATTERN = '/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D';

    public static function normalize(?string $value): ?string
    {
        $value = trim($value ?? '');
        if ($value === '') { return null; }
        if (!preg_match(self::PATTERN, $value)) { return $value; }
        return self::fromMinor(self::toMinor($value));
    }
    public static function toMinor(string $value): int
    {
        if (!preg_match(self::PATTERN, $value)) { throw new VoucherException('Bitte einen nicht negativen Betrag mit höchstens zwei Nachkommastellen eingeben.'); }
        [$euros, $cents] = array_pad(explode('.', $value), 2, '');
        return (int) $euros * 100 + (int) str_pad($cents, 2, '0');
    }
    public static function fromMinor(int $value): string
    {
        if ($value < 0) { throw new VoucherException('Guthaben darf nicht negativ sein.'); }
        return (string) intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }
    public static function format(string $value): string
    {
        if (!preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/D', $value, $parts)) { throw new VoucherException('Ungültiger Betrag.'); }
        $euros = ltrim($parts[1], '0') ?: '0'; $cents = str_pad($parts[2] ?? '', 2, '0');
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $euros).','.$cents.' €';
    }
}
