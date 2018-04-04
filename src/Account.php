<?php

namespace LTO;

use StephenHill\Base58;
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
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string
     */
    public function getAddress($encoding = 'base58')
    {
        return $this->address ? static::encode($this->address, $encoding) : null;
    }
    
    /**
     * Get base58 encoded public sign key
     * 
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string
     */
    public function getPublicSignKey($encoding = 'base58')
    {
        return $this->sign ? static::encode($this->sign->publickey, $encoding) : null;
    }
    
    /**
     * Get base58 encoded public encryption key
     * 
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string
     */
    public function getPublicEncryptKey($encoding = 'base58')
    {
        return $this->encrypt ? static::encode($this->encrypt->publickey, $encoding) : null;
    }
    
    
    /**
     * Create an encoded signature of a message.
     * 
     * @param string $message
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string
     */
    public function sign($message, $encoding = 'base58')
    {
        if (!isset($this->sign->secretkey)) {
            throw new \RuntimeException("Unable to sign message; no secret sign key");
        }
        
        $signature = \sodium\crypto_sign_detached($message, $this->sign->secretkey);
        
        return static::encode($signature, $encoding);
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
     * Verify a signature of a message
     * 
     * @param string $signature
     * @param string $message
     * @param string $encoding   signature encoding 'raw', 'base58' or 'base64'
     * @return boolean
     */
    public function verify($signature, $message, $encoding = 'base58')
    {
        if (!isset($this->sign->publickey)) {
            throw new \RuntimeException("Unable to verify message; no public sign key");
        }
        
        $rawSignature = static::decode($signature, $encoding);
        
        if (strlen($rawSignature) !== \sodium\CRYPTO_SIGN_BYTES) {
            throw new \RuntimeException("Invalid signature length");
        }
        
        if (strlen($this->sign->publickey) !== \sodium\CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new \RuntimeException("Invalid public key length");
        }
        
        return \sodium\crypto_sign_verify_detached($rawSignature, $message, $this->sign->publickey);
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
     * Base58 or base64 encode a string
     * 
     * @param string $string
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string
     */
    protected static function encode($string, $encoding = 'base58')
    {
        if ($encoding === 'base58') {
            $base58 = new Base58();
            $string = $base58->encode($string);
        }
        
        if ($encoding === 'base64') {
            $string = base64_encode($string);
        }

        return $string;
    }
    
    /**
     * Base58 or base64 decode a string
     * 
     * @param string $string
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string
     */
    protected static function decode($string, $encoding = 'base58')
    {
        if ($encoding === 'base58') {
            $base58 = new Base58();
            $string = $base58->decode($string);
        }
        
        if ($encoding === 'base64') {
            $string = base64_decode($string);
        }

        return $string;
    }
}
