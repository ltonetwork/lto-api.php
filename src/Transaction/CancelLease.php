<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\is_valid_address;

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
    public const VERSION  = 2;

    /** @var string */
    public $leaseId;

    /** @var Lease|null */
    public $lease;

    /**
     * Class constructor.
     *
     * @param string $leaseId   Transaction ID of the lease transaction (base58 encoded)
     */
    public function __construct(string $leaseId)
    {
        $this->leaseId = $leaseId;
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
            'CCaa32JJa32',
            static::TYPE,
            static::VERSION,
            $this->getNetwork(),
            decode($this->senderPublicKey, 'base58'),
            $this->fee,
            $this->timestamp,
            decode($this->leaseId, 'base58')
        );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        $data =
            ['type' => static::TYPE, 'version' => static::VERSION] +
            ['chainId' => ord($this->getNetwork())] +
            parent::jsonSerialize();

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
        static::assertNoMissingKeys($data, ['id', 'height', 'lease']);
        static::assertTypeAndVersion($data, static::TYPE, static::VERSION);

        if (isset($data['lease'])) {
            $data['lease'] = Lease::fromData($data['lease']);
        }

        return static::createFromData($data);
    }
}
