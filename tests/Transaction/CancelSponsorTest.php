<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\AccountFactory;
use LTO\PublicNode;
use LTO\Transaction;
use LTO\Transaction\CancelSponsor;
use PHPUnit\Framework\TestCase;
use function LTO\decode;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\CancelSponsor
 * @covers \LTO\Transaction\Pack\CancelSponsorV1
 */
class CancelSponsorTest extends TestCase
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
        $transaction = new CancelSponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');

        $this->assertEquals(500000000, $transaction->fee);
        $this->assertEquals('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', $transaction->recipient);
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

        new CancelSponsor($recipient);
    }


    public function testToBinaryNoSender()
    {
        $transaction = new CancelSponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Sender public key not set");

        $transaction->toBinary();
    }

    public function testToBinaryNoTimestamp()
    {
        $transaction = new CancelSponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new CancelSponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported cancel sponsor tx version 99");

        $transaction->toBinary();
    }


    public function testSign()
    {
        $transaction = new CancelSponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);
        $this->assertEquals(
            '2AKUBja93hF8AC2ee21m9AtedomXZNQG5J3FZMU85avjKF9B8CL45RWyXkXEeYb13r1AhpSzRvcudye39xggtDHv',
            $transaction->proofs[0]
        );

        // Unchanged
        $this->assertEquals((new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(), $transaction->timestamp);

        $this->assertTrue($this->account->verify($transaction->proofs[0], $transaction->toBinary()));
    }

    public function dataProvider()
    {
        $data = [
            "type" => 19,
            "version" => 1,
            "recipient" => "3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh",
            "sender" => "3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM",
            "senderPublicKey" => "7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7",
            "timestamp" => 1610152569000,
            "fee" => 500000000,
            "proofs" => [
                "ZJ77G5rBQb6uVKnpBVV6WPUe48yXsES8tjEi8BpwPPxjBvnhwY9jFVXyoqg5js1PDVyrXakUGqeT9d4otSrcjYy"
            ],
        ];

        return [
            'new' => [$data, null, null],
            'unconfirmed' => [$data, 'AdWugi2xJjPzVezP28oJXenn6uGw7V7sWgJ9TiNEsFjj', null],
            'confirmed' => [$data, 'AdWugi2xJjPzVezP28oJXenn6uGw7V7sWgJ9TiNEsFjj', 1221437],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFromData(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        /** @var CancelSponsor $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(CancelSponsor::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->sender);
        $this->assertEquals('7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7', $transaction->senderPublicKey);
        $this->assertEquals(500000000, $transaction->fee);
        $this->assertEquals(1610152569000, $transaction->timestamp);
        $this->assertEquals('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', $transaction->recipient);
        $this->assertEquals(
            ['ZJ77G5rBQb6uVKnpBVV6WPUe48yXsES8tjEi8BpwPPxjBvnhwY9jFVXyoqg5js1PDVyrXakUGqeT9d4otSrcjYy'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: recipient, version, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => CancelSponsor::TYPE]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 19");

        CancelSponsor::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new CancelSponsor('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh');
        $transaction->id = $id;
        $transaction->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
        $transaction->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
        $transaction->fee = 500000000;
        $transaction->timestamp = 1610152569000;
        $transaction->proofs[] = 'ZJ77G5rBQb6uVKnpBVV6WPUe48yXsES8tjEi8BpwPPxjBvnhwY9jFVXyoqg5js1PDVyrXakUGqeT9d4otSrcjYy';
        $transaction->height = $height;

        $this->assertEquals($data, $transaction->jsonSerialize());
    }

    public function testBroadcast()
    {
        $transaction = new CancelSponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');

        $broadcastedTransaction = clone $transaction;
        $broadcastedTransaction->id = 'AdWugi2xJjPzVezP28oJXenn6uGw7V7sWgJ9TiNEsFjj';

        $node = $this->createMock(PublicNode::class);
        $node->expects($this->once())->method('broadcast')
            ->with($this->identicalTo($transaction))
            ->willReturn($broadcastedTransaction);

        $ret = $transaction->broadcastTo($node);

        $this->assertSame($broadcastedTransaction, $ret);
    }
}
