<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\CancelSponsor;
use function LTO\decode;

/**
 * Callable to get binary for a cancel sponsor transaction v1.
 */
class CancelSponsorV1
{
    /**
     * Get binary (to sign) for transaction.
     */
    public function __invoke(CancelSponsor $tx): string
    {
        if ($tx->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($tx->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        return pack(
            'CCaa32a26JJ',
            CancelSponsor::TYPE,
            $tx->version,
            $tx->getNetwork(),
            decode($tx->senderPublicKey, 'base58'),
            decode($tx->recipient, 'base58'),
            $tx->timestamp,
            $tx->fee
        );
    }
}
