<?php

declare(strict_types=1);

namespace LTO\Transaction;

use function LTO\decode;

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
     * @param string $script
     * @param string $encoding  'base64' or 'raw'
     */
    public function __construct(string $script, string $encoding = 'raw')
    {
        if ($encoding !== 'raw' && $encoding !== 'base64') {
            throw new \InvalidArgumentException("Script should be base64 encoded or raw, not $encoding");
        }

        $this->script = $encoding === 'raw' ? base64_encode($script) : $script;
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

        return pack(
            'CCaa32a26JJJ',
            static::TYPE,
            static::VERSION,
            $this->getNetwork(),
            decode($this->senderPublicKey, 'base58'),
            decode($this->script, 'base64'),
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
        static::assertNoMissingKeys($data);
        static::assertTypeAndVersion($data, static::TYPE, static::VERSION);

        return static::createFromData($data);
    }
}
