<?php

namespace LTO\Tests\Account;

use LTO\Account;
use LTO\Account\VerifyCallback;
use LTO\AccountFactory;
use LTO\InvalidAccountException;
use PHPUnit\Framework\TestCase;
use function LTO\encode;

/**
 * @covers \LTO\Account\VerifyCallback
 */
class VerifyCallbackTest extends TestCase
{
    public function hashAlgoProvider()
    {
        return [
            [],
            ['sha256'],
            ['md5'],
            ['sha3-512'],
        ];
    }

    /**
     * @dataProvider hashAlgoProvider
     */
    function test(?string $hashAlgo = null)
    {
        $publicKey = 'GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY';
        $message = $hashAlgo === null ? 'hello' : hash($hashAlgo, 'hello', true);

        $account = $this->createMock(Account::class);
        $account->sign = (object)['publickey' => 'GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY'];
        $account->expects($this->once())->method('verify')
            ->with('__mock_signature__', $message, 'base64')
            ->willReturn(true);

        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->once())->method('createPublic')
            ->with($publicKey, null, 'base58')
            ->willReturn($account);

        $algo = 'ed25519' . ($hashAlgo !== null ? '-' . $hashAlgo : '');

        $verify = new VerifyCallback($accountFactory, 'base58');
        $ret = $verify('hello', '__mock_signature__', $publicKey, $algo);

        $this->assertTrue($ret);
    }

    public function encodingProvider()
    {
        return [
            ['raw'],
            ['base58'],
            ['base64'],
        ];
    }

    /**
     * @dataProvider encodingProvider
     */
    function testPublicKeyEncoding(string $encoding)
    {
        $rawKey = base58_decode('GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY');
        $publicKey = encode($rawKey, $encoding);

        $account = $this->createMock(Account::class);
        $account->sign = (object)['publickey' => $rawKey];
        $account->expects($this->once())->method('verify')
            ->with('__mock_signature__', 'hello', 'base64')
            ->willReturn(true);

        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->once())->method('createPublic')
            ->with($publicKey, null, $encoding)
            ->willReturn($account);

        $verify = new VerifyCallback($accountFactory, $encoding);
        $ret = $verify('hello', '__mock_signature__', $publicKey, 'ed25519');

        $this->assertTrue($ret);
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
        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->never())->method('createPublic');

        $this->expectExceptionMessage('Unsupported algorithm: ' . $algo);

        $verify = new VerifyCallback($accountFactory, 'base58');
        $verify('hello', '__mock_signature__', 'GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY', $algo);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unsupported encoding: binary
     */
    public function testInvalidEncoding()
    {
        $accountFactory = $this->createMock(AccountFactory::class);

        new VerifyCallback($accountFactory, 'binary');
    }

    public function testWithInvalidSignature()
    {
        $publicKey = 'GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY';
        $rawKey = base58_decode('GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY');

        $account = $this->createMock(Account::class);
        $account->sign = (object)['publickey' => $rawKey];
        $account->expects($this->once())->method('verify')
            ->with('__mock_signature__', 'hello', 'base64')
            ->willReturn(false);

        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->once())->method('createPublic')
            ->with($publicKey, null, 'base58')
            ->willReturn($account);

        $verify = new VerifyCallback($accountFactory, 'base58');
        $ret = $verify('hello', '__mock_signature__', $publicKey, 'ed25519');

        $this->assertFalse($ret);
    }

    public function testWithInvalidPublicKey()
    {
        $publicKey = 'GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY';

        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->once())->method('createPublic')
            ->with($publicKey, null, 'base58')
            ->willThrowException(new InvalidAccountException('invalid account'));

        $verify = new VerifyCallback($accountFactory, 'base58');
        $ret = $verify('hello', '__mock_signature__', $publicKey, 'ed25519');

        $this->assertFalse($ret);
    }
}
