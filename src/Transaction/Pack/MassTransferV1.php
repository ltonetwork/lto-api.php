<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\MassTransfer;
use function LTO\decode;

/**
 * Callable to get binary for a mass transfer transaction v1.
 */
class MassTransferV1
{
    /**
     * Get binary (to sign) for transaction.
     */
    public function __invoke(MassTransfer $tx): string
    {
        if ($tx->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($tx->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        $packed = pack(
            'CCa32n',
            MassTransfer::TYPE,
            $tx->version,
            decode($tx->senderPublicKey, 'base58'),
            count($tx->transfers)
        );

        foreach ($tx->transfers as $transfer) {
            $packed .= pack(
                'a26J',
                decode($transfer['recipient'], 'base58'),
                $transfer['amount']
            );
        }

        $packed .= pack(
            'JJna*',
            $tx->timestamp,
            $tx->fee,
            $tx->attachment->length(),
            $tx->attachment->raw()
        );

        return $packed;
    }
}
