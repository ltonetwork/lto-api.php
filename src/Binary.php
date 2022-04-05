<?php

declare(strict_types=1);

namespace LTO;

use JsonSerializable;

/**
 * Binary value
 */
class Binary implements JsonSerializable
{
    protected string $binary;

    /**
     * Class constructor
     */
    public function __construct(string $value, string $encoding = 'raw')
    {
        $this->binary = decode($value, $encoding);
    }

    /**
     * Get non-encoded binary
     */
    public function raw(): string
    {
        return $this->binary;
    }

    /**
     * Length of the non-encoded binary
     */
    public function length(): int
    {
        return strlen($this->binary);
    }

    /**
     * Get binary as base58 encoded string
     */
    public function base58(): string
    {
        return encode($this->binary, 'base58');
    }

    /**
     * Get binary as base64 encoded string
     */
    public function base64(): string
    {
        return encode($this->binary, 'base64');
    }

    /**
     * Get binary as hex encoded string
     */
    public function hex(): string
    {
        return encode($this->binary, 'hex');
    }


    /**
     * In JSON use base58 representation
     */
    public function jsonSerialize(): string
    {
        return $this->base58();
    }

    /**
     * Cast object to a string, will return the binary without encoding.
     */
    public function __toString(): string
    {
        return $this->binary;
    }


    /**
     * Create Binary object from non-encoded binary
     */
    public static function fromRaw(string $value): Binary
    {
        return new Binary($value, 'raw');
    }

    /**
     * Create Binary object from base58 encoded value
     */
    public static function fromBase58(string $value): Binary
    {
        return new Binary($value, 'base58');
    }

    /**
     * Create Binary object from base64 encoded value
     */
    public static function fromBase64(string $value): Binary
    {
        return new Binary($value, 'base64');
    }

    /**
     * Create Binary object from hex value
     */
    public static function fromHex(string $value): Binary
    {
        return new Binary($value, 'hex');
    }


    /**
     * Hash the value and return a Binary object.
     * @see \hash
     */
    public static function hash(string $algo, string $data): Binary
    {
        return new Binary(hash($algo, $data, true));
    }
}
