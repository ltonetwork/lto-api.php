<?php

namespace LTO\Tests\Account;

use Jasny\PHPUnit\CallbackMockTrait;
use LTO\Account;
use LTO\Account\ServerMiddleware;
use LTO\AccountFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function LTO\encode;

/**
 * @covers \LTO\Account\ServerMiddleware
 */
class ServerMiddlewareTest extends TestCase
{
    use CallbackMockTrait;

    public function keyProvider()
    {
        $raw = base58_decode('GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY');

        return [
            [$raw, 'raw'],
            [base58_encode($raw), 'base58'],
            [base64_encode($raw), 'base64'],
        ];
    }

    /**
     * @dataProvider keyProvider
     */
    public function testSinglePass(string $keyId, string $encoding)
    {
        $account = $this->createMock(Account::class);

        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->once())->method('createPublic')
            ->with($keyId, null, $encoding)
            ->willReturn($account);

        $accountRequest = $this->createMock(ServerRequestInterface::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->atLeastOnce())->method('getAttribute')
            ->with('signature_key_id')->willReturn($keyId);
        $request->expects($this->once())->method('withAttribute')
            ->with('account', $account)->willReturn($accountRequest);
        $request->expects($this->never())->method('hasHeader');

        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')
            ->with($this->identicalTo($accountRequest))
            ->willReturn($response);

        $middleware = new ServerMiddleware($accountFactory, $encoding);

        $ret = $middleware->process($request, $handler);

        $this->assertSame($response, $ret);
    }


    /**
     * @dataProvider keyProvider
     */
    public function testDoublePass(string $keyId, string $encoding)
    {
        $account = $this->createMock(Account::class);

        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->once())->method('createPublic')
            ->with($keyId, null, $encoding)
            ->willReturn($account);

        $accountRequest = $this->createMock(ServerRequestInterface::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->atLeastOnce())->method('getAttribute')
            ->with('signature_key_id')->willReturn($keyId);
        $request->expects($this->once())->method('withAttribute')
            ->with('account', $account)->willReturn($accountRequest);

        $baseResponse = $this->createMock(ResponseInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $next = $this->createCallbackMock(
            $this->once(),
            [$this->identicalTo($accountRequest), $this->identicalTo($baseResponse)],
            $response
        );

        $middleware = new ServerMiddleware($accountFactory, $encoding);
        $doublePass = $middleware->asDoublePass();

        $ret = $doublePass($request, $baseResponse, $next);

        $this->assertSame($response, $ret);
    }

    public function testTrustedAccount()
    {
        $account = $this->createMock(Account::class);

        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->never())->method($this->anything());

        $middleware = new ServerMiddleware($accountFactory);
        $this->assertNull($middleware->getTrustedAccount());

        $trustedMiddleware = $middleware->withTrustedAccount($account);
        $this->assertInstanceOf(ServerMiddleware::class, $trustedMiddleware);
        $this->assertNotSame($middleware, $trustedMiddleware);
        $this->assertSame($account, $trustedMiddleware->getTrustedAccount());

        $this->assertSame($trustedMiddleware, $trustedMiddleware->withTrustedAccount($account));

        $untrustedMiddleware = $trustedMiddleware->withoutTrustedAccount();
        $this->assertNotSame($trustedMiddleware, $untrustedMiddleware);
        $this->assertNull($untrustedMiddleware->getTrustedAccount());

        $this->assertSame($untrustedMiddleware, $untrustedMiddleware->withoutTrustedAccount());
    }

    /**
     * @dataProvider keyProvider
     */
    public function testWithOriginalKeyId(string $originalKeyId, string $encoding)
    {
        $raw = base58_decode('5LucyTBFqSeg8qg4e33uuLY93RZqSQZjmrtsUydUNYgg');
        $trustedKeyId = encode($raw, $encoding);

        $account = $this->createMock(Account::class);

        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->once())->method('createPublic')
            ->with($originalKeyId, null, $encoding)
            ->willReturn($account);

        $accountRequest = $this->createMock(ServerRequestInterface::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->atLeastOnce())->method('getAttribute')
            ->with('signature_key_id')->willReturn($trustedKeyId);
        $request->expects($this->atLeastOnce())->method('hasHeader')
            ->with('X-Original-Key-Id')->willReturn(true);
        $request->expects($this->atLeastOnce())->method('getHeaderLine')
            ->with('X-Original-Key-Id')->willReturn($originalKeyId);
        $request->expects($this->once())->method('withAttribute')
            ->with('account', $account)->willReturn($accountRequest);

        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')
            ->with($this->identicalTo($accountRequest))
            ->willReturn($response);

        $trustedAccount = $this->createMock(Account::class);
        $trustedAccount->expects($this->once())->method('getPublicSignKey')
            ->with($encoding)->willReturn($trustedKeyId);

        $middleware = (new ServerMiddleware($accountFactory, $encoding))
            ->withTrustedAccount($trustedAccount);

        $ret = $middleware->process($request, $handler);

        $this->assertSame($response, $ret);
    }
}
