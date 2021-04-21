<?php

declare(strict_types=1);

namespace LTO;

/**
 * Functions for signing and encryption
 */
interface Cryptography
{
    /**
     * Create an encoded signature of a message.
     */
    public function sign(string $message, string $secretkey): string;

    /**
     * Verify a signature of a message
     */
    public function verify(string $message, string $publickey, string $signature): bool;


    /**
     * Encrypt a message for another account.
     * The nonce is appended.
     */
    public function encrypt(string $message, string $secretkey, string $publickey): string;

    /**
     * Decrypt a message from another account.
     *
     * @throws DecryptException if message can't be decrypted
     */
    public function decrypt(string $cypherText, string $secretkey, string $publickey): string;


    /**
     * Create sign key pair.
     *
     * @param string $seed
     * @return \stdClass
     */
    public function createSignKeys(string $seed): \stdClass;

    /**
     * Get the public key of a secret key for signing.
     *
     * @param string $secretkey
     * @return string
     */
    public function getPublicSignKey(string $secretkey): string;

    /**
     * Create encrypt key pair
     *
     * @param string $seed
     * @return \stdClass
     */
    public function createEncryptKeys(string $seed): \stdClass;

    /**
     * Get the public key of a secret key for encryption.
     *
     * @param string $secretkey
     * @return string
     */
    public function getPublicEncryptKey(string $secretkey): string;

    /**
     * Convert sign keys to encrypt keys.
     *
     * @param object|string $sign
     * @return \stdClass|null
     */
    public function convertSignToEncrypt($sign): ?\stdClass;
}
