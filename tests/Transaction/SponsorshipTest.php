<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\Account;
use LTO\AccountFactory;
use LTO\Binary;
use LTO\PublicNode;
use LTO\Tests\CustomAsserts;
use LTO\Transaction;
use LTO\Transaction\Sponsorship;
use PHPUnit\Framework\TestCase;
use function LTO\decode;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\AbstractSponsorship
 * @covers \LTO\Transaction\Sponsorship
 * @covers \LTO\Transaction\Pack\SponsorshipV1
 * @covers \LTO\Transaction\Pack\SponsorshipV3
 */
class SponsorshipTest extends TestCase
{
    use CustomAsserts;

    protected Account $account;
    protected string $recipient = "3NACnKFVN2DeFYjspHKfa2kvDqnPkhjGCD2";

    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed('test');
    }

    public function testConstruct()
    {
        $transaction = new Sponsorship($this->recipient);

        $this->assertEquals(500000000, $transaction->fee);
        $this->assertEquals($this->recipient, $transaction->recipient);
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

        new Sponsorship($recipient);
    }


    public function versionProvider()
    {
        return [
            'v1' => [1, '8gCPwExgz2sxHQ5nMn2tsgwvPfMtaXvms3bZVA6yiK9iLHdPRWSqp9SfGyx7x5AnWB1DdZyTEERsu1z8ofPFwowd532zRxD4LQjfL3Lcf'],
            'v3' => [3, 'atrk25dhE856q2qSEPm6umwL1kPTiZ7CtCojL7SRSnYYWqM24jtQFffmWP3n9vt3VPQ5cahaMoWuYjYAVvA7hA6apBwSrNyf2DhFYuXwME'],
        ];
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinaryNoSender(int $version)
    {
        $transaction = new Sponsorship($this->recipient);
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
        $transaction = new Sponsorship($this->recipient);
        $transaction->version = $version;
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new Sponsorship($this->recipient);
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported sponsorship tx version 99");

        $transaction->toBinary();
    }

    /**
     * @dataProvider versionProvider
     */
    public function testSign(int $version)
    {
        $transaction = new Sponsorship($this->recipient);
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

    public function dataProvider()
    {
        $data = [
            "type" => 18,
            "version" => 1,
            "recipient" => "3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh",
            "sender" => "3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM",
            'senderKeyType' => 'ed25519',
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

        /** @var Sponsorship $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(Sponsorship::class, $transaction);

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
        $this->expectExceptionMessage("Invalid data, missing keys: recipient, version, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => Sponsorship::TYPE]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 18");

        Sponsorship::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new Sponsorship('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh');
        $transaction->id = $id;
        $transaction->version = 1;
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
        $transaction = new Sponsorship($this->recipient);

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
