<?php

declare(strict_types=1);

namespace LTO;

use PHPUnit\Framework\TestCase;
use LTO\Account;
use kornrunner\Keccak;

/**
 * @covers \LTO\Account
 */
class AccountTest extends TestCase
{
    /**
     * @var Account
     */
    public $account;
    
    public function setUp()
    {
        $base58 = new \StephenHill\Base58();
        
        $this->account = $this->createPartialMock(Account::class, ['getNonce']);
        $this->account->method('getNonce')->willReturn(str_repeat("\0", 24));

        $this->account->address = $base58->decode('3PLSsSDUn3kZdGe8qWEDak9y8oAjLVecXV1');
        
        $this->account->sign = (object)[
            'secretkey' => $base58->decode('wJ4WH8dD88fSkNdFQRjaAhjFUZzZhV5yiDLDwNUnp6bYwRXrvWV8MJhQ9HL9uqMDG1n7XpTGZx7PafqaayQV8Rp'),
            'publickey' => $base58->decode('FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y')
        ];
        
        $this->account->encrypt = (object)[
            'secretkey' => $base58->decode('BnjFJJarge15FiqcxrB7Mzt68nseBXXR4LQ54qFBsWJN'),
            'publickey' => $base58->decode('BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6')
        ];
    }
    
    public function testGetAddress()
    {
        $this->assertSame("3PLSsSDUn3kZdGe8qWEDak9y8oAjLVecXV1", $this->account->getAddress());
    }
    
    public function testGetPublicSignKey()
    {
        $this->assertSame("FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y", $this->account->getPublicSignKey());
    }
    
    public function testGetPublicEncryptKey()
    {
        $this->assertSame("BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6", $this->account->getPublicEncryptKey());
    }
    
    
    public function testSign()
    {
        $signature = $this->account->sign("hello");
        
        $this->assertSame(
            '2DDGtVHrX66Ae8C4shFho4AqgojCBTcE4phbCRTm3qXCKPZZ7reJBXiiwxweQAkJ3Tsz6Xd3r5qgnbA67gdL5fWE',
            $signature
        );
    }
    
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Unable to sign message; no secret sign key
     */
    public function testSignNoKey()
    {
        $account = new Account();
        
        $account->sign("hello");
    }
    
    public function testSignEvent()
    {
        $message = join("\n", [
            "HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv",
            '2018-03-01T00:00:00+00:00',
            "72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW",
            "FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y"
        ]);
        
        $event = $this->createMock(Event::class);
        $event->expects($this->once())->method('getMessage')->willReturn($message);
        $event->expects($this->once())->method('getHash')->willReturn('47FmxvJ4v1Bnk4SGSwrHcncX5t5u3eAjmc6QJgbR5nn8');
        
        $ret = $this->account->signEvent($event);
        $this->assertSame($event, $ret);
        
        $this->assertAttributeEquals('FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y', 'signkey', $event);
        $this->assertAttributeEquals('Szr7uLhwirqEuVJ9SBPuAgvFAbuiMG23FbCsVNbptLbMH7uzrR5R23Yze83YGe98HawMzjvEMWgsJhdRQDXw8Br', 'signature', $event);
        $this->assertAttributeEquals('47FmxvJ4v1Bnk4SGSwrHcncX5t5u3eAjmc6QJgbR5nn8', 'hash', $event);
    }

    public function testVerify()
    {
        $signature = '2DDGtVHrX66Ae8C4shFho4AqgojCBTcE4phbCRTm3qXCKPZZ7reJBXiiwxweQAkJ3Tsz6Xd3r5qgnbA67gdL5fWE';
        
        $this->assertTrue($this->account->verify($signature, 'hello'));
    }

