<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\is_valid_address;

class Transfer extends Transaction
{
    /** Minimum transaction fee for a transfer transaction in LTO */
    public const MINIMUM_FEE = 100000000;

    /** Transaction type */
    public const TYPE = 4;

    /** Transaction version */
    public const VERSION  = 2;

    /** @var int */
    public $amount;

    /** @var int */
    public $fee = self::MINIMUM_FEE;

    /** @var string */
    public $recipient;

    /** @var string */
    public $attachment = '';


    /**
     * Transfer constructor.
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
            'CCa32JJJa26Sa*',
            self::TYPE,
            self::VERSION,
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
     * Get data for JSON serialization.
     */
    public function jsonSerialize()
    {
        return
            ($this->id !== null ? ['id' => $this->id] : []) +
            [
                'type' => self::TYPE,
                'version' => self::VERSION,
                'sender' => $this->sender,
                'senderPublicKey' => $this->senderPublicKey,
                'fee' => $this->fee,
                'timestamp' => $this->timestamp,
                'amount' => $this->amount,
                'recipient' => $this->recipient,
                'attachment' => $this->attachment,
                'proofs' => $this->proofs,
            ] +
            ($this->height !== null ? ['height' => $this->height] : []);
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data);

        if (isset($data['type']) && $data['type'] !== self::TYPE) {
            throw new \InvalidArgumentException("Invalid type {$data['type']}, should be " . self::TYPE);
        }

        if (isset($data['version']) && $data['version'] !== self::VERSION) {
            throw new \InvalidArgumentException("Invalid version {$data['version']}, should be " . self::VERSION);
        }

        return static::createFromData($data);
    }
}
