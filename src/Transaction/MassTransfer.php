<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\encode;
use function LTO\is_valid_address;

/**
 * LTO Mass transfer transaction.
 */
class MassTransfer extends Transaction
{
    /** Base transaction fee */
    public const BASE_FEE = 100000000;

    /** Transaction fee per transfer */
    public const ITEM_FEE = 10000000;

    /** Transaction type */
    public const TYPE = 11;

    /** Transaction version */
    public const VERSION  = 1;

    /**
     * @var array<array{amount:int,recipient:string}>
     */
    public $transfers = [];

    /** @var string */
    public $attachment = '';


    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->fee = self::BASE_FEE;
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

        $packed = pack(
            'CCa32n',
            static::TYPE,
            static::VERSION,
            decode($this->senderPublicKey, 'base58'),
            count($this->transfers)
        );

        foreach ($this->transfers as $transfer) {
            $packed .= pack(
                'a26J',
                decode($transfer['recipient'], 'base58'),
                $transfer['amount']
            );
        }

        $packed .= pack(
            'JJna*',
            $this->timestamp,
            $this->fee,
            strlen($binaryAttachment),
            $binaryAttachment
        );

        return $packed;
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
     * Add a transfer
     *
     * @param string $recipient   Recipient address (base58 encoded)
     * @param int    $amount      Amount of LTO (*10^8)
     * @return $this
     */
    public function addTransfer(string $recipient, int $amount): self
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Invalid amount; should be greater than 0");
        }

        if (!is_valid_address($recipient, 'base58')) {
            throw new \InvalidArgumentException("Invalid recipient address; is it base58 encoded?");
        }

        $this->transfers[] = [
            'amount' => $amount,
            'recipient' => $recipient,
        ];

        $this->fee += self::ITEM_FEE;

        return $this;
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
