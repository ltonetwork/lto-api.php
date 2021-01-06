<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\encode;
use function LTO\is_valid_address;

/**
 * LTO transaction to invoke an association.
 */
class Association extends Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 100000000;

    /** Transaction type */
    public const TYPE = 16;

    /** Transaction version */
    public const VERSION  = 1;

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

        $this->party = $party;
        $this->associationType = $type;
        $this->hash = encode(decode($hash, $encoding), 'base58');

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

        $rawHash = $this->hash !== null
            ? decode($this->hash, 'base58')
            : '';

        $packed = pack(
            'CCaa32a26NCa*JJ',
            self::TYPE,
            self::VERSION,
            $this->getNetwork(),
            decode($this->senderPublicKey, 'base58'),
            decode($this->party, 'base58'),
            $this->associationType,
            strlen($rawHash),
            $rawHash,
            $this->timestamp,
            $this->fee
        );

        $unpacked = array_values(unpack('C*', $packed));

        return $packed;
    }

    /**
     * Get data for JSON serialization.
     */
    public function jsonSerialize()
    {
        return
            ['type' => self::TYPE, 'version' => self::VERSION] +
            parent::jsonSerialize();
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data);
        static::assertTypeAndVersion($data, self::TYPE, self::VERSION);

        return static::createFromData($data);
    }
}
