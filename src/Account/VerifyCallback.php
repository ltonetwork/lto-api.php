<?php declare(strict_types=1);

namespace LTO\Account;

use LTO\AccountFactory;
use LTO\Binary;
use LTO\InvalidAccountException;

/**
 * Callback to verify a signature.
 * This can be used as callback for the HttpSignature service.
 */
class VerifyCallback
{
    protected AccountFactory $accountFactory;

    /**
     * Public key encoding.
     * @options raw,base58,base64
     */
    protected string $encoding;

    /**
     * Class constructor.
     *
     * @param AccountFactory $accountFactory
     * @param string         $encoding        Public key encoding for `keyId`.
     */
    public function __construct(AccountFactory $accountFactory, string $encoding = 'base58')
    {
        if (!in_array($encoding, ['raw', 'base58', 'base64'], true)) {
            throw new \InvalidArgumentException('Unsupported encoding: '. $encoding);
        }

        $this->accountFactory = $accountFactory;
        $this->encoding = $encoding;
    }

    /**
     * Invoke the callback.
     *
     * @param string $message
     * @param string $signature  Raw signature
     * @param string $publicKey  Encoded public key (default base58)
     * @param string $algorithm  'ed25519' or 'ed25519-sha256'
     * @return bool
     * @throws InvalidAccountException
     */
    public function __invoke(string $message, string $signature, string $publicKey, string $algorithm)
    {
        list($encryptAlgo, $hashAlgo) = explode('-', $algorithm, 2) + [null, null];

        if ($encryptAlgo !== 'ed25519' || ($hashAlgo !== null && !in_array($hashAlgo, hash_algos(), true))) {
            throw new \InvalidArgumentException('Unsupported algorithm: ' . $algorithm);
        }

        try {
            $account = $this->accountFactory->createPublic($publicKey, null, $this->encoding);
        } catch (InvalidAccountException $exception) {
            return false;
        }

        if ($hashAlgo !== null) {
            $message = hash($hashAlgo, $message, true);
        }

        return $account->verify($message, Binary::fromRaw($signature));
    }
}
