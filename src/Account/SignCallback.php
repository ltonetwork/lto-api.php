<?php declare(strict_types=1);

namespace LTO\Account;

use LTO\Account;

/**
 * Callback to sign a message.
 * This can be used as callback for the HttpSignature service.
 */
class SignCallback
{
    /**
     * @var Account
     */
    protected $account;

    /**
     * Class constructor.
     *
     * @param Account $account
     * @throws \RuntimeException
     */
    public function __construct(Account $account)
    {
        if (!isset($account->sign->secretkey)) {
            throw new \InvalidArgumentException('Unable to use account to sign; no secret sign key');
        }

        $this->account = $account;
    }

    /**
     * Invoke the callback
     *
     * @param string $message
     * @param string $algorithm
     * @return string
     */
    public function __invoke(string $message, string $algorithm)
    {
        list($encryptAlgo, $hashAlgo) = explode('-', $algorithm, 2) + [null, null];

        if ($encryptAlgo !== 'ed25519' || ($hashAlgo !== null && !in_array($hashAlgo, hash_algos(), true))) {
            throw new \InvalidArgumentException('Unsupported algorithm: ' . $algorithm);
        }

        if ($hashAlgo !== null) {
            $message = hash($hashAlgo, $message, true);
        }

        return $this->account->sign($message);
    }
}
