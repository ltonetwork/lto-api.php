<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\MassTransfer;
use function LTO\decode;

/**
 * Callable to get binary for a mass transfer transaction v3.
 */
class MassTransferV3
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

        $binaryAttachment = decode($tx->attachment, 'base58');

        $packed = pack(
            'CCaJCa32Jn',
            MassTransfer::TYPE,
            $tx->version,
            $tx->getNetwork(),
            $tx->timestamp,
            1, // key type 'ed25519'
            decode($tx->senderPublicKey, 'base58'),
            $tx->fee,
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
            'na*',
            strlen($binaryAttachment),
            $binaryAttachment
        );

        return $packed;
    }
}
