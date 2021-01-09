<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\encode;
use function LTO\is_valid_address;

/**
 * LTO Transfer transaction.
 */
class Transfer extends Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 100000000;

    /** Transaction type */
    public const TYPE = 4;

    /** Transaction version */
    public const VERSION  = 2;

    /** @var int */
    public $amount;

    /** @var string */
    public $recipient;

    /** @var string */
    public $attachment = '';


    /**
     * Class constructor.
     *
     * @param int    $amount      Amount of LTO (*10^8)
     * @param string $recipient   Recipient address (base58 encoded)
     */
    public function __construct(string $recipient, int $amount)
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

        $binaryAttachment = decode($this->attachment, 'base58');

        return pack(
            'CCa32JJJa26na*',
            static::TYPE,
            static::VERSION,
            decode($this->senderPublicKey, 'base58'),
            $this->timestamp,
            $this->amount,
            $this->fee,
            decode($this->recipient, 'base58'),
            strlen($binaryAttachment),
            $binaryAttachment
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

    /**
     * Set the transaction attachment message.
     *
     * @param string $message
     * @param string $encoding  Encoding the message is in; 'raw', 'hex', 'base58', or 'base64'.
     * @return $this
     */
    public function setAttachment(string $message, string $encoding = 'raw'): self
    {
        $this->attachment = encode(decode($message, $encoding), 'base58');

        return $this;
    }
}
