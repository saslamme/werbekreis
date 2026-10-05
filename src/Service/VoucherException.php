<?php

declare(strict_types=1);

namespace App\Service;

/** Safe, user-facing voucher validation failure; never includes codes or DB details. */
final class VoucherException extends \DomainException {}
