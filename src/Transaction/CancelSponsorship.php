<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\is_valid_address;

/**
 * LTO transaction to stop sponsoring an account.
 */
class CancelSponsorship extends AbstractSponsorship
{
    /** Default transaction fee */
    public const DEFAULT_FEE = 500000000;

    /** Transaction type */
    public const TYPE = 19;
}
