<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\Account;
use LTO\AccountFactory;
use LTO\Binary;
use LTO\PublicNode;
use LTO\Tests\CustomAsserts;
use LTO\Transaction;
use LTO\Transaction\Transfer;
use PHPUnit\Framework\TestCase;
use function LTO\decode;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\Transfer
 * @covers \LTO\Transaction\Pack\TransferV2
 * @covers \LTO\Transaction\Pack\TransferV3
 */
class TransferTest extends TestCase
{
    use CustomAsserts;

    protected Account $account;
    protected string $recpient = "3NACnKFVN2DeFYjspHKfa2kvDqnPkhjGCD2";
    protected int $amount = 100000000;
    protected string $attachment = "hello";

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
        $transaction = new Transfer($this->recpient, 10000, $attachment);

        $this->assertEquals(3, $transaction->version);
        $this->assertEquals(10000, $transaction->amount);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals($this->recpient, $transaction->recipient);

        $this->assertInstanceOf(Binary::class, $transaction->attachment);
        $this->assertEquals((string)$attachment, (string)$transaction->attachment);
    }

    public function testConstructInvalidAmount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid amount; should be greater than 0");

        new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', -100);
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

        new Transfer($recipient, 10000);
    }

    public function versionProvider()
    {
        return [
            'v2' => [2, '3mpAPSojckMbTLycyr8EVtnHprFrM9wT5rzdQGt6tZy498ZFMZ3G4HEKbuNrFHzGG2ta8Nb9gqKyeoYUExFUue9H3NxT3Xj73GS3wXT2h2cAueFj4P4vfJf4U5x2'],
            'v3' => [3, 'w5iNQ3SBaqD2bQyGfEd997ZhxsLG9WukRtCvnXxJEFJtWnDUXJwk4xy8WGwXehKqScok56yArNFgSFVeWdyAXncmEX8PxLGpJQyN1B6A7gS9Wh81G8nNa6tQKJ7Qte'],
        ];
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinary(int $version, string $binary)
    {
        $transaction = new Transfer($this->recpient, $this->amount, $this->attachment);
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
        $transaction = new Transfer($this->recpient, $this->amount, $this->attachment);
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
        $transaction = new Transfer($this->recpient, $this->amount, $this->attachment);
        $transaction->version = $version;
        $transaction->sender = $this->account->getAddress();
        $transaction->senderPublicKey = $this->account->getPublicSignKey();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new Transfer($this->recpient, $this->amount, $this->attachment);
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported transfer tx version 99");

        $transaction->toBinary();
    }


    /**
     * @dataProvider versionProvider
     */
    public function testSign(int $version)
    {
        $transaction = new Transfer($this->recpient, $this->amount, $this->attachment);
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
            'type' => 4,
            'version' => 2,
            'sender' => '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM',
            'senderKeyType' => 'ed25519',
            'senderPublicKey' => '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7',
            'fee' => 100000000,
            'timestamp' => 1609773456000,
            'amount' => 120000000,
            'recipient' => '3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh',
            'attachment' => 'Cn8eVZg',
            'proofs' => [
                '57Ysp2ugieiidpiEtutzyfJkEugxG43UXXaKEqzU3c2oLmN8qd3hzEFQoNL93R1SvyXemnnTBNtfhfCM2PenmQqa',
            ],
        ];

        return [
            'new' => [$data, null, null],
            'no senderKeyType' => [array_diff_key($data, ['senderKeyType' => null]), null, null],
            'unconfirmed' => [$data, '7cCeL1qwd9i6u8NgMNsQjBPxVhrME2BbfZMT1DF9p4Yi', null],
            'confirmed' => [$data, '7cCeL1qwd9i6u8NgMNsQjBPxVhrME2BbfZMT1DF9p4Yi', 1215007],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFromData(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        /** @var Transfer $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(Transfer::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->sender);
        $this->assertEquals('ed25519', $transaction->senderKeyType);
        $this->assertEquals('7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7', $transaction->senderPublicKey);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals(1609773456000, $transaction->timestamp);
        $this->assertEquals(120000000, $transaction->amount);
        $this->assertEquals('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', $transaction->recipient);
        $this->assertEquals(new Binary('hello'), $transaction->attachment);
        $this->assertEquals(
            ['57Ysp2ugieiidpiEtutzyfJkEugxG43UXXaKEqzU3c2oLmN8qd3hzEFQoNL93R1SvyXemnnTBNtfhfCM2PenmQqa'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: recipient, amount, attachment, version, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => Transfer::TYPE]);
    }

    public function testFromDataWithMissingSponsorKeys()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['sponsor'] = '3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: sponsorPublicKey");

        Transaction::fromData($data);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 4");

        Transfer::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];
        $data += ['senderKeyType' => 'ed25519'];

        $transaction = new Transfer('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', 120000000);
        $transaction->id = $id;
        $transaction->version = 2;
        $transaction->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
        $transaction->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
        $transaction->fee = 100000000;
        $transaction->timestamp = 1609773456000;
        $transaction->attachment = new Binary('hello');
        $transaction->proofs[] = '57Ysp2ugieiidpiEtutzyfJkEugxG43UXXaKEqzU3c2oLmN8qd3hzEFQoNL93R1SvyXemnnTBNtfhfCM2PenmQqa';
        $transaction->height = $height;

        $this->assertEqualsAsJson($data, $transaction);
    }

    public function testBroadcast()
    {
        $transaction = new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);

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
