<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\AccountFactory;
use LTO\PublicNode;
use LTO\Transaction;
use LTO\Transaction\Sponsor;
use PHPUnit\Framework\TestCase;
use function LTO\decode;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\Sponsor
 */
class SponsorTest extends TestCase
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
        $transaction = new Sponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');

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

        new Sponsor($recipient);
    }


    public function testToBinaryNoSender()
    {
        $transaction = new Sponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Sender public key not set");

        $transaction->toBinary();
    }

    public function testToBinaryNoTimestamp()
    {
        $transaction = new Sponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }


    public function testSign()
    {
        $transaction = new Sponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);
        $this->assertEquals(
            '5VDwGh6jLb6JccuK7tYNjb5TzVZQRdJN1SJv7RtjroCmSWPWtpfiEUjEmkzKDYmc4j6iEygQwNBdRXXAwacF1t4h',
            $transaction->proofs[0]
        );

        // Unchanged
        $this->assertEquals((new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(), $transaction->timestamp);

        $this->assertTrue($this->account->verify($transaction->proofs[0], $transaction->toBinary()));
    }

    public function dataProvider()
    {
        $data = [
            "type" => 18,
            "version" => 1,
            "recipient" => "3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh",
            "sender" => "3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM",
            "senderPublicKey" => "7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7",
            "timestamp" => 1610149399000,
            "fee" => 500000000,
            "proofs" => [
                "4xhvehfKqRtm5LjEMBuPGMZYJ4mwRxYA4fYBGy1S7aZCT5z8Cx2q62z1rbJPv4sFJycLbFMwnV2jRuXDkiZe1kkh"
            ],
        ];

        return [
            'new' => [$data, null, null],
            'unconfirmed' => [$data, '4jxUkX9nrzCqgBtanTYmdwrEYYXzBkCSENT4sd4Q896W', null],
            'confirmed' => [$data, '4jxUkX9nrzCqgBtanTYmdwrEYYXzBkCSENT4sd4Q896W', 1221378],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFromData(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        /** @var Sponsor $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(Sponsor::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->sender);
        $this->assertEquals('7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7', $transaction->senderPublicKey);
        $this->assertEquals(500000000, $transaction->fee);
        $this->assertEquals(1610149399000, $transaction->timestamp);
        $this->assertEquals('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', $transaction->recipient);
        $this->assertEquals(
            ['4xhvehfKqRtm5LjEMBuPGMZYJ4mwRxYA4fYBGy1S7aZCT5z8Cx2q62z1rbJPv4sFJycLbFMwnV2jRuXDkiZe1kkh'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: recipient, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => 18]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 18");

        Sponsor::fromData($data);
    }

    public function testFromDataWithIncorrectVersion()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['version'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid version 99, should be 1");

        Sponsor::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new Sponsor('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh');
        $transaction->id = $id;
        $transaction->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
        $transaction->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
        $transaction->fee = 500000000;
        $transaction->timestamp = 1610149399000;
        $transaction->proofs[] = '4xhvehfKqRtm5LjEMBuPGMZYJ4mwRxYA4fYBGy1S7aZCT5z8Cx2q62z1rbJPv4sFJycLbFMwnV2jRuXDkiZe1kkh';
        $transaction->height = $height;

        $this->assertEquals($data, $transaction->jsonSerialize());
    }

    public function testBroadcast()
    {
        $transaction = new Sponsor('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');

        $broadcastedTransaction = clone $transaction;
        $broadcastedTransaction->id = '4jxUkX9nrzCqgBtanTYmdwrEYYXzBkCSENT4sd4Q896W';

        $node = $this->createMock(PublicNode::class);
        $node->expects($this->once())->method('broadcast')
            ->with($this->identicalTo($transaction))
            ->willReturn($broadcastedTransaction);

        $ret = $transaction->broadcastTo($node);

        $this->assertSame($broadcastedTransaction, $ret);
    }
}
