<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\is_valid_address;

/**
 * LTO transaction to stop sponsoring an account.
 */
class CancelSponsor extends Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 500000000;

    /** Transaction type */
    public const TYPE = 19;

    /** Transaction version */
    public const VERSION  = 1;

    /** @var string */
    public $recipient;


    /**
     * Class constructor.
     *
     * @param string $recipient   Recipient address (base58 encoded)
     */
    public function __construct(string $recipient)
    {
        if (!is_valid_address($recipient, 'base58')) {
            throw new \InvalidArgumentException("Invalid recipient address; is it base58 encoded?");
        }

        $this->recipient = $recipient;
        $this->fee = self::MINIMUM_FEE;
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
            'CCaa32a26JJ',
            static::TYPE,
            static::VERSION,
            $this->getNetwork(),
            decode($this->senderPublicKey, 'base58'),
            decode($this->recipient, 'base58'),
            $this->timestamp,
            $this->fee
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
