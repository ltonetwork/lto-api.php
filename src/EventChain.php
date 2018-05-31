<?php

declare(strict_types=1);

namespace LTO;

use LTO\Event;
use LTO\Account; 
use kornrunner\Keccak;
use LTO\Encoding;

/**
 * Live contracts event chain
 */
class EventChain
{
    const ADDRESS_VERSION = 0x40;
    const PROJECTION_ADDRESS_VERSION = 0x50;

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
    public function __construct(string $id = null, string $latestHash = null)
    {
        $this->id = $id;
        $this->latestHash = $latestHash ?: (isset($id) ? $this->getInitialHash() : null);
    }

    
    /**
     * Generate an 20 byte random nonce for the id
     * 
     * @return string
     */
    protected function getRandomNonce(): string
    {
        return random_bytes(20);
    }

    /**
     * Create an id.
     *
     * @param int    $type
     * @param string $ns         Namespace
     * @param string $nonceSeed  Specify for deterministic id
     * @return string  Base58 encoded id
     */
    protected function createId(int $type, string $ns, ?string $nonceSeed = null): string
    {
        $nsHashed = Keccak::hash(sodium_crypto_generichash($ns, '', 32), 256, true);

        $nonce = isset($nonceSeed) ? hash('sha256', $nonceSeed, true) : $this->getRandomNonce();

        $packed = pack('Ca20a20', $type, $nonce, $nsHashed);
        $chksum = Keccak::hash(sodium_crypto_generichash($packed), 256, true);

        $idBinary = pack('Ca20a20a4', $type, $nonce, $nsHashed, $chksum);

        return Encoding::encode($idBinary);
    }

    /**
     * Initialize a new event chain
     * 
     * @param Account $account
     * @param string  $nonceSeed  Specify for deterministic id
     */
    public function initFor(Account $account, ?string $nonceSeed = null)
    {
        if (isset($this->id)) {
            throw new \BadMethodCallException("Chain id already set");
        }
        
        if (!isset($account->sign->publickey)) {
            throw new \InvalidArgumentException("Unable to create new event chain; public sign key unknown");
        }
        
        $this->id = $this->createId(self::ADDRESS_VERSION, $account->sign->publickey, $nonceSeed);
        $this->latestHash = $this->getInitialHash();
    }

    /**
     * Create a projection id.
     *
     * @param string $nonceSeed  Specify for deterministic id
     * @return string
     */
    public function createProjectionId(?string $nonceSeed = null): string
    {
        if (!isset($this->id)) {
            throw new \BadMethodCallException("Chain id not set");
        }

        return $this->createId(self::PROJECTION_ADDRESS_VERSION, $this->id, $nonceSeed);
    }

    /**
     * Validate if the ID is a valid projection ID for this event chain.
     *
     * @param string $projectionId
     * @return bool
     */
    public function isValidProjectionId(string $projectionId): bool
    {
        try {
            $binaryId = Encoding::decode($projectionId);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        if (strlen($binaryId) !== 45) {
            return false;
        }

        $nsHashed = Keccak::hash(sodium_crypto_generichash($this->id, '', 32), 256, true);

        $parts = unpack('Ctype/a20nonce/a20ns/a4checksum', $binaryId);
        $checksum = Keccak::hash(sodium_crypto_generichash(substr($binaryId, 0, -4)), 256, true);

        return $parts['type'] === self::PROJECTION_ADDRESS_VERSION
            && $parts['ns'] === substr($nsHashed, 0, 20)
            && $parts['checksum'] === substr($checksum, 0, 4);
    }

    /**
     * Get the initial hash which is based on the event chain id
     *
     * @return string
     */
    public function getInitialHash(): string
    {
        $rawId = Encoding::decode($this->id);
        
        return Encoding::encode(hash('sha256', $rawId, true));
    }
    
    /**
     * Get the latest hash.
     * Expecting a new event to use this as previous property.
     * 
     * @return string|null
     */
    public function getLatestHash(): ?string
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
    public function add(Event $event): Event
    {
        $event->previous = $this->getLatestHash();
        
        $this->events[] = $event;
        $this->latestHash = null;
        
        return $event;
    }
}
