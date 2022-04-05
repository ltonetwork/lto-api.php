<?php

declare(strict_types=1);

namespace LTO\Transaction;

/**
 * LTO transaction to sponsor an account.
 */
class Sponsorship extends AbstractSponsorship
{
    /** Default transaction fee */
    public const DEFAULT_FEE = 500000000;

    /** Transaction type */
    public const TYPE = 18;
}
