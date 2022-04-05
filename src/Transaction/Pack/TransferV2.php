<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\Transfer;
use function LTO\decode;

/**
 * Callable to get binary for a transfer transaction v2.
 */
class TransferV2
{
    /**
     * Get binary (to sign) for transaction.
     */
    public function __invoke(Transfer $tx): string
    {
        if ($tx->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($tx->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        return pack(
            'CCa32JJJa26na*',
            Transfer::TYPE,
            $tx->version,
            decode($tx->senderPublicKey, 'base58'),
            $tx->timestamp,
            $tx->amount,
            $tx->fee,
            decode($tx->recipient, 'base58'),
            $tx->attachment->length(),
            $tx->attachment->raw()
        );
    }
}
