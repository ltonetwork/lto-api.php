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
    /** Minimum transaction fee for a transfer transaction in LTO */
    public const MINIMUM_FEE = 35000000;

    /** Transaction type */
    public const TYPE = 15;

    /** Transaction version */
    public const VERSION  = 1;

    /** @var string[] */
    public $anchors = [];


    /**
     * Transfer constructor.
     *
     * @param string $hash
     * @param string $encoding 'raw', 'hex', 'base58', or 'base64'
     */
    public function __construct(string $hash, string $encoding = 'raw')
    {
        $this->anchors[] = encode(decode($hash, $encoding), 'base58');
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

        $packed = pack(
            'CCa32n',
            self::TYPE,
            self::VERSION,
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

        $bytes = array_values(unpack('C*', $packed));

        return $packed;
    }

    /**
     * Get data for JSON serialization.
     */
    public function jsonSerialize()
    {
        return
            ($this->id !== null ? ['id' => $this->id] : []) +
            [
                'type' => self::TYPE,
                'version' => self::VERSION,
                'sender' => $this->sender,
                'senderPublicKey' => $this->senderPublicKey,
                'fee' => $this->fee,
                'timestamp' => $this->timestamp,
                'anchors' => $this->anchors,
                'proofs' => $this->proofs,
            ] +
            ($this->height !== null ? ['height' => $this->height] : []);
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data);

        if (isset($data['type']) && $data['type'] !== self::TYPE) {
            throw new \InvalidArgumentException("Invalid type {$data['type']}, should be " . self::TYPE);
        }

        if (isset($data['version']) && $data['version'] !== self::VERSION) {
            throw new \InvalidArgumentException("Invalid version {$data['version']}, should be " . self::VERSION);
        }

        return static::createFromData($data);
    }

    /**
     * Get the anchor hash.
     *
     * @param string $encoding 'raw', 'hex', 'base58', or 'base64'
     * @return string
     */
    public function getAnchor(string $encoding = 'raw'): string
    {
        return $encoding === 'base58'
            ? $this->anchors[0]
            : encode(decode($this->anchors[0], 'base58'), $encoding);
    }
}
