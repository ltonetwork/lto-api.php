<?php declare(strict_types=1);

namespace LTO;

use InvalidArgumentException;

/**
 * Base58 or base64 encode a string
 *
 * @param string $string
 * @param string $encoding  'raw', 'base58' or 'base64'
 * @return string
 */
function encode(string $string, string $encoding): string
{
    if ($encoding === 'base58') {
        $string = base58_encode($string);
    }

    if ($encoding === 'base64') {
        $string = base64_encode($string);
    }

    if ($string === false) {
        throw new \InvalidArgumentException("Failed to encode to '$encoding'");
    }

    return $string;
}

/**
 * Base58 or base64 decode a string
 *
 * @param string $string
 * @param string $encoding  'raw', 'base58' or 'base64'
 * @return string
 */
function decode(string $string, string $encoding): string
{
    if ($encoding === 'base58') {
        $string = base58_decode($string);
    }

    if ($encoding === 'base64') {
        $string = base64_decode($string);
    }

    if ($string === false) {
        throw new \InvalidArgumentException("Failed to decode from '$encoding'");
    }

    return $string;
}

/**
 * Create a raw SHA-256 hash of the input.
 *
 * @param string $input
 * @return string
 */
function sha256(string $input): string
{
    return hash('sha256', $input, true);
}

/**
 * Create a raw Blake2b hash of the input.
 *
 * @param string $input
 * @return string
 */
function blake2b(string $input): string
{
    return sodium_crypto_generichash($input);
}
