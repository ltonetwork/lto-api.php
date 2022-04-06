<?php

namespace LTO\Tests\Account\Secp256k1;

use LTO\Account;
use LTO\Binary;
use LTO\Cryptography\ECDSA;
use LTO\Event;
use LTO\EventChain;
use LTO\Transaction;
use PHPUnit\Framework\TestCase;
use function base58_decode;
use function LTO\sha256;
use function sodium_crypto_generichash as blake2b;

/**
 * @covers \LTO\Account
 * @covers \LTO\Cryptography\ECDSA
 */
class AccountTest extends TestCase
{
    protected Account $account;

    public function setUp(): void
    {
        $cryptography = ECDSA::forCurve('secp256k1');

        $this->account = new Account($cryptography);

        $this->account->address = base58_decode('3JmCa4jLVv7Yn2XkCnBUGsa7WNFVEMxAfWe');

        $this->account->sign = (object)[
            'secretkey' => base58_decode('7poBZFLzoHLX3j8C4n6dzmZWPQGB7Ypu2KRfbBCk3jWa'),
            'publickey' => base58_decode('vcHtjj77tE1QFSe3t7cjvWae7tVR4NuLPudkakVZtdRs')
        ];
    }

    public function testGetAddress()
    {
        $this->assertEquals("3JmCa4jLVv7Yn2XkCnBUGsa7WNFVEMxAfWe", $this->account->getAddress());
    }

    public function testGetPublicSignKey()
    {
        $this->assertEquals("GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY", $this->account->getPublicSignKey());
    }

    public function testGetNetwork()
    {
        $this->assertEquals("L", $this->account->getNetwork());
    }

    public function testGetNetworkWithoutAnAddress()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Address not set");

