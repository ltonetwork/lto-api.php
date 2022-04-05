<?php declare(strict_types=1);

namespace LTO;

use BadMethodCallException;
use JsonSerializable;
use function sodium_crypto_sign_verify_detached as ed25519_verify;

/**
 * Live Contracts Event
 */
class Event implements JsonSerializable
{
    /**
     * Base58 encoded JSON string with the body of the event.
     * @var string
     */
    public $body;

    /**
     * Time when the event was signed.
     * @var int
     */
    public $timestamp;
    
    /**
     * Hash to the previous event
     * @var string
     */
    public $previous;
    
    /**
     * URI of the public key used to sign the event
     * @var string
     */
    public $signkey;
    
    /**
     * Base58 encoded signature of the event
     * @var string
     */
    public $signature;
    
    /**
     * Base58 encoded SHA256 hash of the event
     * @var string
     */
    public $hash;

    /**
     * The original event, in case the event was rebased.
     * @var Event
     */
    public $original;
    
    /**
     * Class constructor
     *
     * @param object|array $body
     * @param string|null  $previous
     */
    public function __construct($body = null, string $previous = null)
    {
        if (isset($body)) {
            $this->body = encode(json_encode($body), 'base58');
            $this->timestamp = time();
        }
        
        $this->previous = $previous;
    }
    
    /**
     * Get the message used for hash and signature
     *
     * @return string
     * @throws BadMethodCallException if called before the body or signkey is set
     */
    public function getMessage(): string
    {
        if (!isset($this->body)) {
            throw new BadMethodCallException("Body unknown");
        }
        
        if (!isset($this->signkey)) {
            throw new BadMethodCallException("First set signkey before creating message");
        }

        $parts = array_merge(
            [
                $this->body,
                $this->timestamp,
                $this->previous,
                $this->signkey
            ],
            $this->original !== null ? [$this->original->hash] : []
        );

        return join("\n", $parts);
    }
    
    /**
     * Get the base58 encoded hash of the event
     *
     * @return string
     */
    public function getHash(): string
    {
        if ($this->hash === null) {
            $this->hash = encode(sha256($this->getMessage()), 'base58');
        }

        return $this->hash;
    }
    
    /**
     * Verify that the signature is valid
     *
     * @return bool
     * @throw BadMethodCallException if signature or signkey is not set
     */
    public function verifySignature(): bool
    {
        if (!isset($this->signature) || !isset($this->signkey)) {
            throw new BadMethodCallException("Signature and/or signkey not set");
        }
        
        $signature = decode($this->signature, 'base58');
        $signkey = decode($this->signkey, 'base58');
        
        return
            strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES &&
            strlen($signkey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES &&
            ed25519_verify($signature, $this->getMessage(), $signkey);
    }

    /**
     * Sign this event
     *
     * @param Account $account
     * @return $this
     * @throws \RuntimeException if account secret sign key is not set
     */
    public function signWith(Account $account)
    {
        $this->signkey = $account->getPublicSignKey();
        $this->signature = $account->sign($this->getMessage())->base58();
        $this->hash = $this->getHash();

        return $this;
    }
    
    /**
     * Add this event to the chain
     *
     * @param EventChain $chain
     * @return $this
     */
    public function addTo(EventChain $chain)
    {
        $chain->add($this);

        return $this;
    }

    /**
     * Prepare for JSON serialization.
     *
     * @return \stdClass
     */
    public function jsonSerialize()
    {
        $data = (object)get_object_vars($this);

        if ($this->original === null) {
            unset($data->original);
        } else {
            $data->original = $this->original->jsonSerialize();
            unset($data->original->body);
        }

        return $data;
    }
}
