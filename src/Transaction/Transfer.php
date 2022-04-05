<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Binary;
use LTO\Transaction;
use function LTO\is_valid_address;

/**
 * LTO Transfer transaction.
 */
class Transfer extends Transaction
{
    /** Default transaction fee */
    public const DEFAULT_FEE = 100000000;

    /** Transaction type */
    public const TYPE = 4;

    /** Transaction version */
    public const DEFAULT_VERSION = 3;

    public string $recipient;
    public int $amount;
    public Binary $attachment;


    /**
     * Class constructor.
     *
     * @param int           $amount      Amount of LTO (*10^8)
     * @param string        $recipient   Recipient address (base58 encoded)
     * @param string|Binary $attachment
     */
    public function __construct(string $recipient, int $amount, $attachment = '')
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Invalid amount; should be greater than 0");
        }

        if (!is_valid_address($recipient, 'base58')) {
            throw new \InvalidArgumentException("Invalid recipient address; is it base58 encoded?");
        }

        $this->version = self::DEFAULT_VERSION;
        $this->fee = self::DEFAULT_FEE;

        $this->amount = $amount;
        $this->recipient = $recipient;
        $this->attachment = $attachment instanceof Binary ? $attachment : new Binary($attachment);
    }

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 2:
                $pack = new Pack\TransferV2();
                break;
            case 3:
                $pack = new Pack\TransferV3();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported transfer tx version {$this->version}");
        }

        return $pack($this);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return ['type' => static::TYPE] + parent::jsonSerialize();
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data);
        static::assertType($data, static::TYPE);

        $data['attachment'] = Binary::fromBase58($data['attachment'] ?? '');

        return static::createFromData($data);
    }
}
