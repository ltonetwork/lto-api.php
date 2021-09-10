<?php

declare(strict_types=1);

namespace LTO;

use function sodium_crypto_sign_detached as ed25519_sign;
use function sodium_crypto_sign_verify_detached as ed25519_verify;
use function sodium_crypto_box_keypair_from_secretkey_and_publickey as x25519_keypair;
use function sodium_crypto_box as x25519_encrypt;
use function sodium_crypto_box_open as x25519_decrypt;

/**
 * An account (aka wallet)
 */
class Account
{
    /**
     * Account public address
     * @var string|null
     */
    public $address;
    
    /**
     * Sign keys
     * @var \stdClass|null
     */
    public $sign;
    
    /**
     * Encryption keys
     * @var \stdClass|null
     */
    public $encrypt;
    
    
    /**
     * Get a random nonce.
     * @codeCoverageIgnore
     *
     * @return string
     */
    protected function getNonce()
    {
        return random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
    }
    
    
    /**
     * Get base58 encoded address
     *
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string|null
     */
    public function getAddress(string $encoding = 'base58'): ?string
    {
        return $this->address !== null ? encode($this->address, $encoding) : null;
    }
    
    /**
     * Get base58 encoded public sign key
     *
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string|null
     */
    public function getPublicSignKey(string $encoding = 'base58'): ?string
    {
        return $this->sign !== null ? encode($this->sign->publickey, $encoding) : null;
    }
    
    /**
     * Get base58 encoded public encryption key
     *
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string|null
     */
    public function getPublicEncryptKey(string $encoding = 'base58'): ?string
    {
        return $this->encrypt !== null ? encode($this->encrypt->publickey, $encoding) : null;
    }

    /**
     * Get network chain id.
     */
    public function getNetwork(): ?string
    {
        if ($this->address === null) {
            return null;
        }

        ['network' => $network] = unpack('Cversion/anetwork', $this->address);

        return $network;
    }
    
    
    /**
     * Create an encoded signature of a message.
     *
     * @param string $message
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string
     * @throws \RuntimeException if secret sign key is not set
     */
    public function sign(string $message, string $encoding = 'base58'): string
    {
        if (!isset($this->sign->secretkey)) {
            throw new \RuntimeException("Unable to sign message; no secret sign key");
        }
        
        $signature = ed25519_sign($message, $this->sign->secretkey);
        
        return encode($signature, $encoding);
    }
    
    /**
     * Sign an event.
     *
     * @param Event $event
     * @return Event
     * @throws \RuntimeException if secret sign key is not set
     */
    final public function signEvent(Event $event): Event
    {
        return $event->signWith($this);
    }

    /**
     * Sign a transaction.
     *
     * @template T
     * @param Transaction&T $transaction
     * @return Transaction&T
     */
    final public function signTransaction(Transaction $transaction): Transaction
    {
        return $transaction->signWith($this);
    }

    /**
     * Sponsor a transaction.
     *
     * @template T
     * @param Transaction&T $transaction
     * @return Transaction&T
     */
    final public function sponsorTransaction(Transaction $transaction): Transaction
    {
        return $transaction->sponsorWith($this);
    }

    /**
     * Verify a signature of a message
     *
     * @param string $signature
     * @param string $message
     * @param string $encoding   signature encoding 'raw', 'base58' or 'base64'
     * @return boolean
     * @throws \RuntimeException if public sign key is not set
     */
    public function verify(string $signature, string $message, string $encoding = 'base58'): bool
    {
        if (!isset($this->sign->publickey)) {
            throw new \RuntimeException("Unable to verify message; no public sign key");
        }
        
        $rawSignature = decode($signature, $encoding);
        
        return strlen($rawSignature) === SODIUM_CRYPTO_SIGN_BYTES &&
            strlen($this->sign->publickey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES &&
            ed25519_verify($rawSignature, $message, $this->sign->publickey);
    }
    
    
    /**
     * Encrypt a message for another account.
     * The nonce is appended.
     *
     * @param Account $recipient
     * @param string  $message
     * @return string
     * @throws \RuntimeException if secret encrypt key of sender or public encrypt key of recipient is not set
     */
    public function encryptFor(Account $recipient, string $message): string
    {
        if (!isset($this->encrypt->secretkey)) {
            throw new \RuntimeException("Unable to encrypt message; no secret encryption key");
        }
        
        if (!isset($recipient->encrypt->publickey)) {
            throw new \RuntimeException("Unable to encrypt message; no public encryption key for recipient");
        }
        
        $nonce = $this->getNonce();
        $encryptionKey = x25519_keypair($this->encrypt->secretkey, $recipient->encrypt->publickey);
        
        return x25519_encrypt($message, $nonce, $encryptionKey) . $nonce;
    }
    
    /**
     * Decrypt a message from another account.
     *
     * @param Account $sender
     * @param string  $cyphertext
     * @return string
     * @throws \RuntimeException if secret encrypt key of recipient or public encrypt key of sender is not set
     * @throws DecryptException if message can't be decrypted
     */
    public function decryptFrom(Account $sender, string $cyphertext): string
    {
        if (!isset($this->encrypt->secretkey)) {
            throw new \RuntimeException("Unable to decrypt message; no secret encryption key");
        }
        
        if (!isset($sender->encrypt->publickey)) {
            throw new \RuntimeException("Unable to decrypt message; no public encryption key for sender");
        }
        
        $encryptedMessage = substr($cyphertext, 0, -24);
        $nonce = substr($cyphertext, -24);

        $encryptionKey = x25519_keypair($this->encrypt->secretkey, $sender->encrypt->publickey);
        
        $message = x25519_decrypt($encryptedMessage, $nonce, $encryptionKey);
        
        if ($message === false) {
            throw new DecryptException("Failed to decrypt message from " . $sender->getAddress());
        }
        
        return $message;
    }
    
    /**
     * Create a new event chain for this account
     *
     * @param mixed $nonceSeed  Seed the nonce, rather than using a random nonce.
     * @return EventChain
     * @throws \BadMethodCallException
     */
    public function createEventChain($nonceSeed = null): EventChain
    {
        $chain = new EventChain();
        $chain->initFor($this, isset($nonceSeed) ? (string)$nonceSeed : null);
        
        return $chain;
    }
}
