<?php

declare(strict_types=1);

namespace LTO\Transaction;

/**
 * LTO transaction to invoke an association.
 */
class Association extends AbstractAssociation
{
    /** Transaction type */
    public const TYPE = 16;

    /** @var int|null epoch in milliseconds */
    public $expire = null;

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

        $this->expire = $time instanceof \DateTimeInterface
            ? $time->getTimestamp() * 1000
            : $time;

        return $this;
    }
}
