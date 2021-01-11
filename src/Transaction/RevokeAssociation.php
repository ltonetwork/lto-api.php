<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\encode;
use function LTO\is_valid_address;

/**
 * LTO transaction to revoke an association.
 */
class RevokeAssociation extends Association
{
    /** Transaction type */
    public const TYPE = 17;
}
