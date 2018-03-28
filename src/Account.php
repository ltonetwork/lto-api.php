<?php

namespace LTO;

use LTO\DecryptException;

/**
 * An account (aka wallet)
 */
class Account
{
    /**
     * Account public address
     * @var string
     */
    public $address;
    
    /**
     * Sign keys
     * @var object
     */
    public $sign;
    
    /**
     * Encryption keys
     * @var object
     */
    public $encrypt;
    
    
    /**
     * Get a random nonce
     * @codeCoverageIgnore
     * 
     * @return string
     */
    protected function getNonce()
    {
        return random_bytes(\sodium\CRYPTO_BOX_NONCEBYTES);
    }
    
    
    /**
     * Get base58 encoded address
     * 
     * @return string
     */
    public function getAddress()
    {
        return $this->address ? static::base58($this->address) : null;
    }
    
    /**
     * Get base58 encoded public sign key
     * 
     * @return string
     */
    public function getPublicSignKey()
    {
        return $this->sign ? static::base58($this->sign->publickey) : null;
    }
    
    /**
     * Get base58 encoded public encryption key
     * 
     * @return string
     */
    public function getPublicEncryptKey()
    {
        return $this->encrypt ? static::base58($this->encrypt->publickey) : null;
    }
    
    
    /**
     * Create a base58 encoded signature of a message.
     * 
     * @param string $message
     * @return string
     */
    public function sign($message)
    {
        if (!isset($this->sign->secretkey)) {
            throw new \RuntimeException("Unable to sign message; no secret sign key");
        }
        
        $signature = \sodium\crypto_sign_detached($message, $this->sign->secretkey);
        
        return static::base58($signature);
    }
    
    /**
     * Sign an event
     * 
     * @param Event $event
     * @return $event
     */
    public function signEvent($event)
    {
        $event->signkey = $this->getPublicSignKey();
        $event->signature = $this->sign($event->getMessage());
        $event->hash = $event->getHash();
        
        return $event;
    }
    
    /**
     * Encrypt a message for another account.
     * The nonce is appended.
     * 
     * @param Account $recipient 
     * @param string  $message
     * @return string
     */
    public function encryptFor(Account $recipient, $message)
    {
        if (!isset($this->encrypt->secretkey)) {
            throw new \RuntimeException("Unable to encrypt message; no secret encryption key");
        }
        
        if (!isset($recipient->encrypt->publickey)) {
            throw new \RuntimeException("Unable to encrypt message; no public encryption key for recipient");
        }
        
        $nonce = $this->getNonce();

        $encryptionKey = \sodium\crypto_box_keypair_from_secretkey_and_publickey($this->encrypt->secretkey,
            $recipient->encrypt->publickey);
        
        return \sodium\crypto_box($message, $nonce, $encryptionKey) . $nonce;
    }
    
    /**
     * Decrypt a message from another account.
     * 
     * @param Account $sender 
     * @param string  $cyphertext
     * @return string
     * @throws 
     */
    public function decryptFrom(Account $sender, $cyphertext)
    {
        if (!isset($this->encrypt->secretkey)) {
            throw new \RuntimeException("Unable to decrypt message; no secret encryption key");
        }
        
        if (!isset($sender->encrypt->publickey)) {
            throw new \RuntimeException("Unable to decrypt message; no public encryption key for recipient");
        }
        
        $encryptedMessage = substr($cyphertext, 0, -24);
        $nonce = substr($cyphertext, -24);

        $encryptionKey = \sodium\crypto_box_keypair_from_secretkey_and_publickey($sender->encrypt->secretkey,
            $this->encrypt->publickey);
        
        $message = \sodium\crypto_box_open($encryptedMessage, $nonce, $encryptionKey);
        
        if ($message === false) {
            throw new DecryptException("Failed to decrypt message from " . $sender->getAddress());
        }
        
        return $message;
    }
    
    /**
     * Create a new event chain for this account
     * 
     * @return EventChain
     * @throws \BadMethodCallException
     */
    public function createEventChain()
    {
        $chain = new EventChain();
        $chain->initFor($this);
        
        return $chain;
    }
    
    /**
     * Base58 encode a string
     * 
     * @param string $string
     * @return string
     */
    protected static function base58($string)
    {
        $base58 = new \StephenHill\Base58();
        
        return $base58->encode($string);
    }
}