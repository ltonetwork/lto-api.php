<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\SetScript;
use function LTO\decode;

/**
 * Callable to get binary for a set script transaction v1.
 */
class SetScriptV1
{
    /**
     * Get binary (to sign) for transaction.
     */
    public function __invoke(SetScript $tx): string
    {
        if ($tx->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($tx->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        $packed = pack(
            'CCaa26',
            SetScript::TYPE,
            $tx->version,
            $tx->getNetwork(),
            decode($tx->senderPublicKey, 'base58')
        );

        if ($tx->script !== null) {
            $binaryScript = decode(preg_replace('/^base64:/', '', $tx->script), 'base64');
            $packed .= pack('Cna*', 1, strlen($binaryScript), $binaryScript);
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
