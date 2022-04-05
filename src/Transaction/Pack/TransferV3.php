<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\Transfer;
use function LTO\decode;

/**
 * Callable to get binary for a transfer transaction v3.
 */
class TransferV3
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
            'CCaJCa32Ja26Jna*',
            $tx::TYPE,
            $tx->version,
            $tx->getNetwork(),
            $tx->timestamp,
            1, // key type 'ed25519'
            decode($tx->senderPublicKey, 'base58'),
            $tx->fee,
            decode($tx->recipient, 'base58'),
            $tx->amount,
            $tx->attachment->length(),
            $tx->attachment->raw()
        );
    }
}
