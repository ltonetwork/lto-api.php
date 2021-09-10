<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\AbstractAssociation;
use LTO\Transaction\Association;
use LTO\Transaction\RevokeAssociation;
use function LTO\decode;

/**
 * Callable to get binary for an association or revoke association transaction v3.
 */
class AssociationV3
{
    /**
     * Get binary (to sign) for transaction.
     *
     * @var Association|RevokeAssociation $tx
     * @return string
     */
    public function __invoke(AbstractAssociation $tx): string
    {
        if ($tx->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($tx->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        $rawHash = decode($tx->hash, 'base58');

        return pack(
            'CCaJCa32Ja26NJna*',
            $tx::TYPE,
            $tx->version,
            $tx->getNetwork(),
            $tx->timestamp,
            1, // key type 'ed25519'
            decode($tx->senderPublicKey, 'base58'),
            $tx->fee,
            decode($tx->recipient, 'base58'),
            $tx->associationType,
            $tx->expire ?? 0,
            strlen($rawHash),
            $rawHash
        );
    }
}