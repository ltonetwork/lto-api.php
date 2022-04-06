<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\Account;
use LTO\AccountFactory;
use LTO\Binary;
use LTO\PublicNode;
use LTO\Tests\CustomAsserts;
use LTO\Transaction;
use LTO\Transaction\SetScript;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\SetScript
 * @covers \LTO\Transaction\Pack\SetScriptV1
 * @covers \LTO\Transaction\Pack\SetScriptV3
 */
class SetScriptTest extends TestCase
{
    use CustomAsserts;

    protected const COMPILE_SCRIPT = "AQkAAfQAAAADCAUAAAACdHgAAAAJYm9keUJ5dGVzCQABkQAAAAIIBQAAAAJ0eAAAAAZwcm9vZnMAAAAAAAAAAAAIBQAAAAJ0eAAAAA9zZW5kZXJQdWJsaWNLZXmmsz2x";

    protected Account $account;

    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed('test');
    }

    public function prefixProvider()
    {
        return [
            '{script}' => [''],
            'base64:{script}' => ['base64:'],
        ];
    }

    /**
     * @dataProvider prefixProvider
     */
    public function testConstruct(string $prefix)
    {
        $transaction = new SetScript($prefix . self::COMPILE_SCRIPT);

        $this->assertEquals(3, $transaction->version);
        $this->assertEquals(500000000, $transaction->fee);
        $this->assertEquals('base64:' . self::COMPILE_SCRIPT, $transaction->script);
    }

    public function versionProvider()
    {
        return [
            'v1' => [1, 'j54dTjvtQHLzxqfHkNRPR8WTZFUWfnjxFfYRyA6Vu7SKCttPBQG2zk7c594Kihsa2hrJb9Cr7AKrtmHcycsntv29YgX2dbXn5bhWNF2ZcjLQ486qy6FTixt3sk1RqjAPeJPRRA3rNq2NGutSApvjuyW5yS2Wp5SLD57dXNY695tiHUHNM6mZHewYibo5FXAd8fF5'],
            'v3' => [3, '2bXLHWotshX1ZvrzMgQoZ75J6XWVv3TdHnaGgQKcxdW4PriTbzgDwWvuWJupe9iu2tKwvbDSs5kGNiXwf15iHFiz9P4MREur12foF2EBHHcxEscQeeYAd1KU14dVKFwCei66QstfcwArNaeg779pAQj4aCdFDx8L2F3ZLSNJ9r83mowFh14ajKffnUdNvk4SncpebCNnP8nv4'],
        ];
    }

    public function versionNoScriptProvider()
    {
        return [
            'v1' => [1, '2RNtCy4uufAKDhzZmFZPch3QzYfu67VsfKggdEw2uYRdsqxAa3CdQnAGycuJbqH'],
            'v3' => [3, '23rTaytvD2Ed4ZACzp9AXY6yV42jgZkFowuWwvhUPQNim58EYLyckU3GkLAyYKTy5C2iBjSBJw'],
        ];
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinary(int $version, string $binary)
    {
        $transaction = new SetScript(self::COMPILE_SCRIPT);
        $transaction->version = $version;
        $transaction->sender = $this->account->getAddress();
        $transaction->senderPublicKey = $this->account->getPublicSignKey();
        $transaction->timestamp = strtotime('2018-03-01T00:00:00+00:00') * 1000;

        $this->assertEqualsBase58($binary, $transaction->toBinary());
    }

    /**
     * @dataProvider versionNoScriptProvider
     */
    public function testToBinaryNoScript(int $version, string $binary)
    {
        $transaction = new SetScript(null);
        $transaction->version = $version;
        $transaction->sender = $this->account->getAddress();
        $transaction->senderPublicKey = $this->account->getPublicSignKey();
        $transaction->timestamp = strtotime('2018-03-01T00:00:00+00:00') * 1000;

        $this->assertEqualsBase58($binary, $transaction->toBinary());
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinaryNoSender(int $version)
    {
        $transaction = new SetScript(self::COMPILE_SCRIPT);
        $transaction->version = $version;
        $transaction->timestamp = strtotime('2018-03-01T00:00:00+00:00') * 1000;

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Sender public key not set");

        $transaction->toBinary();
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinaryNoTimestamp(int $version)
    {
        $transaction = new SetScript(self::COMPILE_SCRIPT);
        $transaction->version = $version;
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new SetScript(self::COMPILE_SCRIPT);
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported set script tx version 99");

        $transaction->toBinary();
    }

    /**
     * @dataProvider versionProvider
     */
    public function testSign(int $version)
    {
        $transaction = new SetScript(self::COMPILE_SCRIPT);
        $transaction->version = $version;

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals($this->account->getAddress(), $transaction->sender);
        $this->assertEquals($this->account->getPublicSignKey(), $transaction->senderPublicKey);

        $this->assertTimestampIsNow($transaction->timestamp);

        $this->assertTrue(
            $this->account->verify($transaction->toBinary(), Binary::fromBase58($transaction->proofs[0]))
        );
    }

    public function testSignNoScript()
    {
        $transaction = new SetScript(null);

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals($this->account->getAddress(), $transaction->sender);
        $this->assertEquals($this->account->getPublicSignKey(), $transaction->senderPublicKey);

        $this->assertTimestampIsNow($transaction->timestamp);

        $this->assertTrue(
            $this->account->verify($transaction->toBinary(), Binary::fromBase58($transaction->proofs[0]))
        );
    }

    public function dataProvider()
    {
        $data = [
            "type" => 13,
            "version" => 1,
            "script" => "base64:" . self::COMPILE_SCRIPT,
            "sender" => "3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM",
            'senderKeyType' => 'ed25519',
            "senderPublicKey" => "7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7",
            "timestamp" => 1610148915000,
            "fee" => 100000000,
            "proofs" => [
                "5MXTj9WfF3nWeJe5VCqRamXexhRR3sJxbSDbvtSzaFaPSdD6RYpDU4BfDEYaSzwfsTgp4iUfLhevpVxZr6yTdUYs"
            ],
        ];

        return [
            'new' => [$data, null, null],
            'unconfirmed' => [$data, 'AfanxjNfgtdmaJ4bz4dDg5e5ELUvXtRnuWe6Q49K6u3v', null],
            'confirmed' => [$data, 'AfanxjNfgtdmaJ4bz4dDg5e5ELUvXtRnuWe6Q49K6u3v', 1221375],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFromData(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        /** @var SetScript $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(SetScript::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->sender);
        $this->assertEquals('7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7', $transaction->senderPublicKey);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals(1610148915000, $transaction->timestamp);
        $this->assertEquals('base64:' . self::COMPILE_SCRIPT, $transaction->script);
        $this->assertEquals(
            ['5MXTj9WfF3nWeJe5VCqRamXexhRR3sJxbSDbvtSzaFaPSdD6RYpDU4BfDEYaSzwfsTgp4iUfLhevpVxZr6yTdUYs'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: version, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => SetScript::TYPE]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 13");

        SetScript::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new SetScript(self::COMPILE_SCRIPT);
        $transaction->id = $id;
        $transaction->version = 1;
        $transaction->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
        $transaction->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
        $transaction->fee = 100000000;
        $transaction->timestamp = 1610148915000;
        $transaction->proofs[] = '5MXTj9WfF3nWeJe5VCqRamXexhRR3sJxbSDbvtSzaFaPSdD6RYpDU4BfDEYaSzwfsTgp4iUfLhevpVxZr6yTdUYs';
        $transaction->height = $height;

        $this->assertEquals($data, $transaction->jsonSerialize());
    }

    public function testBroadcast()
    {
        $transaction = new SetScript(self::COMPILE_SCRIPT);

        $broadcastedTransaction = clone $transaction;
        $broadcastedTransaction->id = 'AfanxjNfgtdmaJ4bz4dDg5e5ELUvXtRnuWe6Q49K6u3v';

        $node = $this->createMock(PublicNode::class);
        $node->expects($this->once())->method('broadcast')
            ->with($this->identicalTo($transaction))
            ->willReturn($broadcastedTransaction);

        $ret = $transaction->broadcastTo($node);

        $this->assertSame($broadcastedTransaction, $ret);
    }
}
