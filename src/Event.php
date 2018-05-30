<?php

declare(strict_types=1);

namespace LTO;

use LTO\Account;
use LTO\EventChain;
use BadMethodCallException;

/**
 * Live Contracts Event
 */
class Event
{
    /**
     * Base58 encoded JSON string with the body of the event.
     * 
     * @var string
     */
    public $body;

    /**
     * Time when the event was signed.
     * 
     * @var int
     */
    public $timestamp;
    
    /**
     * Hash to the previous event
     * 
     * @var string
     */
    public $previous;
    
    /**
     * URI of the public key used to sign the event
     * 
     * @var string
     */
    public $signkey;
    
    /**
     * Base58 encoded signature of the event
     * 
     * @var string
     */
    public $signature;
    
    /**
     * Base58 encoded SHA256 hash of the event
     * 
     * @var string
     */
    public $hash;
    
    
    /**
     * Class constructor
     * 
     * @param object|array $body
     * @param string       $previous
     */
    public function __construct($body = null, string $previous = null)
    {
        if (isset($body)) {
            $this->body = \Encoding::encode(json_encode($body));
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
        
        $message = join("\n", [
            $this->body,
            $this->timestamp,
            $this->previous,
            $this->signkey
        ]);
        
        return $message;
    }
    
    /**
     * Get the base58 encoded hash of the event
     * 
     * @return string
     */
    public function getHash(): string
    {
        $hash = hash('sha256', $this->getMessage(), true);

        return \Encoding::encode($hash);
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
        
        $signature = \Encoding::decode($this->signature);
        $signkey = \Encoding::decode($this->signkey);
        
        return strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES &&
            strlen($signkey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES &&
            sodium_crypto_sign_verify_detached($signature, $this->getMessage(), $signkey);
    }

    /**
     * Get the version a resource might be given when defined through this event.
     *
     * @return string
     */
    public function getResourceVersion()
    {
        $rawHash = hash('sha256', $this->body, true);
        $hash = \Encoding::encode($rawHash);

        return substr($hash, 0, 8);
    }

    /**
     * Sign this event
     * 
     * @param Account $account
     * @return $this
     */
    public function signWith(Account $account): self
    {
        return $account->signEvent($this);
    }
    
    /**
     * Add this event to the chain
     * 
     * @param EventChain $chain
     * @return $this
     */
    public function addTo(EventChain $chain): self
    {
        return $chain->add($this);
    }
}
