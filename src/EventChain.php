<?php declare(strict_types=1);

namespace LTO;

use InvalidArgumentException;

/**
 * Live contracts event chain
 */
class EventChain
{
    const CHAIN_ID = 0x40;
    const RESOURCE_ID = 0x50;

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
     * Generate an 20 byte random nonce for the id.
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
        $nsHashed = sha256(blake2b($ns, '', 32));

        $nonce = isset($nonceSeed) ? sha256($nonceSeed) : $this->getRandomNonce();

        $packed = pack('Ca20a20', $type, $nonce, $nsHashed);
        $chksum = sha256(blake2b($packed));

        $idBinary = pack('Ca20a20a4', $type, $nonce, $nsHashed, $chksum);

        return encode($idBinary, 'base58');
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
        
        $this->id = $this->createId(self::CHAIN_ID, $account->sign->publickey, $nonceSeed);
        $this->latestHash = $this->getInitialHash();
    }

    /**
     * Get the initial hash which is based on the event chain id
     *
     * @return string
     */
    public function getInitialHash(): string
    {
        $rawId = decode($this->id, 'base58');
        
        return encode(sha256($rawId), 'base58');
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

    /**
     * Create a resource id.
     *
     * @param string $nonceSeed  Specify for deterministic id
     * @return string
     */
    public function createResourceId(?string $nonceSeed = null): string
    {
        if (!isset($this->id)) {
            throw new \BadMethodCallException("Chain id not set");
        }

        return $this->createId(self::RESOURCE_ID, decode($this->id, 'base58'), $nonceSeed);
    }

    /**
     * Check if the ID is a valid resource ID for this event chain.
     *
     * @param string $id
     * @return bool
     */
    public function isValidResourceId(string $id): bool
    {
        try {
            $binaryId = decode($id, 'base58');
        } catch (InvalidArgumentException $e) {
            return false;
        }

        if (strlen($binaryId) !== 45) {
            return false;
        }

        $nsHashed = sha256(blake2b(decode($this->id, 'base58')));
        $parts = unpack('Ctype/a20nonce/a20ns/a4checksum', $binaryId);
        $checksum = sha256(blake2b(substr($binaryId, 0, -4)));

        return $parts['type'] === self::RESOURCE_ID
            && $parts['ns'] === substr($nsHashed, 0, 20)
            && $parts['checksum'] === substr($checksum, 0, 4);
    }
}
