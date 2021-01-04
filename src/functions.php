<?php declare(strict_types=1);

namespace LTO;

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
        $string = @base58_decode($string);
    }

    if ($encoding === 'base64') {
        $string = @base64_decode($string, true);
    }

    if ($string === false) {
        $err = error_get_last();
        throw new \InvalidArgumentException("Failed to decode from '$encoding'. " . ($err['message'] ?? ''));
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
 * Check if address is a valid LTO Network address.
 *
 * @param string $address
 * @param string $encoding  'raw', 'base58' or 'base64'
 * @return bool
 */
function is_valid_address(string $address, string $encoding): bool
{
    if ($encoding === 'base58' && !preg_match('/^[1-9A-HJ-NP-Za-km-z]+$/', $address)) {
        return false;
    }

    if ($encoding === 'base64' && !preg_match('/^[A-Za-z0-9+/]+={0,2}$/', $address)) {
        return false;
    }

    return strlen(decode($address, $encoding)) === 26;
}