    public function testVerifyFail()
    {
        $signature = '2DDGtVHrX66Ae8C4shFho4AqgojCBTcE4phbCRTm3qXCKPZZ7reJBXiiwxweQAkJ3Tsz6Xd3r5qgnbA67gdL5fWE';
        
        $this->assertFalse($this->account->verify($signature, 'not this'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testVerifyInvalid()
    {
        $signature = 'not a real signature';
        
        $this->assertTrue($this->account->verify($signature, 'hello'));
    }
    
    

    public function createSecondaryAccount()
    {
        $base58 = new \StephenHill\Base58();
        
        $account = $this->createPartialMock(Account::class, ['getNonce']);
        $account->method('getNonce')->willReturn(str_repeat('0', 24));

        $account->address = $base58->decode('3PPbMwqLtwBGcJrTA5whqJfY95GqnNnFMDX');
        
        $account->sign = (object)[
            'secretkey' => $base58->decode('pLX2GgWzkjiiPp2SsowyyHZKrF4thkq1oDLD7tqBpYDwfMvRsPANMutwRvTVZHrw8VzsKjiN8EfdGA9M84smoEz'),
            'publickey' => $base58->decode('BvEdG3ATxtmkbCVj9k2yvh3s6ooktBoSmyp8xwDqCQHp')
        ];
        
        $account->encrypt = (object)[
            'secretkey' => $base58->decode('3kMEhU5z3v8bmer1ERFUUhW58Dtuhyo9hE5vrhjqAWYT'),
            'publickey' => $base58->decode('HBqhfdFASRQ5eBBpu2y6c6KKi1az6bMx8v1JxX4iW1Q8')
        ];
        
        return $account;
    }
    
    public function testEncryptFor()
    {
        $base58 = new \StephenHill\Base58();
        
        $recipient = $this->createSecondaryAccount();
        
        $cyphertext = $this->account->encryptFor($recipient, 'hello');
        
        $this->assertSame('3NQBM8qd7nbLjABMf65jdExWt3xSAtAW2Sonjc7ZTLyqWAvDgiJNq7tW1XFX5H', $base58->encode($cyphertext));
    }
    
    public function testDecryptFrom()
    {
        $base58 = new \StephenHill\Base58();
        
        $recipient = $this->createSecondaryAccount();
        $cyphertext = $base58->decode('3NQBM8qd7nbLjABMf65jdExWt3xSAtAW2Sonjc7ZTLyqWAvDgiJNq7tW1XFX5H');
        
        $message = $recipient->decryptFrom($this->account, $cyphertext);
        
        $this->assertSame('hello', $message);
    }
    
    /**
     * Try to encrypt a message with your own keys.
     * @expectedException LTO\DecryptException
     */
    public function testDecryptFromFail()
    {
        $base58 = new \StephenHill\Base58();
        
        $cyphertext = $base58->decode('3NQBM8qd7nbLjABMf65jdExWt3xSAtAW2Sonjc7ZTLyqWAvDgiJNq7tW1XFX5H');
        
        $this->account->decryptFrom($this->account, $cyphertext);
    }


    /**
     * Assert that the chain has a valid id for this account
     * 
     * @param string     $signkey
     * @param EventChain $chain
     */
    protected function assertValidId($signkey, $chain)
    {
        $signkeyHashed = substr(Keccak::hash(sodium_crypto_generichash($signkey, '', 32), 256), 0, 40);
        
        $base58 = new \StephenHill\Base58();
        $decodedId = $base58->decode($chain->id);
        
        $vars = (object)unpack('Cversion/H40nonce/H40keyhash/H8checksum', $decodedId);
        
        $this->assertAttributeEquals(EventChain::ADDRESS_VERSION, 'version', $vars);
        $this->assertAttributeEquals(substr($signkeyHashed, 0, 40), 'keyhash', $vars);
        $this->assertAttributeEquals(substr(bin2hex($decodedId), -8), 'checksum', $vars);
    }
    
    public function testCreateEventChain()
    {
        $chain = $this->account->createEventChain();
        
        $this->assertInstanceOf(EventChain::class, $chain);
        $this->assertValidId($this->account->sign->publickey, $chain);
    }

    public function testCreateEventChainSeeded()
    {
        $chain = $this->account->createEventChain(10);

        $this->assertInstanceOf(EventChain::class, $chain);
        $this->assertValidId($this->account->sign->publickey, $chain);
        $this->assertEquals("2bGCW3XbfLmSRhotYzcUgqiomiiFLSXKDU43jLMNaf29UXTkpkn2PfvyZkF8yx", $chain->id);
    }
}
