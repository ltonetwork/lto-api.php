<?php

namespace LTO;

use LTO\Event;
use LTO\Account; 
use LTO\Keccak;

/**
 * Live contracts event chain
 */
class EventChain
{
    const ADDRESS_VERSION = 0x40;
    
    /**
     * Unique identifier
     * @var string
     */
    public $id;
    
    /**
     * List of event
     * @var Event[]
     */
    public $events = [];

    /**
     * Hash of the latest event on the chain
     * @var string
     */
    protected $latestHash;
    
    
    /**
     * Class constructor
     * 
     * @param string $id
     * @param string $latestHash
     */
    public function __construct($id = null, $latestHash = null)
    {
        $this->id = $id;
        $this->latestHash = $latestHash ?: (isset($id) ? $this->getInitialHash() : null);
    }

    
    /**
     * Generate an 20 byte random nonce for the id
     * 
     * @return string
     */
    protected function getRandomNonce()
    {
        return random_bytes(20);
    }
    
    /**
     * Initialize a new event chain
     * 
     * @param Account $account
     * @param string  $nonceSeed
     */
    public function initFor(Account $account, $nonceSeed = null)
    {
        if (isset($this->id)) {
            throw new \BadMethodCallException("Chain id already set");
        }
        
        if (!isset($account->sign->publickey)) {
            throw new \InvalidArgumentException("Unable to create new event chain; public sign key unknown");
        }
        
        $signkey = $account->sign->publickey;
        $signkeyHashed = Keccak::hash(\sodium\crypto_generichash($signkey, null, 32), 256, true);
        
        $nonce = isset($nonceSeed) ? hash('sha256', $nonceSeed, true) : $this->getRandomNonce();
        
        $packed = pack('Ca20a20', self::ADDRESS_VERSION, $nonce, $signkeyHashed);
        $chksum = Keccak::hash(\sodium\crypto_generichash($packed), 256, true);
        
        $idBinary = pack('Ca20a20a4', self::ADDRESS_VERSION, $nonce, $signkeyHashed, $chksum);
        
        $base58 = new \StephenHill\Base58();
        
        $this->id = $base58->encode($idBinary);
        $this->latestHash = $this->getInitialHash();
    }

    
    /**
     * Get the initial hash which is based on the event chain id
     */
    public function getInitialHash()
    {
        $base58 = new \StephenHill\Base58();
        
        $rawId = $base58->decode($this->id);
        
        return $base58->encode(hash('sha256', $rawId, true));
    }
    
    /**
     * Get the latest hash.
     * Expecting a new event to use this as previous property.
     * 
     * @return string
     */
    public function getLatestHash()
    {
        if (empty($this->events)) {
            return $this->latestHash;
        }

        $lastEvent = end($this->events);
        return $lastEvent->getHash();
    }
    
    /**
     * Add a new event
     * 
     * @param Event $event
     * @return Event
     */
    public function add(Event $event)
    {
        $event->previous = $this->getLatestHash();
        
        $this->events[] = $event;
        $this->latestHash = null;
        
        return $event;
    }
}
