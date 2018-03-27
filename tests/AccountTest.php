<?php

namespace LTO;

use PHPUnit_Framework_TestCase as TestCase;
use LTO\Account;

/**
 * @covers LTO\Account
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
        $base58 = new \StephenHill\Base58();
        
        $signature = $this->account->sign("hello");
        
        $this->assertSame(
            'QTMso5awML5XXfhrCgjmJCxGpm85PEEAK3WdBuQjKF4zuDtzVKCrGC2PcjZc5fjjREczg1sMaApP5yCZX3Z3WNBzzLcYeRbVxyGa8TvNBfFxeTgPMD52gMbS',
            $base58->encode($signature)
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
}
