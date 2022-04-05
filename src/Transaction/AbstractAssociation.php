<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Binary;
use LTO\Transaction;
use function LTO\recode;
use function LTO\is_valid_address;

/**
 * Base class for association and revoke association tx.
 */
abstract class AbstractAssociation extends Transaction
{
    /** Default transaction fee */
    public const DEFAULT_FEE = 100000000;

    /** Transaction version */
    public const DEFAULT_VERSION  = 3;

    public string $recipient;
    public int $associationType;
    public ?Binary $hash;


    /**
     * Class constructor.
     *
     * @param string      $recipient Recipient address (base58 encoded)
     * @param int         $type      Association type
     * @param Binary|null $hash      Association hash
     */
    public function __construct(string $recipient, int $type, ?Binary $hash = null)
    {
        if (!is_valid_address($recipient, 'base58')) {
            throw new \InvalidArgumentException("Invalid recipient address; is it base58 encoded?");
        }

        $this->version = self::DEFAULT_VERSION;
        $this->fee = self::DEFAULT_FEE;

        $this->recipient = $recipient;
        $this->associationType = $type;
        $this->hash = $hash;
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data, ['expire', 'hash']);
        static::assertType($data, static::TYPE);

        if (isset($data['hash']) && is_string($data['hash'])) {
            $data['hash'] = Binary::fromBase58($data['hash']);
        }

        return static::createFromData($data);
    }
}
