<?php declare(strict_types=1);

namespace LTO;

/**
 * Encode and decode to base58 or base64
 */
class Encoding
{
    /**
     * Base58 or base64 encode a string
     * 
     * @param string $string
     * @param string $encoding  'raw', 'base58' or 'base64'
     * @return string
     */
    public static function encode(string $string, string $encoding = 'base58'): string
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
    public static function decode(string $string, string $encoding = 'base58'): string
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
}

