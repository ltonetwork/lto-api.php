<?php declare(strict_types=1);

namespace LTO\Account;

use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Http\Message\ResponseInterface as Response;
use LTO\AccountFactory;
use LTO\Account;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to add an Account attribute to a request.
 * Can be used both as single pass (PSR-15) and double pass middleware.
 * This middleware is intended to be used in conjunction with HttpSignature middleware.
 */
class ServerMiddleware implements MiddlewareInterface
{
    /**
     * @var AccountFactory
     */
    protected $accountFactory;

    /**
     * @var Account|null
     */
    protected $trustedAccount;

    /**
     * Public key encoding.
     * @var string
     * @options raw,base58,base64
     */
    protected $encoding;


    /**
     * Class constructor.
     *
     * @param AccountFactory $accountFactory
     * @param string         $encoding        Public key encoding.
     */
    public function __construct(AccountFactory $accountFactory, $encoding = 'base58')
    {
        $this->accountFactory = $accountFactory;
        $this->encoding = $encoding;
    }

    /**
     * Get a copy with a trusted account.
     * Requests signed with this account, may use the `X-Original-Key-Id` header.
     *
     * @param Account $account
     * @return self
     */
    public function withTrustedAccount(Account $account)
    {
        if ($this->trustedAccount === $account) {
            return $this;
        }

        $clone = clone $this;
        $clone->trustedAccount = $account;

        return $clone;
    }

    /**
     * Get a copy without a trusted account.
     * The `X-Original-Key-Id` header is ignored.
     *
     * @return self
     */
    public function withoutTrustedAccount()
    {
        if ($this->trustedAccount === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->trustedAccount = null;

        return $clone;
    }

    /**
     * Get the trusted account.
     * Requests signed with this account, may use the `X-Original-Key-Id` header.
     *
     * @return Account|null
     */
    public function getTrustedAccount(): ?Account
    {
        return $this->trustedAccount;
    }


    /**
     * Process an incoming server request (PSR-15).
     *
     * @param ServerRequest           $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    public function process(ServerRequest $request, RequestHandlerInterface $handler): Response
    {
        return $this->handleRequest($request, [$handler, 'handle']);
    }

    /**
     * Get a callback that can be used as double pass middleware.
     *
     * @return callable
     */
    public function asDoublePass(): callable
    {
        return function (ServerRequest $request, Response $response, callable $next): Response {
            $fn = function (ServerRequest $request) use ($response, $next) {
                return $next($request, $response, $next);
            };

            return $this->handleRequest($request, $fn);
        };
    }

    /**
     * Handle a request, adding the 'account' attribute.
     *
     * @param ServerRequest  $request
     * @param callable       $next
     * @return Response
     */
    protected function handleRequest(ServerRequest $request, callable $next): Response
    {
        $keyId = $request->getAttribute('signature_key_id');

        if ($this->isTrustedKeyId($keyId) && $request->hasHeader('X-Original-Key-Id')) {
            $keyId = $request->getHeaderLine('X-Original-Key-Id');
        }

        if ($keyId !== null) {
            $account = $this->accountFactory->createPublic($keyId, null, $this->encoding);
            $request = $request->withAttribute('account', $account);
        }

        return $next($request);
    }

    /**
     * Check if the keyId is a trusted if
     *
     * @param string|null|mixed $key
     * @return bool
     */
    protected function isTrustedKeyId($key): bool
    {
        return $this->trustedAccount !== null && $this->trustedAccount->getPublicSignKey($this->encoding) === $key;
    }
}
