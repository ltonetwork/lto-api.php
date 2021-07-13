<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\is_valid_address;

/**
 * LTO transaction to sponsor an account.
 */
abstract class AbstractSponsorship extends Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 0;

    /** Transaction version */
    public const DEFAULT_VERSION  = 1;

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

        $this->version = self::DEFAULT_VERSION;
        $this->fee = self::MINIMUM_FEE;

        $this->recipient = $recipient;
    }

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 1:
                $pack = new Pack\SponsorshipV1();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported sponsor tx version {$this->version}");
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
