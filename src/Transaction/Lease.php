<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\is_valid_address;

/**
 * LTO transaction to start leasing.
 */
class Lease extends Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 100000000;

    /** Transaction type */
    public const TYPE = 8;

    /** Transaction version */
    public const VERSION  = 2;

    /** @var int */
    public $amount;

    /** @var string */
    public $recipient;


    /**
     * Class constructor.
     *
     * @param int    $amount      Amount of LTO (*10^8)
     * @param string $recipient   Recipient address (base58 encoded)
     */
    public function __construct(int $amount, string $recipient)
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Invalid amount; should be greater than 0");
        }

        if (!is_valid_address($recipient, 'base58')) {
            throw new \InvalidArgumentException("Invalid recipient address; is it base58 encoded?");
        }

        $this->amount = $amount;
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
            'CCCa32a26JJJ',
            self::TYPE,
            self::VERSION,
            0,
            decode($this->senderPublicKey, 'base58'),
            decode($this->recipient, 'base58'),
            $this->amount,
            $this->fee,
            $this->timestamp
        );
    }

    /**
     * Get data for JSON serialization.
     */
    public function jsonSerialize()
    {
        return
            ['type' => self::TYPE, 'version' => self::VERSION] +
            parent::jsonSerialize();
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data);
        static::assertTypeAndVersion($data, self::TYPE, self::VERSION);

        return static::createFromData($data);
    }
}
