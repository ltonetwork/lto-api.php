<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;

/**
 * LTO transaction to cancel leasing.
 */
class CancelLease extends Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 100000000;

    /** Transaction type */
    public const TYPE = 9;

    /** Transaction version */
    public const DEFAULT_VERSION = 2;

    /** @var string */
    public $leaseId;

    /** @var Lease */
    public $lease;

    /**
     * Class constructor.
     *
     * @param string $leaseId   Transaction ID of the lease transaction (base58 encoded)
     */
    public function __construct(string $leaseId)
    {
        $this->version = self::DEFAULT_VERSION;
        $this->fee = self::MINIMUM_FEE;

        $this->leaseId = $leaseId;
    }

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 2:
                $pack = new Pack\CancelLeaseV2();
                break;
            case 3:
                $pack = new Pack\CancelLeaseV3();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported cancel lease tx version {$this->version}");
        }

        return $pack($this);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        $data = ['chainId' => ord($this->getNetwork())] + parent::jsonSerialize();

        if ($this->lease !== null) {
            $data['lease'] = $this->lease->jsonSerialize();
        } else {
            unset($data['lease']);
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data, ['lease']);
        static::assertType($data, static::TYPE);

        if (isset($data['lease'])) {
            $data['lease'] = Lease::fromData($data['lease']);
        }

        return static::createFromData($data);
    }
}
