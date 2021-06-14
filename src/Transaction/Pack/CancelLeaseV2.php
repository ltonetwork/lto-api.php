<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\CancelLease;
use function LTO\decode;

/**
 * Callable to get binary for a cancel lease transaction v2.
 */
class CancelLeaseV2
{
    /**
     * Get binary (to sign) for transaction.
     */
    public function __invoke(CancelLease $tx): string
    {
        if ($tx->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($tx->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        return pack(
            'CCaa32JJa32',
            CancelLease::TYPE,
            $tx->version,
            $tx->getNetwork(),
            decode($tx->senderPublicKey, 'base58'),
            $tx->fee,
            $tx->timestamp,
            decode($tx->leaseId, 'base58')
        );
    }
}
