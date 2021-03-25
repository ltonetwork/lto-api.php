<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\encode;
use function LTO\is_valid_address;

/**
 * LTO Anchor transaction.
 *
 * Caveat; the transaction schema supports multiple anchors per transaction, but this is disallowed by the consensus
 *   model.
 */
class Anchor extends Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 35000000;

    /** Transaction type */
    public const TYPE = 15;

    /** Transaction version */
    public const VERSION  = 1;

    /** @var string[] */
    public $anchors = [];


    /**
     * Class constructor.
     *
     * @param string|null $hash
     * @param string      $encoding 'raw', 'hex', 'base58', or 'base64'
     */
    public function __construct(?string $hash = null, string $encoding = 'hex')
    {
        $this->fee = self::MINIMUM_FEE;

        if ($hash !== null) {
            $this->addHash($hash, $encoding);
        }
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

        $packed = pack(
            'CCa32n',
            static::TYPE,
            static::VERSION,
            decode($this->senderPublicKey, 'base58'),
            count($this->anchors)
        );

        foreach ($this->anchors as $anchor) {
            $rawHash = decode($anchor, 'base58');
            $packed .= pack('na*', strlen($rawHash), $rawHash);
        }

        $packed .= pack(
            'JJ',
            $this->timestamp,
            $this->fee
        );

        return $packed;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return
            ['type' => static::TYPE, 'version' => static::VERSION] +
            parent::jsonSerialize();
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data);
        static::assertTypeAndVersion($data, static::TYPE, static::VERSION);

        return static::createFromData($data);
    }

    /**
     * Add a hash to the transaction.
     *
     * @param string $hash
     * @param string $encoding 'raw', 'hex', 'base58', or 'base64'
     * @return $this
     */
    public function addHash(string $hash, string $encoding = 'hex'): self
    {
        $this->anchors[] = $encoding === 'base58'
            ? $hash
            : encode(decode($hash, $encoding), 'base58');

        return $this;
    }

    /**
     * Get the anchor hash.
     *
     * @param string $encoding 'raw', 'hex', 'base58', or 'base64'
     * @return string
     */
    public function getHash(string $encoding = 'hex'): string
    {
        if (count($this->anchors) !== 1) {
            throw new \BadMethodCallException("Method 'getHash' can't be used on a multi-anchor tx");
        }

        return $encoding === 'base58'
            ? $this->anchors[0]
            : encode(decode($this->anchors[0], 'base58'), $encoding);
    }

    /**
     * Get anchor hashes for a multi-anchor tx.
     *
     * @param string $encoding 'raw', 'hex', 'base58', or 'base64'
     * @return string[]
     */
    public function getHashes(string $encoding = 'hex'): array
    {
        if ($encoding === 'base58') {
            return $this->anchors;
        }

        $hashes = [];

        foreach ($this->anchors as $anchor) {
            $hashes[] = encode(decode($anchor, 'base58'), $encoding);
        }

        return $hashes;
    }
}
