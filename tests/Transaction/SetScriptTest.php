<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\AccountFactory;
use LTO\PublicNode;
use LTO\Transaction;
use LTO\Transaction\SetScript;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\SetScript
 * @covers \LTO\Transaction\Pack\SetScriptV1
 */
class SetScriptTest extends TestCase
{
    protected const ACCOUNT_SEED = "df3dd6d884714288a39af0bd973a1771c9f00f168cf040d6abb6a50dd5e055d8";
    protected const COMPILE_SCRIPT = "AQkAAfQAAAADCAUAAAACdHgAAAAJYm9keUJ5dGVzCQABkQAAAAIIBQAAAAJ0eAAAAAZwcm9vZnMAAAAAAAAAAAAIBQAAAAJ0eAAAAA9zZW5kZXJQdWJsaWNLZXmmsz2x";

    /** @var \LTO\Account */
    protected $account;

    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed(self::ACCOUNT_SEED);
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

        $this->assertEquals('base64:' . self::COMPILE_SCRIPT, $transaction->script);
    }

    public function testToBinaryNoSender()
    {
        $transaction = new SetScript(self::COMPILE_SCRIPT);
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Sender public key not set");

        $transaction->toBinary();
    }

    public function testToBinaryNoTimestamp()
    {
        $transaction = new SetScript(self::COMPILE_SCRIPT);
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


    public function testSign()
    {
        $transaction = new SetScript(self::COMPILE_SCRIPT);
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);
        $this->assertEquals('jxW9T2iUSQ68yv41Wj8JKb8HykwzKzbuHLBG6eySLaXk45rNbDo3zr2AS9bGMggrBZUUJQTFjKHeiD1q69pPUxY', $transaction->proofs[0]);

        // Unchanged
        $this->assertEquals((new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(), $transaction->timestamp);

        $this->assertTrue($this->account->verify($transaction->proofs[0], $transaction->toBinary()));
    }

    public function testSignNullScript()
    {
        $transaction = new SetScript(null);
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);
        $this->assertEquals('4reQnJyELshRrsWtRF7pXP2n2uKF6T7y4jwmqYRo3trJtRJXcvkLc2ZDf6w4VCWAknr5SS1uUgLW8pkGdDHmpMU3', $transaction->proofs[0]);

        // Unchanged
        $this->assertEquals((new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(), $transaction->timestamp);

        $this->assertTrue($this->account->verify($transaction->proofs[0], $transaction->toBinary()));
    }

    public function dataProvider()
    {
        $data = [
            "type" => 13,
            "version" => 1,
            "script" => "base64:" . self::COMPILE_SCRIPT,
            "sender" => "3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM",
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
