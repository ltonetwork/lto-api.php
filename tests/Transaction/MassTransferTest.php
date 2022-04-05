<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\Account;
use LTO\AccountFactory;
use LTO\Binary;
use LTO\PublicNode;
use LTO\Tests\CustomAsserts;
use LTO\Transaction;
use LTO\Transaction\MassTransfer;
use PHPUnit\Framework\TestCase;
use function LTO\decode;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\MassTransfer
 * @covers \LTO\Transaction\Pack\MassTransferV1
 * @covers \LTO\Transaction\Pack\MassTransferV3
 */
class MassTransferTest extends TestCase
{
    use CustomAsserts;

    protected Account $account;
    protected array $transfers = [
		["recipient" => "3NACnKFVN2DeFYjspHKfa2kvDqnPkhjGCD2", "amount" => 230000000],
        ["recipient" => "3NASW7kxCA8nRaA5axcPiQGXD82MPwLDYbT", "amount" => 370000000],
        ["recipient" => "3N2bAE4of276ekPRqsihsmFmLXq9kao6jqm", "amount" => 370000000],
    ];
    protected $attachment = "hello";

    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed('test');
    }


    public function attachmentProvider()
    {
        return [
            'none' => [''],
            'string' => ['hello'],
            'Binary' => [new Binary('hello')],
        ];
    }

    /**
     * @dataProvider attachmentProvider
     */
    public function testConstruct($attachment)
    {
        $transaction = new MassTransfer($this->transfers, $attachment);

        $this->assertEquals(3, $transaction->version);
        $this->assertEquals(130000000, $transaction->fee);
        $this->assertEquals($this->transfers, $transaction->transfers);

        $this->assertInstanceOf(Binary::class, $transaction->attachment);
        $this->assertEquals((string)$attachment, (string)$transaction->attachment);
    }

    public function testConstructEmpty()
    {
        $transaction = new MassTransfer();

        $this->assertEquals(100000000, $transaction->fee);
    }

    public function testAddTransfer()
    {
        $transaction = new MassTransfer();

        $ret = $transaction->addTransfer("3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh", 230000000);

        $this->assertSame($transaction, $ret);
        $this->assertCount(1, $transaction->transfers);
        $this->assertEquals(
            ['recipient' => "3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh", 'amount' => 230000000],
            $transaction->transfers[0]
        );
        $this->assertEquals(110000000, $transaction->fee);

        $transaction->addTransfer("3NASW7kxCA8nRaA5axcPiQGXD82MPwLDYbT", 370000000);
        $transaction->addTransfer("3N2bAE4of276ekPRqsihsmFmLXq9kao6jqm", 1100000000);

        $this->assertCount(3, $transaction->transfers);
        $this->assertEquals(
            ['recipient' => "3NASW7kxCA8nRaA5axcPiQGXD82MPwLDYbT", 'amount' => 370000000],
            $transaction->transfers[1]
        );
        $this->assertEquals(
            ['recipient' => "3N2bAE4of276ekPRqsihsmFmLXq9kao6jqm", 'amount' => 1100000000],
            $transaction->transfers[2]
        );
        $this->assertEquals(130000000, $transaction->fee);
    }

    public function testAddTransferInvalidAmount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid amount; should be greater than 0");

        $transaction = new MassTransfer();
        $transaction->addTransfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', -100);
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
    public function testAddTransferInvalidRecipient($recipient)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid recipient address; is it base58 encoded?");

        $transaction = new MassTransfer();
        $transaction->addTransfer($recipient, 10000);
    }


    public function versionProvider()
    {
        return [
            'v1' => [1, '2UdpeV2x4heuL8KBNZAGJYHXUkLqf78UKFnL8oxfwG6ihyj1uHyaeN4fNiva72VG9Ms7eJLvShgvVr89CgPyXccNcbw1PqDL27TgmUhuNcASJ4AxoCz2eqLCPSVm1x3vzS5fidzddAA5UFemFrEW6N8C8pdPGDp8wCUxdTVpRsh5nY2kjS8utdYJxFmuFRjPpYzfwb5QVbJP18ggZNEiSAvRKr2a'],
            'v3' => [3, 'Vndp3PEkc9wbYdi9JPgYTtB8XCHx5ufqR7Bmrv82Xo1p69ZfpwkNCXxLPchB6SSqNc7zufGxETomihFcbSn73WU4Aff5LFmaZJqTSgjYBkqPZBVa9exTYbLayRwr4QMNqYgPqm1bLKfY4ogrgyd62zGUyHVhra2UbbA1ZwgknxsYM85XtkmigB2WY9vzzVWYfSz47BNRZJX5t4bCM24k3DgVqsDd9G'],
        ];
    }


    /**
     * @dataProvider versionProvider
     */
    public function testToBinary(int $version, string $binary)
    {
        $transaction = new MassTransfer($this->transfers, $this->attachment);
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
        $transaction = new MassTransfer($this->transfers, $this->attachment);
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
        $transaction = new MassTransfer($this->transfers, $this->attachment);
        $transaction->version = $version;
        $transaction->sender = $this->account->getAddress();
        $transaction->senderPublicKey = $this->account->getPublicSignKey();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new MassTransfer($this->transfers, $this->attachment);
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported mass transfer tx version 99");

        $transaction->toBinary();
    }


    /**
     * @dataProvider versionProvider
     */
    public function testSign(int $version)
    {
        $transaction = new MassTransfer($this->transfers, $this->attachment);
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
            "type" => 11,
            "version" => 1,
            "transfers" => [
                [
                    "recipient" => "3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh",
                    "amount" => 230000000
                ],
                [
                    "recipient" => "3NASW7kxCA8nRaA5axcPiQGXD82MPwLDYbT",
                    "amount" => 370000000
                ],
                [
                    "recipient" => "3N2bAE4of276ekPRqsihsmFmLXq9kao6jqm",
                    "amount" => 1100000000
                ]
            ],
            "attachment" => "Cn8eVZg",
            "sender" => "3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM",
            'senderKeyType' => 'ed25519',
            "senderPublicKey" => "7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7",
            "timestamp" => 1610145916000,
            "fee" => 130000000,
            "proofs" => [
                "38WwVgPY2egb2s7q394pRVD8HSRY84JXedfg4Y4Hs8EZAumh4ekD963nRLWqsdexnhJC8Eux1qywxubsEzL1Zwpb"
            ]
        ];
        
        return [
            'new' => [$data, null, null],
            'unconfirmed' => [$data, '5Uw71ReN29dCeoGkYVfgjsx6Curfcdvqge7aUJdi9Snh', null],
            'confirmed' => [$data, '5Uw71ReN29dCeoGkYVfgjsx6Curfcdvqge7aUJdi9Snh', 1221320],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFromData(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        /** @var MassTransfer $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(MassTransfer::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->sender);
        $this->assertEquals('7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7', $transaction->senderPublicKey);
        $this->assertEquals(130000000, $transaction->fee);
        $this->assertEquals(1610145916000, $transaction->timestamp);

        $this->assertEquals(new Binary('hello'), $transaction->attachment);
        $this->assertEquals(
            ['38WwVgPY2egb2s7q394pRVD8HSRY84JXedfg4Y4Hs8EZAumh4ekD963nRLWqsdexnhJC8Eux1qywxubsEzL1Zwpb'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: transfers, attachment, version, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => MassTransfer::TYPE]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 11");

        MassTransfer::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new MassTransfer();
        $transaction->id = $id;
        $transaction->version = 1;
        $transaction->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
        $transaction->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
        $transaction->fee = 100000000;
        $transaction->timestamp = 1610145916000;
        $transaction->attachment = new Binary('hello');
        $transaction->proofs[] = '38WwVgPY2egb2s7q394pRVD8HSRY84JXedfg4Y4Hs8EZAumh4ekD963nRLWqsdexnhJC8Eux1qywxubsEzL1Zwpb';
        $transaction->height = $height;

        $transaction->addTransfer("3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh", 230000000);
        $transaction->addTransfer("3NASW7kxCA8nRaA5axcPiQGXD82MPwLDYbT", 370000000);
        $transaction->addTransfer("3N2bAE4of276ekPRqsihsmFmLXq9kao6jqm", 1100000000);

        $this->assertEqualsAsJson($data, $transaction);
    }

    public function testBroadcast()
    {
        $transaction = new MassTransfer();

        $broadcastedTransaction = clone $transaction;
        $broadcastedTransaction->id = '7cCeL1qwd9i6u8NgMNsQjBPxVhrME2BbfZMT1DF9p4Yi';

        $node = $this->createMock(PublicNode::class);
        $node->expects($this->once())->method('broadcast')
            ->with($this->identicalTo($transaction))
            ->willReturn($broadcastedTransaction);

        $ret = $transaction->broadcastTo($node);

        $this->assertSame($broadcastedTransaction, $ret);
    }
}
