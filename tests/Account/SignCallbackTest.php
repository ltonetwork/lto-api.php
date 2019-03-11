<?php

namespace LTO\Tests\Account;

use InvalidArgumentException;
use LTO\Account;
use LTO\Account\SignCallback;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LTO\Account\SignCallback
 */
class SignCallbackTest extends TestCase
{
    public function hashAlgoProvider()
    {
        return [
            [null],
            ['sha256'],
            ['md5'],
            ['sha3-512'],
        ];
    }

    /**
     * @dataProvider hashAlgoProvider
     */
    public function test(?string $hashAlgo)
    {
        $message = $hashAlgo === null ? 'hello' : hash($hashAlgo, 'hello', true);

        $account = $this->createMock(Account::class);
        $account->sign = (object)[
            'secretkey' => base58_decode('4zsR9xoFpxfnNwLcY4hdRUarwf5xWtLj6FpKGDFBgscPxecPj2qgRNx4kJsFCpe9YDxBRNoeBWTh2SDAdwTySomS'),
            'publickey' => base58_decode('GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY')
        ];
        $account->expects($this->once())->method('sign')
            ->with($message)
            ->willReturn('__mock_signature__'); // Would normally return the signature

        $algo = 'ed25519' . ($hashAlgo !== null ? '-' . $hashAlgo : '');

        $sign = new SignCallback($account);
        $ret = $sign('hello', '', $algo);

        $this->assertEquals('__mock_signature__', $ret);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidAccount()
    {
        $account = new Account();
        new SignCallback($account);
    }

    public function invalidAlgorithmProvider()
    {
        return [
            ['hmac'],
            ['hmac-sha256'],
            ['ed25519-kaccak'],
        ];
    }

    /**
     * @dataProvider invalidAlgorithmProvider
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidAlgorithm(string $algo)
    {
        $account = $this->createMock(Account::class);
        $account->sign = (object)[
            'secretkey' => base58_decode('4zsR9xoFpxfnNwLcY4hdRUarwf5xWtLj6FpKGDFBgscPxecPj2qgRNx4kJsFCpe9YDxBRNoeBWTh2SDAdwTySomS'),
            'publickey' => base58_decode('GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY')
        ];

        $this->expectExceptionMessage('Unsupported algorithm: ' . $algo);

        $sign = new SignCallback($account);
        $sign('hello', '', $algo);
    }
}
