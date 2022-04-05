<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\UnsupportedFeatureException;

/**
 * LTO transaction to invoke an association.
 */
class Association extends AbstractAssociation
{
    /** Transaction type */
    public const TYPE = 16;

    /** Epoch in milliseconds */
    public ?int $expire = null;


    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 1:
                $pack = new Pack\AssociationV1();
                break;
            case 3:
                $pack = new Pack\AssociationV3();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported association tx version $this->version");
        }

        return $pack($this);
    }

    /**
     * Set expiry date.
     *
     * @param int|\DateTimeInterface|null $time  epoch in milliseconds
     * @return $this
     */
    public function expires($time): self
    {
        if (!is_int($time) && $time !== null && !($time instanceof \DateTimeInterface)) {
            throw new \InvalidArgumentException("Time should be an int, DateTime, or null");
        }

        if ($this->version < 3 && $time !== null) {
            throw new UnsupportedFeatureException(
                "Association expiry isn't supported for association tx v{$this->version}. At least v3 is required"
            );
        }

        $this->expire = $time instanceof \DateTimeInterface
            ? $time->getTimestamp() * 1000
            : $time;

        return $this;
    }
}
