<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\AccountFactory;
use LTO\PublicNode;
use LTO\Transaction;
use LTO\Transaction\Lease;
use PHPUnit\Framework\TestCase;
use function LTO\decode;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\Lease
 * @covers \LTO\Transaction\Pack\LeaseV2
 */
class LeaseTest extends TestCase
{
    protected const ACCOUNT_SEED = "df3dd6d884714288a39af0bd973a1771c9f00f168cf040d6abb6a50dd5e055d8";

    /** @var \LTO\Account */
    protected $account;

    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed(self::ACCOUNT_SEED);
    }

    public function testConstruct()
    {
        $transaction = new Lease('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);

        $this->assertEquals(10000, $transaction->amount);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', $transaction->recipient);
    }

    public function testConstructInvalidAmount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid amount; should be greater than 0");

        new Lease('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', -100);
    }

    public function invalidRecipientProvider()
    {
        return [
            'test' => ['test'],
            'hello' => ['hello'],
            'raw' => [decode('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 'base58')],
        ];
    }

    /**
     * @dataProvider invalidRecipientProvider
     */
    public function testConstructInvalidRecipient($recipient)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid recipient address; is it base58 encoded?");

        new Lease($recipient, 10000);
    }


    public function testCancel()
    {
        $lease = new Lease('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
        $lease->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();
        $lease->signWith($this->account);

        $cancelLease = $lease->cancel();

        $this->assertInstanceOf(Transaction\CancelLease::class, $cancelLease);
        $this->assertEquals($lease->getId(), $cancelLease->leaseId);
        $this->assertSame($lease, $cancelLease->lease);
    }



    public function testToBinaryNoSender()
    {
        $transaction = new Lease('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Sender public key not set");

        $transaction->toBinary();
    }

    public function testToBinaryNoTimestamp()
    {
        $transaction = new Lease('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new Lease('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported lease tx version 99");

        $transaction->toBinary();
    }


    public function testSign()
    {
        $transaction = new Lease('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);
        $this->assertEquals('2A8jVZinijix8PvYZS3bPHbbYRA7yDJhSU1czEDzmeMq3ewpiRTPWhDtEPUG9tK7BSmv2Xo1VCzSAChsEn9cNTrF', $transaction->proofs[0]);

        // Unchanged
        $this->assertEquals((new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(), $transaction->timestamp);

        $this->assertTrue($this->account->verify($transaction->proofs[0], $transaction->toBinary()));
    }

    public function dataProvider()
    {
        $data = [
            "type" => 8,
            "version" => 2,
            "amount" => 120000000,
            "recipient" => "3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh",
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

        /** @var Lease $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(Lease::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->sender);
        $this->assertEquals('7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7', $transaction->senderPublicKey);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals(1610148915000, $transaction->timestamp);
        $this->assertEquals(120000000, $transaction->amount);
        $this->assertEquals('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', $transaction->recipient);
        $this->assertEquals(
            ['5MXTj9WfF3nWeJe5VCqRamXexhRR3sJxbSDbvtSzaFaPSdD6RYpDU4BfDEYaSzwfsTgp4iUfLhevpVxZr6yTdUYs'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: amount, recipient, version, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => Lease::TYPE]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 8");

        Lease::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new Lease('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', 120000000);
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
        $transaction = new Lease('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);

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
