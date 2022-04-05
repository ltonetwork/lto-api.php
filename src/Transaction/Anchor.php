<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Binary;
use LTO\Transaction;

/**
 * LTO Anchor transaction.
 */
class Anchor extends Transaction
{
    /** Base transaction fee */
    public const BASE_FEE = 25000000;

    /** Variable transaction fee */
    public const VAR_FEE = 10000000;

    /** Transaction type */
    public const TYPE = 15;

    /** Transaction version */
    public const DEFAULT_VERSION  = 3;

    /** @var Binary[] */
    public array $anchors = [];


    /**
     * Class constructor.
     */
    public function __construct(Binary ...$anchors)
    {
        $this->version = static::DEFAULT_VERSION;
        $this->fee = self::BASE_FEE + count($anchors) * self::VAR_FEE;

        $this->anchors = $anchors;
    }

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 1:
                $pack = new Pack\AnchorV1();
                break;
            case 3:
                $pack = new Pack\AnchorV3();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported anchor tx version {$this->version}");
        }

        return $pack($this);
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data);
        static::assertType($data, static::TYPE);

        if (isset($data['anchors'])) {
            $data['anchors'] = array_map(fn($hash) => Binary::fromBase58($hash), $data['anchors']);
        }

        return static::createFromData($data);
    }
}
