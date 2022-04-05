<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\Lease;
use function LTO\decode;

/**
 * Callable to get binary for a lease transaction v3.
 */
class LeaseV3
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
            'CCaJCa32Ja26J',
            Lease::TYPE,
            $tx->version,
            $tx->getNetwork(),
            $tx->timestamp,
            1, // key type 'ed25519'
            decode($tx->senderPublicKey, 'base58'),
            $tx->fee,
            decode($tx->recipient, 'base58'),
            $tx->amount
        );
    }
}
