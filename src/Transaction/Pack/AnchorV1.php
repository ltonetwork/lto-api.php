<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\Anchor;
use function LTO\decode;

/**
 * Callable to get binary for an anchor transaction v1.
 */
class AnchorV1
{
    /**
     * Get binary (to sign) for transaction.
     */
    public function __invoke(Anchor $tx): string
    {
        if ($tx->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($tx->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        $packed = pack(
            'CCa32n',
            Anchor::TYPE,
            $tx->version,
            decode($tx->senderPublicKey, 'base58'),
            count($tx->anchors)
        );

        foreach ($tx->anchors as $anchor) {
            $rawHash = decode($anchor, 'base58');
            $packed .= pack('na*', strlen($rawHash), $rawHash);
        }

        $packed .= pack(
            'JJ',
            $tx->timestamp,
            $tx->fee
        );

        return $packed;
    }
}
