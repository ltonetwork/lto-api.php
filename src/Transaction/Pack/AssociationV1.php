<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction;
use LTO\Transaction\Association;
use LTO\Transaction\RevokeAssociation;
use function LTO\decode;

/**
 * Callable to get binary for an association or revoke association transaction v1.
 */
class AssociationV1
{
    /**
     * Get binary (to sign) for transaction.
     *
     * @var Association|RevokeAssociation $tx
     * @return string
     */
    public function __invoke(Transaction $tx): string
    {
        if (!$tx instanceof Association && !$tx instanceof RevokeAssociation) {
            throw new \InvalidArgumentException("Expected an Association or RevokeAssociation transaction"); // @codeCoverageIgnore
        }

        if ($tx->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($tx->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        $packed = pack(
            'CCaa32a26N',
            $tx::TYPE,
            $tx->version,
            $tx->getNetwork(),
            decode($tx->senderPublicKey, 'base58'),
            decode($tx->party, 'base58'),
            $tx->associationType
        );

        if ($tx->hash !== '') {
            $rawHash = decode($tx->hash, 'base58');
            $packed .= pack(
                'Cna*',
                1,
                strlen($rawHash),
                $rawHash
            );
        } else {
            $packed .= pack('C', 0);
        }

        $packed .= pack(
            'JJ',
            $tx->timestamp,
            $tx->fee
        );

        return $packed;
    }
}
