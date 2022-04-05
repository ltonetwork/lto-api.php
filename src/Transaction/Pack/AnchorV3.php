<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\Anchor;
use function LTO\decode;

/**
 * Callable to get binary for an anchor transaction v3.
 */
class AnchorV3
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
            'CCaJCa32Jn',
            Anchor::TYPE,
            $tx->version,
            $tx->getNetwork(),
            $tx->timestamp,
            1, // key type 'ed25519'
            decode($tx->senderPublicKey, 'base58'),
            $tx->fee,
            count($tx->anchors)
        );

        foreach ($tx->anchors as $anchor) {
            $packed .= pack('na*', $anchor->length(), $anchor->raw());
        }

        return $packed;
    }
}
