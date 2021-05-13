<?php

declare(strict_types=1);

namespace LTO\Transaction;

use function LTO\decode;

/**
 * Transaction to create a smart account by setting a script.
 *
 * Note: the script needs to be compiled before it's used.
 * This can be done by the public node via endpoint `/utils/script/compile`.
 */
class SetScript extends \LTO\Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 500000000;

    /** Transaction type */
    public const TYPE = 13;

    /** Transaction version */
    public const VERSION = 1;

    /**
     * Base64 encoded script
     * @var string
     */
    public $script;

    /**
     * Class constructor.
     *
     * @param string|null $compiledScript  Base64 encoded compiled script or NULL to remove the script
     */
    public function __construct(?string $compiledScript)
    {
        $this->fee = self::MINIMUM_FEE;

        $this->script = $compiledScript !== null
            ? preg_replace('/^(base64:)?/', 'base64:', $compiledScript)
            : null;
    }

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        if ($this->senderPublicKey === null) {
            throw new \BadMethodCallException("Sender public key not set");
        }

        if ($this->timestamp === null) {
            throw new \BadMethodCallException("Timestamp not set");
        }

        $binaryScript = $this->script !== null
            ? decode(preg_replace('/^base64:/', '', $this->script), 'base64')
            : '';

        return pack(
            'CCaa26na*JJ',
            static::TYPE,
            static::VERSION,
            $this->getNetwork(),
            decode($this->senderPublicKey, 'base58'),
            strlen($binaryScript),
            $binaryScript,
            $this->fee,
            $this->timestamp
        );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return
            ['type' => static::TYPE, 'version' => static::VERSION] +
            parent::jsonSerialize();
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data, ['id', 'height', 'script']);
        static::assertTypeAndVersion($data, static::TYPE, static::VERSION);

        return static::createFromData($data);
    }
}