        $this->account->address = null;
        $this->account->getNetwork();
    }

    public function testSign()
    {
        $signature = $this->account->sign("hello");

        $this->assertEquals(
            Binary::fromBase58('5i9gBaHwg9UFPuwU63LBdBR29yZdRDstWM9z7oo8GzevWhBdAAWwCSRUQbPLaCT3nFgjbQuuWxVQckzCd3CoFig4'),
            $signature
        );
    }

    public function testSignNoKey()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to sign message; no secret sign key');

        $account = new Account($this->account->getCryptography());

        $account->sign("hello");
    }

    public function testSignAndVerify()
    {
        $signature = $this->account->sign("hello");

        $this->assertEquals(
            Binary::fromBase58('5i9gBaHwg9UFPuwU63LBdBR29yZdRDstWM9z7oo8GzevWhBdAAWwCSRUQbPLaCT3nFgjbQuuWxVQckzCd3CoFig4'),
            $signature
        );

        $this->account->verify("hello", $signature);
    }

    public function testSignAndVerifyOtherAccount()
    {
        $account = new Account($this->account->getCryptography());

        $account->sign = (object) [
            'secretkey' => base58_decode('3Exo2vCYQXd6Uqb4basuhSbCQbfUXAfp71Tbr1E2Yi7tkJeMqdEabjavuHFZj9oJ3TJyMGJssw4w1pdg8HVT1Xjx'),
            'publickey' => base58_decode('42uogHha8jzG8idNGZvNpZDEzcRZustuTxk6SKKrgEpr')
        ];

        $signature = $account->sign("hello");

        $this->assertEquals(
            Binary::fromBase58('2bu6zVLVJCtjuhiAmHiKhBHcvE9rQPCwDgMMwbQdEKfAZUYTp3DemCNteFAAFjZZFwnM2yKjCNx2uudMkQ8Jamp'),
            $signature
        );

        $this->assertFalse($account->verify("hello", $signature));
    }

    public function testSignAndVerifyHash()
    {
        $message = 'hello';
        $hash = hash('sha256', $message, true);
        $signature = $this->account->sign($hash);

        $this->assertEquals(
            Binary::fromBase58('5HHgRNtbEPRDok5JwBkP7YLDje6oJfJkpGtrxCB7WNKexxc1MqdXdhsDoETBmLD7XDNnhdeW73eLS7s2ApAR624H'),
            $signature
        );

        $this->account->verify($hash, $signature);
    }

    public function testVerifyLtoRequest()
    {
        $account = new Account($this->account->getCryptography());

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

        $signatureMsg = $account->sign($msg);
        $this->assertEquals( Binary::fromBase64('qegUJyHIOASGArXct2F2me9p4ebCTkxHJSDCc+niAb9A+1Pl/hiz5cHTkzQ4ddWUTeBLknYN3FueNqOAIGFRBg=='), $signatureMsg);

        $this->assertTrue($account->verify($msg, $signatureMsg));

        $hash = Binary::hash('sha256', $msg);
        $signatureHash = $account->sign($hash);
        $this->assertTrue($account->verify($hash, $signatureHash));
    }

    public function verifyProvider()
    {
        return [
            'string' => ['hello'],
            'Binary' => [new Binary('hello')],
        ];
    }

    /**
     * @dataProvider verifyProvider
     */
    public function testVerify($message)
    {
        $signature = Binary::fromBase58('5i9gBaHwg9UFPuwU63LBdBR29yZdRDstWM9z7oo8GzevWhBdAAWwCSRUQbPLaCT3nFgjbQuuWxVQckzCd3CoFig4');

        $this->assertTrue($this->account->verify($message, $signature));
    }

    public function testVerifyFail()
    {
        $signature = Binary::fromBase58('5i9gBaHwg9UFPuwU63LBdBR29yZdRDstWM9z7oo8GzevWhBdAAWwCSRUQbPLaCT3nFgjbQuuWxVQckzCd3CoFig4');

        $this->assertFalse($this->account->verify('not this', $signature));
    }

    public function testVerifyWithoutPublicKey()
    {
        $this->account->sign = null;

        $signature = Binary::fromBase58('5i9gBaHwg9UFPuwU63LBdBR29yZdRDstWM9z7oo8GzevWhBdAAWwCSRUQbPLaCT3nFgjbQuuWxVQckzCd3CoFig4');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Unable to verify message; no public sign key");

        $this->account->verify('hello', $signature);
    }

    public function testVerifyInvalid()
    {
        $signature = new Binary('not a real signature');

        $this->assertFalse($this->account->verify('hello', $signature));
    }


    public function testSignEvent()
    {
        $event = $this->createMock(Event::class);
        $event->expects($this->once())->method('signWith')->with($this->account)->willReturnSelf();

        $ret = $this->account->signEvent($event);
        $this->assertEquals($event, $ret);
    }

    public function testSignTransaction()
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())->method('signWith')->with($this->account)->willReturnSelf();

        $ret = $this->account->signTransaction($transaction);
        $this->assertEquals($transaction, $ret);
    }

    public function testSponsorTransaction()
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())->method('sponsorWith')->with($this->account)->willReturnSelf();

        $ret = $this->account->sponsorTransaction($transaction);
        $this->assertEquals($transaction, $ret);
    }


    public function createSecondaryAccount()
    {
        $account = new Account($this->account->getCryptography());

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

        $this->assertEquals('2246pmtzDem9GB15GmtULMXB7Vrr1wciQcHsQvrUsmapeaBQzqHNUcS4KYYu7D', $cyphertext);
    }

    public function testEncryptForWithoutPrivateKey()
    {
        $recipient = $this->createSecondaryAccount();

        $this->account->encrypt->secretkey = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Unable to encrypt message; no secret encryption key");

        $this->account->encryptFor($recipient, 'hello');
    }

    public function testEncryptForWithoutRecipientPublicKey()
    {
        $recipient = $this->createSecondaryAccount();
        $recipient->encrypt = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Unable to encrypt message; no public encryption key for recipient");

        $this->account->encryptFor($recipient, 'hello');
    }

    public function testDecryptFrom()
    {
        $recipient = $this->createSecondaryAccount();
        $cyphertext = Binary::fromBase58('2246pmtzDem9GB15GmtULMXB7Vrr1wciQcHsQvrUsmapeaBQzqHNUcS4KYYu7D');

        $message = $recipient->decryptFrom($this->account, $cyphertext);

        $this->assertEquals('hello', $message);
    }

    public function testDecryptFromWithoutPrivateKey()
    {
        $recipient = $this->createSecondaryAccount();
        $recipient->encrypt->secretkey = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Unable to decrypt message; no secret encryption key");

        $cyphertext = Binary::fromBase58('2246pmtzDem9GB15GmtULMXB7Vrr1wciQcHsQvrUsmapeaBQzqHNUcS4KYYu7D');

        $recipient->decryptFrom($this->account, $cyphertext);
    }

    public function testDecryptFromWithoutSenderPublicKey()
    {
        $recipient = $this->createSecondaryAccount();

        $this->account->encrypt = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Unable to decrypt message; no public encryption key for sender");

        $cyphertext = Binary::fromBase58('2246pmtzDem9GB15GmtULMXB7Vrr1wciQcHsQvrUsmapeaBQzqHNUcS4KYYu7D');

        $recipient->decryptFrom($this->account, $cyphertext);
    }

    /**
     * Try to decrypt a message with your own keys.
     */
    public function testDecryptFromFail()
    {
        $this->expectException(\LTO\DecryptException::class);

        $cyphertext = Binary::fromBase58('2246pmtzDem9GB15GmtULMXB7Vrr1wciQcHsQvrUsmapeaBQzqHNUcS4KYYu7D');

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
