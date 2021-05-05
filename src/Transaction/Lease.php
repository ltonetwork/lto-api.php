<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\is_valid_address;

/**
 * LTO transaction to start leasing.
 */
class Lease extends Transaction
{
    /** Default transaction fee */
    public const DEFAULT_FEE = 100000000;

    /** Transaction type */
    public const TYPE = 8;

    /** Transaction version */
    public const DEFAULT_VERSION = 3;

    public string $recipient;
    public int $amount;

    /**
     * Class constructor.
     *
     * @param string $recipient   Recipient address (base58 encoded)
     * @param int    $amount      Amount of LTO (*10^8)
     */
    public function __construct(string $recipient, int $amount)
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Invalid amount; should be greater than 0");
        }

        if (!is_valid_address($recipient, 'base58')) {
            throw new \InvalidArgumentException("Invalid recipient address; is it base58 encoded?");
        }

        $this->version = self::DEFAULT_VERSION;
        $this->fee = self::DEFAULT_FEE;

        $this->recipient = $recipient;
        $this->amount = $amount;
    }

    /**
     * Create a cancel lease tx for this lease.
     *
     * @return CancelLease
     */
    public function cancel(): CancelLease
    {
        $tx = new CancelLease($this->getId());
        $tx->lease = $this;

        return $tx;
    }

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 2:
                $pack = new Pack\LeaseV2();
                break;
            case 3:
                $pack = new Pack\LeaseV3();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported lease tx version {$this->version}");
        }

        return $pack($this);
    }

    /**
     * Create a Cancel Lease transaction for this lease.
     */
    public function cancel(): CancelLease
    {
        return new CancelLease($this->getId());
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

        return static::createFromData($data);
    }
}
