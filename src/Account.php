<?php

declare(strict_types=1);

namespace LTO;

/**
 * An account (aka wallet).
 * Used to sign transactions for the public chain and events for the private layer.
 */
class Account
{
    /**
     * @var Cryptography
     */
    protected $cryptography;

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
     * Account constructor.
     *
     * @param Cryptography $cryptography
     */
    public function __construct(Cryptography $cryptography)
    {
        $this->cryptography = $cryptography;
    }

    /**
     * Get cryptography used by this account.
     */
    public function getCryptography(): Cryptography
    {
        return $this->cryptography;
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
     *
     * @throws \RuntimeException if address is not set
     */
    public function getNetwork(): string
    {
        if ($this->address === null) {
            throw new \RuntimeException("Address not set");
        }

        ['network' => $network] = unpack('Cversion/anetwork', $this->address);

        return $network;
    }
    
    
    /**
     * Create an encoded signature of a message.
     *
     * @param string|Binary $message
     * @return Binary
     * @throws \RuntimeException if secret sign key is not set
     */
    public function sign(string $message): Binary
    {
        $rawMessage = $message instanceof Binary ? $message->raw() : $message;

        if (!isset($this->sign->secretkey)) {
            throw new \RuntimeException("Unable to sign message; no secret sign key");
        }
        
        $signature = $this->cryptography->sign($rawMessage, $this->sign->secretkey);

        return new Binary($signature);
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
     * @param string|Binary $message
     * @param Binary        $signature
     * @return boolean
     * @throws \RuntimeException if public sign key is not set
     */
    public function verify($message, Binary $signature): bool
    {
        $rawMessage = $message instanceof Binary ? $message->raw() : $message;

        if (!isset($this->sign->publickey)) {
            throw new \RuntimeException("Unable to verify message; no public sign key");
        }
        
        return $this->cryptography->verify($message, $this->sign->publickey, $signature->raw());
    }
    
    
    /**
     * Encrypt a message for another account.
     * The nonce is appended.
     *
     * @param Account $recipient
     * @param string|Binary  $message
     * @return Binary
     * @throws \RuntimeException if secret encrypt key of sender or public encrypt key of recipient is not set
     */
    public function encryptFor(Account $recipient, $message): Binary
    {
        $rawMessage = $message instanceof Binary ? $message->raw() : $message;

        if (!isset($this->encrypt->secretkey)) {
            throw new \RuntimeException("Unable to encrypt message; no secret encryption key");
        }
        
        if (!isset($recipient->encrypt->publickey)) {
            throw new \RuntimeException("Unable to encrypt message; no public encryption key for recipient");
        }
        
        $cypherText = $this->cryptography->encrypt(
            $rawMessage,
            $this->encrypt->secretkey,
            $recipient->encrypt->publickey
        );

        return new Binary($cypherText);
    }
    
    /**
     * Decrypt a message from another account.
     *
     * @param Account $sender
     * @param Binary  $cypherText
     * @return Binary
     * @throws \RuntimeException if secret encrypt key of recipient or public encrypt key of sender is not set
     * @throws DecryptException if message can't be decrypted
     */
    public function decryptFrom(Account $sender, Binary $cypherText): Binary
    {
        if (!isset($this->encrypt->secretkey)) {
            throw new \RuntimeException("Unable to decrypt message; no secret encryption key");
        }
        
        if (!isset($sender->encrypt->publickey)) {
            throw new \RuntimeException("Unable to decrypt message; no public encryption key for sender");
        }

        $message = $this->cryptography->decrypt(
            $cypherText->raw(),
            $this->encrypt->secretkey,
            $sender->encrypt->publickey
        );

        return new Binary($message);
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
