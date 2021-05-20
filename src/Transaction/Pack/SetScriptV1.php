<?php

declare(strict_types=1);

namespace LTO\Transaction\Pack;

use LTO\Transaction\SetScript;
use function LTO\decode;

/**
 * Callable to get binary for an set script transaction v1.
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

        $binaryScript = $tx->script !== null
            ? decode(preg_replace('/^base64:/', '', $tx->script), 'base64')
            : '';

        return pack(
            'CCaa26na*JJ',
            SetScript::TYPE,
            $tx->version,
            $tx->getNetwork(),
            decode($tx->senderPublicKey, 'base58'),
            strlen($binaryScript),
            $binaryScript,
            $tx->fee,
            $tx->timestamp
        );
    }
}
