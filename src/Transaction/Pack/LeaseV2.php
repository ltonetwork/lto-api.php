<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\Lease;
use function LTO\decode;

/**
 * Callable to get binary for a lease transaction v2.
 */
class LeaseV2
{
    /**
     * Get binary (to sign) for transaction.
     */
    public function __invoke(Lease $tx): string
    {
        if ($tx->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($tx->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        return pack(
            'CCCa32a26JJJ',
            Lease::TYPE,
            $tx->version,
            0,
            decode($tx->senderPublicKey, 'base58'),
            decode($tx->recipient, 'base58'),
            $tx->amount,
            $tx->fee,
            $tx->timestamp
        );
    }
}
