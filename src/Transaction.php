<?php

declare(strict_types=1);

namespace LTO;

use function sodium_crypto_generichash as blake2b;

/**
 * Abstract base class for transactions.
 */
abstract class Transaction implements \JsonSerializable
{
    /** Must be overwritten in child class */
    public const TYPE = 0;

    protected const TYPES = [
        4 => Transaction\Transfer::class,
        8 => Transaction\Lease::class,
        9 => Transaction\CancelLease::class,
        11 => Transaction\MassTransfer::class,
        13 => Transaction\SetScript::class,
        15 => Transaction\Anchor::class,
        16 => Transaction\Association::class,
        17 => Transaction\RevokeAssociation::class,
        18 => Transaction\Sponsorship::class,
        19 => Transaction\CancelSponsorship::class,
    ];


    /** @var string */
    public $id;

    /** @var int */
    public $version;

    /** @var string|null */
    public $sender = null;

    /** @var string */
    public $senderKeyType = 'ed25519';

    /** @var string|null */
    public $senderPublicKey = null;

    /** @var int|null epoch in milliseconds */
    public $timestamp = null;

    /** @var int */
    public $fee;

    /** @var string|null */
    public $sponsor = null;

    /** @var string */
    public $sponsorKeyType = 'ed25519';

    /** @var string|null */
    public $sponsorPublicKey = null;

    /** @var string[] */
    public $proofs = [];

    /** @var int|null */
    public $height;

    /**
     * Get binary representation of the unsigned transaction.
     */
    abstract public function toBinary(): string;

    /**
     * Get the transaction id.
     * Generate it from the binary if the id is unknown.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id ?? base58_encode(blake2b($this->toBinary()));
    }

    /**
     * Sign this transaction.
     *
     * @param Account $account
     * @return $this
     * @throws \RuntimeException if account secret sign key is not set
     */
    public function signWith(Account $account): self
    {
        if ($this->sender === null) {
            $this->sender = $account->getAddress();
            $this->senderPublicKey = $account->getPublicSignKey();
        }

        if ($this->timestamp === null) {
            $this->timestamp = time() * 1000;
        }

        $this->proofs[] = $account->sign($this->toBinary())->base58();

        return $this;
    }

    /**
     * Sponsor this transaction by co-signing it.
     *
     * @param Account $account
     * @return $this
     * @throws \BadMethodCallException if transaction isn't signed
     * @throws \RuntimeException if account secret sign key is not set
     */
    public function sponsorWith(Account $account): self
    {
        if (!$this->isSigned()) {
            throw new \BadMethodCallException("Transaction isn't signed");
        }

        $this->sponsor = $account->getAddress();
        $this->sponsorPublicKey = $account->getPublicSignKey();

        $this->proofs[] = $account->sign($this->toBinary())->base58();

        return $this;
    }

    /**
     * Get the network id based on the sender address.
     */
    public function getNetwork(): string
    {
        if ($this->sender === null) {
            throw new \BadMethodCallException("Sender not set");
        }

        ['network' => $network] = unpack('Cversion/anetwork', decode($this->sender, 'base58'));

        return $network;
    }

    /**
     * Broadcast transaction to a node.
     *
     * @param PublicNode $node
     * @return static
     */
    final public function broadcastTo(PublicNode $node): self
    {
        return $node->broadcast($this);
    }

    /**
     * Is the transaction signed?
     */
    public function isSigned(): bool
    {
        return $this->proofs !== [];
    }


    /**
     * Get data for JSON serialization.
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $data = ['type' => static::TYPE] + array_filter(get_public_properties($this), fn($val) => $val !== null);

        if (!isset($data['sponsor'])) {
            unset($data['sponsorKeyType'], $data['sponsorPublicKey']);
        }

        return $data;
    }

    /**
     * Assert that there are no missing keys in the data.
     *
     * @throws \InvalidArgumentException
     */
    protected static function assertNoMissingKeys(array $data, array $optionalKeys = []): void
    {
        $optionalKeys = array_merge($optionalKeys, ['id', 'height', 'senderKeyType', 'sponsorKeyType']);

        if (!isset($data['sponsor']) && !isset($data['sponsorPublicKey'])) {
            $optionalKeys = array_merge($optionalKeys, ['sponsor', 'sponsorPublicKey']);
        }

        $requiredKeys = array_diff(array_keys(get_class_vars(get_called_class())), $optionalKeys);
        $missingKeys = array_diff($requiredKeys, array_keys($data));

        if ($missingKeys !== []) {
            throw new \InvalidArgumentException("Invalid data, missing keys: " . join(', ', $missingKeys));
        }
    }

    /**
     * Assert that the tx type of the data matches the expected values.
     *
     * @throws \InvalidArgumentException
     */
    protected static function assertType(array $data, int $type): void
    {
        if (isset($data['type']) && (int)$data['type'] !== $type) {
            throw new \InvalidArgumentException("Invalid type {$data['type']}, should be {$type}");
        }
    }

    /**
     * Create a Transaction object from data.
     *
     * @param array $data
     * @return static
     */
    public static function fromData(array $data)
    {
        if (get_called_class() !== __CLASS__) {
            throw new \LogicException(__CLASS__ . " must override the fromData method"); // @codeCoverageIgnore
        }

        if (!isset($data['type'])) {
            throw new \InvalidArgumentException("Invalid data; type field is missing");
        }

        $class = self::TYPES[$data['type']] ?? null;

        if ($class === null) {
            throw new \InvalidArgumentException("Unsupported transaction type {$data['type']}");
        }

        /** @noinspection PhpUndefinedMethodInspection */
        return $class::fromData($data);
    }

    /**
     * Factory method to create and populate a transaction.
     * May only be called from a child class.
     *
     * @param array $data
     * @return object
     * @throws \ReflectionException
     */
    final protected static function createFromData(array $data)
    {
        $reflection = new \ReflectionClass(get_called_class());

        $transaction = $reflection->newInstanceWithoutConstructor();

        foreach ($data as $key => $value) {
            if ($reflection->hasProperty($key) && $reflection->getProperty($key)->isPublic()) {
                $transaction->{$key} = $value;
            }
        }

        return $transaction;
    }
}
