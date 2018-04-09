<?php

namespace LTO;

use \LTO\Account;

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
    public function __construct($body = null, $previous = null)
    {
        if (isset($body)) {
            $base58 = new \StephenHill\Base58();

            $this->body = $base58->encode(json_encode($body));
            $this->timestamp = new \DateTime();
        }
        
        $this->previous = $previous;
    }
    
    /**
     * Get the message used for hash and signature
     * 
     * @return string
     */
    public function getMessage()
    {
        if (!isset($this->body)) {
            throw new \BadMethodCallException("Body unknown");
        }
        
        if (!isset($this->signkey)) {
            throw new \BadMethodCallException("First set signkey before creating message");
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
    public function getHash()
    {
        $hash = hash('sha256', $this->getMessage(), true);

        $base58 = new \StephenHill\Base58();
        return $base58->encode($hash);
    }
    
    /**
     * Verify that the signature is valid
     * 
     * @return boolean
     */
    public function verifySignature()
    {
        if (!isset($this->signature) || !isset($this->signkey)) {
            throw new \BadMethodCallException("Signature and/or signkey not set");
        }
        
        $base58 = new \StephenHill\Base58();
        
        $signature = $base58->decode($this->signature);
        $signkey = $base58->decode($this->signkey);
        
        return strlen($signature) === \sodium\CRYPTO_SIGN_BYTES &&
            strlen($signkey) === \sodium\CRYPTO_SIGN_PUBLICKEYBYTES &&
            \sodium\crypto_sign_verify_detached($signature, $this->getMessage(), $signkey);
    }
    
    /**
     * Sign this event
     * 
     * @param Account $account
     * @return $this
     */
    public function signWith(Account $account)
    {
        return $account->signEvent($this);
    }
}
