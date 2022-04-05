<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\SetScript;
use function LTO\decode;

/**
 * Callable to get binary for a set script transaction v3.
 */
class SetScriptV3
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

        $binaryScript = $tx->script !== null
            ? decode(preg_replace('/^base64:/', '', $tx->script), 'base64')
            : '';

        return pack(
            'CCaJCa32Jna*',
            SetScript::TYPE,
            $tx->version,
            $tx->getNetwork(),
            $tx->timestamp,
            1, // key type 'ed25519'
            decode($tx->senderPublicKey, 'base58'),
            $tx->fee,
            strlen($binaryScript),
            $binaryScript
        );
    }
}
