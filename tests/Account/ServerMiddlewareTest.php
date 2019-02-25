<?php

namespace LTO\Tests\Account;

use Jasny\TestHelper;
use LTO\Account;
use LTO\Account\ServerMiddleware;
use LTO\AccountFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @covers \LTO\Account\ServerMiddleware
 */
class ServerMiddlewareTest extends TestCase
{
    use TestHelper;

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
}
