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
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 100000000;

    /** Transaction type */
    public const TYPE = 8;

    /** Transaction version */
    public const DEFAULT_VERSION  = 2;

    /** @var int */
    public $amount;

    /** @var string */
    public $recipient;


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
        $this->fee = self::MINIMUM_FEE;

        $this->amount = $amount;
        $this->recipient = $recipient;
    }

    /**
     * Create a cancel lease tx for this lease.
     *
     * @return CancelLease
     */
    public function cancel(): CancelLease
    {
        return new CancelLease($this->getId());
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
            default:
                throw new \UnexpectedValueException("Unsupported lease tx version {$this->version}");
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

        return static::createFromData($data);
    }
}
