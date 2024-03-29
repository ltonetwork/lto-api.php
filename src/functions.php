<?php

declare(strict_types=1);

namespace LTO;

/**
 * Base58 or base64 encode a string
 *
 * @param string $string
 * @param string $encoding  'raw', 'hex', 'base58' or 'base64'
 * @return string
 */
function encode(string $string, string $encoding): string
{
    switch ($encoding) {
        case 'raw':
            return $string;
        case 'base58':
            $string = base58_encode($string);
            break;
        case 'base64':
            $string = base64_encode($string);
            break;
        case 'hex':
            $string = bin2hex($string);
            break;
        default:
            throw new \InvalidArgumentException("Unknown encoding '$encoding'");
    }

    if ($string === false) {
        throw new \InvalidArgumentException("Failed to encode to '$encoding'"); // @codeCoverageIgnore
    }

    return $string;
}

/**
 * Base58 or base64 decode a string
 *
 * @param string $string
 * @param string $encoding  'raw', 'hex', 'base58' or 'base64'
 * @return string
 */
function decode(string $string, string $encoding): string
{
    switch ($encoding) {
        case 'raw':
            return $string;
        case 'base58':
            $string = @base58_decode($string);
            break;
        case 'base64':
            $string = @base64_decode($string, true);
            break;
        case 'hex':
            $string = @hex2bin($string);
            break;
        default:
            throw new \InvalidArgumentException("Unknown encoding '$encoding'");
    }

    if ($string === false) {
        $err = error_get_last();
        throw new \InvalidArgumentException("Failed to decode from '$encoding'. " . ($err['message'] ?? ''));
    }

    return $string;
}

/**
 * Decode and then encode a string
 *
 * @param string $string
 * @param string $fromEncoding  'raw', 'hex', 'base58' or 'base64'
 * @param string $toEncoding    'raw', 'hex', 'base58' or 'base64'
 * @return string
 */
function recode(string $string, string $fromEncoding, string $toEncoding): string
{
    return $fromEncoding === $toEncoding ? $string : encode(decode($string, $fromEncoding), $toEncoding);
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
function is_valid_address(string $address, string $encoding = 'base58'): bool
{
    if ($encoding === 'base58' && !preg_match('/^[1-9A-HJ-NP-Za-km-z]+$/', $address)) {
        return false;
    }

    if (
        $encoding === 'base64' &&
        !preg_match('/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/', $address)
    ) {
        return false;
    }

    return strlen(decode($address, $encoding)) === 26;
}

/**
 * Get the public properties of an object as associative array.
 *
 * @param object $object
 * @return array
 */
function get_public_properties(object $object): array
{
    return get_object_vars($object);
}
