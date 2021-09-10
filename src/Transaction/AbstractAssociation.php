<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\encode;
use function LTO\is_valid_address;

/**
 * Base class for association and revoke association tx.
 */
abstract class AbstractAssociation extends Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 100000000;

    /** Transaction version */
    public const DEFAULT_VERSION  = 1;

    /** @var string */
    public $recipient;

    /** @var int */
    public $associationType;

    /** @var string */
    public $hash;


    /**
     * Class constructor.
     *
     * @param string $recipient Recipient address (base58 encoded)
     * @param int    $type      Association type
     * @param string $hash      Association hash
     * @param string $encoding  Hash encoding 'raw', 'hex', 'base58', or 'base64'
     */
    public function __construct(string $recipient, int $type, string $hash = '', string $encoding = 'hex')
    {
        if (!is_valid_address($recipient, 'base58')) {
            throw new \InvalidArgumentException("Invalid recipient address; is it base58 encoded?");
        }

        $this->version = self::DEFAULT_VERSION;
        $this->fee = self::MINIMUM_FEE;

        $this->recipient = $recipient;
        $this->associationType = $type;
        $this->hash = encode(decode($hash, $encoding), 'base58');
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data, ['expire', 'hash']);
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
