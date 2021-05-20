<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\encode;
use function LTO\is_valid_address;

/**
 * LTO transaction to revoke an association.
 */
class RevokeAssociation extends Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 100000000;

    /** Transaction type */
    public const TYPE = 17;

    /** Transaction version */
    public const DEFAULT_VERSION  = 1;

    /** @var string */
    public $party;

    /** @var int */
    public $associationType;

    /** @var string */
    public $hash;


    /**
     * Class constructor.
     *
     * @param string $party     Recipient address (base58 encoded)
     * @param int    $type      Association type
     * @param string $hash      Association hash
     * @param string $encoding  'raw', 'hex', 'base58', or 'base64'
     */
    public function __construct(string $party, int $type, string $hash = '', string $encoding = 'hex')
    {
        if (!is_valid_address($party, 'base58')) {
            throw new \InvalidArgumentException("Invalid party address; is it base58 encoded?");
        }

        $this->version = self::DEFAULT_VERSION;
        $this->fee = self::MINIMUM_FEE;

        $this->party = $party;
        $this->associationType = $type;
        $this->hash = encode(decode($hash, $encoding), 'base58');
    }

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 1:
                $pack = new Pack\AssociationV1();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported revoke association tx version {$this->version}");
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
        static::assertNoMissingKeys($data, ['id', 'height', 'hash']);
        static::assertType($data, static::TYPE);

        return static::createFromData($data);
    }

    /**
     * Get the anchor hash.
     *
     * @param string $encoding 'raw', 'hex', 'base58', or 'base64'
     * @return string
     */
    public function getHash(string $encoding = 'hex'): string
    {
        return $encoding === 'base58'
            ? $this->hash
            : encode(decode($this->hash, 'base58'), $encoding);
    }
}
