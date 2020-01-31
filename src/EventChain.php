<?php declare(strict_types=1);

namespace LTO;

use JsonSerializable;
use OutOfBoundsException;
use function sodium_crypto_generichash as blake2b;

/**
 * Live contracts event chain
 */
class EventChain implements JsonSerializable
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
     * Hash of the latest event on the chain.
     * @var string|null
     */
    protected $latest_hash;


    /**
     * Class constructor
     *
     * @param string $id
     * @param string $latestHash
     */
    public function __construct(string $id = null, string $latestHash = null)
    {
        $this->id = $id;
        $this->latest_hash = $latestHash ?: (isset($id) ? $this->getInitialHash() : null);
    }


    /**
     * Generate an 20 byte random nonce for the id.
     *
     * @return string
     * @throws \Exception
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
    public function initFor(Account $account, ?string $nonceSeed = null): void
    {
        if (isset($this->id)) {
            throw new \BadMethodCallException("Chain id already set");
        }
        
        if (!isset($account->sign->publickey)) {
            throw new \InvalidArgumentException("Unable to create new event chain; public sign key unknown");
        }
        
        $this->id = $this->createId(self::CHAIN_ID, $account->sign->publickey, $nonceSeed);
        $this->latest_hash = $this->getInitialHash();
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
        if ($this->events === []) {
            return $this->latest_hash;
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
        $this->latest_hash = null;
        
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
        } catch (\InvalidArgumentException $e) {
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


    /**
     * Create a partial chain starting from the given hash.
     *
     * @param string $hash
     * @return EventChain
     * @throws OutOfBoundsException
     */
    public function getPartialAfter(string $hash): EventChain
    {
        if ($hash === ($this->events === [] ? $this->getLatestHash() : $this->events[0]->previous)) {
            return $this;
        }

        $newEvents = $this->getEventsAfter($hash);

        $partialChain = clone $this;
        $partialChain->events = $newEvents;

        if ($newEvents === []) {
            $partialChain->latest_hash = $this->getLatestHash();
        }

        return $partialChain;
    }

    /**
     * Check if this chain has the genesis event or is empty.
     */
    public function isPartial(): bool
    {
        return count($this->events) > 0
            ? $this->events[0]->previous !== $this->getInitialHash()
            : $this->previous_hash !== null;
    }

    /**
     * Check if the chain has events.
     */
    public function hasEvents(): bool
    {
        return count($this->events) !== 0;
    }

    /**
     * Get a partial chain without any events.
     *
     * @return EventChain
     */
    public function getPartialWithoutEvents(): EventChain
    {
        $partial = $this->withoutEvents();
        $partial->previous_hash = $this->getLatestHash();

        return $partial;
    }

    /**
     * Get all events that follow the specified event.
     *
     * @param string $hash  Event hash. Initial hash will not work.
     * @return Event[]
     * @throws OutOfBoundsException if event for the given hash can't be found.
     */
    protected function getEventsAfter(string $hash): array
    {
        $events = null;

        foreach ($this->events as $event) {
            if ($events !== null) {
                $events[] = $event;
            }

            if ($event->hash === $hash) {
                $events = [];
            }
        }

        if ($events === null) {
            throw new OutOfBoundsException("Event '$hash' not found");
        }

        return $events;
    }

    /**
     * Called upon cloning.
     */
    public function __clone()
    {
        foreach ($this->events as $key => $event) {
            $this->events[$key] = clone $event;
        }
    }

    /**
     * Prepare for JSON serialization.
     *
     * @return \stdClass
     */
    public function jsonSerialize()
    {
        $serializeEvent = static function (Event $event) {
            return $event->jsonSerialize();
        };

        return (object)[
            'id' => $this->id,
            'events' => array_map($serializeEvent, $this->events),
            'latest_hash' => $this->getLatestHash(),
        ];
    }
}
