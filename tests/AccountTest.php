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
        $this->account = $this->createPartialMock(Account::class, ['getNonce']);
        $this->account->method('getNonce')->willReturn(str_repeat("\0", 24));

        $this->account->address = base58_decode('3JmCa4jLVv7Yn2XkCnBUGsa7WNFVEMxAfWe');
        
        $this->account->sign = (object)[
            'secretkey' => base58_decode('4zsR9xoFpxfnNwLcY4hdRUarwf5xWtLj6FpKGDFBgscPxecPj2qgRNx4kJsFCpe9YDxBRNoeBWTh2SDAdwTySomS'),
            'publickey' => base58_decode('GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY')
        ];
        
        $this->account->encrypt = (object)[
            'secretkey' => base58_decode('4q7HKMbwbLcG58iFV3pz4vkRnPTwbrY9Q5JrwnwLEZCC'),
            'publickey' => base58_decode('6fDod1xcVj4Zezwyy3tdPGHkuDyMq8bDHQouyp5BjXsX')
        ];
    }
    
    public function testGetAddress()
    {
        $this->assertSame("3JmCa4jLVv7Yn2XkCnBUGsa7WNFVEMxAfWe", $this->account->getAddress());
    }
    
    public function testGetPublicSignKey()
    {
        $this->assertSame("GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY", $this->account->getPublicSignKey());
    }
    
    public function testGetPublicEncryptKey()
    {
        $this->assertSame("6fDod1xcVj4Zezwyy3tdPGHkuDyMq8bDHQouyp5BjXsX", $this->account->getPublicEncryptKey());
    }
    
    
    public function testSign()
    {
        $signature = $this->account->sign("hello");
        
        $this->assertSame(
            '5i9gBaHwg9UFPuwU63LBdBR29yZdRDstWM9z7oo8GzevWhBdAAWwCSRUQbPLaCT3nFgjbQuuWxVQckzCd3CoFig4',
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
            "GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY"
        ]);
        
        $event = $this->createMock(Event::class);
        $event->expects($this->once())->method('getMessage')->willReturn($message);
        $event->expects($this->once())->method('getHash')->willReturn('47FmxvJ4v1Bnk4SGSwrHcncX5t5u3eAjmc6QJgbR5nn8');
        
        $ret = $this->account->signEvent($event);
        $this->assertSame($event, $ret);
        
        $this->assertAttributeEquals('GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY', 'signkey', $event);
        $this->assertAttributeEquals('4pwrLbWSYqE7st7fCGc2fW2eA33DP1uE4sBm6onfwYNk4M8Av9u4Mqx1R77sVzRRofQgoHGTLRh8pRBRzp5JGBo9', 'signature', $event);
        $this->assertAttributeEquals('47FmxvJ4v1Bnk4SGSwrHcncX5t5u3eAjmc6QJgbR5nn8', 'hash', $event);
    }
    
    public function testSignAndVerify()
    {
        $signature = $this->account->sign("hello");

        $this->assertSame(
            '5i9gBaHwg9UFPuwU63LBdBR29yZdRDstWM9z7oo8GzevWhBdAAWwCSRUQbPLaCT3nFgjbQuuWxVQckzCd3CoFig4',
            $signature
        );

        $this->account->verify($signature, "hello");
    }

    public function testSignAndVerifyOtherAccount()
    {
        $base58 = new \StephenHill\Base58();
        $account = $this->createPartialMock(Account::class, ['getNonce']);
        $account->method('getNonce')->willReturn(str_repeat("\0", 24));
        
        $account->sign = (object) [
            'secretkey' => base58_decode('3Exo2vCYQXd6Uqb4basuhSbCQbfUXAfp71Tbr1E2Yi7tkJeMqdEabjavuHFZj9oJ3TJyMGJssw4w1pdg8HVT1Xjx'),
            'publickey' => base58_decode('42uogHha8jzG8idNGZvNpZDEzcRZustuTxk6SKKrgEpr')
        ];
        
        $signature = $account->sign("hello");
        
        $this->assertSame(
            '2bu6zVLVJCtjuhiAmHiKhBHcvE9rQPCwDgMMwbQdEKfAZUYTp3DemCNteFAAFjZZFwnM2yKjCNx2uudMkQ8Jamp',
            $signature
        );
        
        $account->verify($signature, "hello");
    }
    
    public function testSignAndVerifyHash()
    {
        $message = 'hello';
        $hash = hash('sha256', $message, true);
        $signature = $this->account->sign($hash);        
        
        $this->assertSame(
            '5HHgRNtbEPRDok5JwBkP7YLDje6oJfJkpGtrxCB7WNKexxc1MqdXdhsDoETBmLD7XDNnhdeW73eLS7s2ApAR624H',
            $signature
        );
        
        $this->account->verify($signature, $hash);
    }
    
    public function testSignAndVerifyHashBase64()
    {
        $message = 'hello';
        $hash = hash('sha256', $message, true);
        $signature = $this->account->sign($hash, 'base64');        
        
        $this->assertSame(
            '1h0g/FGh5lVNI1cIAcAYaqQjACkTABHqXTTdjgBzsfkWn5KUN5x03fxukQr80Vvkvm2JATHDJBxq96YPVP8WCg==',
            $signature
        );
        
        $this->account->verify($signature, $hash, 'base64');
    }
    
    public function testVerifyLTORequest()
    {
        $account = $this->createPartialMock(Account::class, ['getNonce']);
        $account->method('getNonce')->willReturn(str_repeat("\0", 24));
        
        $account->sign = (object) [
            'secretkey' => base58_decode('4zsR9xoFpxfnNwLcY4hdRUarwf5xWtLj6FpKGDFBgscPxecPj2qgRNx4kJsFCpe9YDxBRNoeBWTh2SDAdwTySomS'),
            'publickey' => base58_decode('GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY')
        ];
        
        $msg = join("\n", [
            "(request-target): post /api/events/event-chains",
            "x-date: 1522854960166",
            "digest: 47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU=",
            "content-length: 8192"
        ]);
        
        $signatureMsg = $account->sign($msg, 'base64');
        $this->assertEquals( 'qegUJyHIOASGArXct2F2me9p4ebCTkxHJSDCc+niAb9A+1Pl/hiz5cHTkzQ4ddWUTeBLknYN3FueNqOAIGFRBg==', $signatureMsg);
        
        $this->assertTrue($account->verify($signatureMsg, $msg, 'base64'));
        
        $hash = hash('sha256', $msg, true);
        $signatureHash = $account->sign($hash, 'base64');
        $this->assertTrue($account->verify($signatureHash, $hash, 'base64'));
    }

    public function testVerify()
    {
        $signature = '5i9gBaHwg9UFPuwU63LBdBR29yZdRDstWM9z7oo8GzevWhBdAAWwCSRUQbPLaCT3nFgjbQuuWxVQckzCd3CoFig4';

        $this->assertTrue($this->account->verify($signature, 'hello'));
    }

    public function testVerifyFail()
    {
        $signature = '5i9gBaHwg9UFPuwU63LBdBR29yZdRDstWM9z7oo8GzevWhBdAAWwCSRUQbPLaCT3nFgjbQuuWxVQckzCd3CoFig4';
        
        $this->assertFalse($this->account->verify($signature, 'not this'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testVerifyInvalid()
    {
        $signature = 'not a real signature';
        
        $this->assertFalse(@$this->account->verify($signature, 'hello'));
    }
    
    

    public function createSecondaryAccount()
    {
        $account = $this->createPartialMock(Account::class, ['getNonce']);
        $account->method('getNonce')->willReturn(str_repeat('0', 24));

        $account->address = base58_decode('3JwCboNM8yFNxZqbDcj4H9andqoSA25iGTa');
        
        $account->sign = (object)[
            'secretkey' => base58_decode('5DteGKYVUUSSaruCK6H8tpd4oYWfcyNohyhJiYGYGBVzhuEmAmRRNcUJQzA2bk4DqqbtpaE51HTD1i3keTvtbCTL'),
            'publickey' => base58_decode('gVVExGUK4J5BsxwUfYsFkkjpn6A7BcvYdmARL28GBRc')
        ];
        
        $account->encrypt = (object)[
            'secretkey' => base58_decode('ACsYcMff8UPUc5dvuCMAkqZxcRTjXHMnCc29TZkWLQsZ'),
            'publickey' => base58_decode('EZa2ndj6h95m3xm7DxPQhrtANvhymNC7nWQ3o1vmDJ4x')
        ];
        
        return $account;
    }
    
    public function testEncryptFor()
    {
        $recipient = $this->createSecondaryAccount();
        
        $cyphertext = $this->account->encryptFor($recipient, 'hello');
        
        $this->assertSame('2246pmtzDem9GB15GmtULMXB7Vrr1wciQcHsQvrUsmapeaBQzqHNUcS4KYYu7D', base58_encode($cyphertext));
    }
    
    public function testDecryptFrom()
    {
        $recipient = $this->createSecondaryAccount();
        $cyphertext = base58_decode('2246pmtzDem9GB15GmtULMXB7Vrr1wciQcHsQvrUsmapeaBQzqHNUcS4KYYu7D');
        
        $message = $recipient->decryptFrom($this->account, $cyphertext);
        
        $this->assertSame('hello', $message);
    }
    
    /**
     * Try to encrypt a message with your own keys.
     * @expectedException LTO\DecryptException
     */
    public function testDecryptFromFail()
    {
        $cyphertext = base58_decode('2246pmtzDem9GB15GmtULMXB7Vrr1wciQcHsQvrUsmapeaBQzqHNUcS4KYYu7D');
        
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
        $signkeyHashed = bin2hex(substr(sha256(blake2b($signkey)), 0, 20));
        $decodedId = base58_decode($chain->id);
        
        ['version' => $version, 'keyhash' => $keyhash, 'checksum' => $checksum] =
            unpack('Cversion/H40nonce/H40keyhash/H8checksum', $decodedId);
        
        $this->assertEquals(EventChain::CHAIN_ID, $version);
        $this->assertEquals($keyhash, substr($signkeyHashed, 0, 40));
        $this->assertEquals($checksum, substr(bin2hex($decodedId), -8));
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
        $this->assertEquals("2bGCW3XbfLmSRhotYzcUgqiomiiFLHZS1AWDKwh11De7JJDehsnTbMd8jqwyTZ", $chain->id);
    }
}
