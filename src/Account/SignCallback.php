<?php declare(strict_types=1);

namespace LTO\Account;

use LTO\Account;

/**
 * Callback to sign a message.
 * This can be used as callback for the HttpSignature service.
 */
class SignCallback
{
    protected Account $account;

    /**
     * Class constructor.
     */
    public function __construct(Account $account)
    {
        if (!isset($account->sign->secretkey)) {
            throw new \InvalidArgumentException('Unable to use account to sign; no secret sign key');
        }

        $this->account = $account;
    }

    /**
     * Invoke the callback.
     *
     * @param string $message
     * @param string $keyId
     * @param string $algorithm  'ed25519' or 'ed25519-sha256'
     * @return string
     */
    public function __invoke(string $message, string $keyId, string $algorithm): string
    {
        [$encryptAlgo, $hashAlgo] = explode('-', $algorithm, 2) + [null, null];

        if ($encryptAlgo !== 'ed25519' || ($hashAlgo !== null && !in_array($hashAlgo, hash_algos(), true))) {
            throw new \InvalidArgumentException('Unsupported algorithm: ' . $algorithm);
        }

        if ($keyId !== $this->account->getPublicSignKey()) {
            throw new \InvalidArgumentException('keyId doesn\'t match account public key');
        }

        if ($hashAlgo !== null) {
            $message = hash($hashAlgo, $message, true);
        }

        return $this->account->sign($message)->raw(); // HttpSignature service will base64 encode.
    }
}
