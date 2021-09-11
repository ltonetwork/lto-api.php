<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\AccountFactory;
use LTO\PublicNode;
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
    protected const ACCOUNT_SEED = "df3dd6d884714288a39af0bd973a1771c9f00f168cf040d6abb6a50dd5e055d8";

    /** @var \LTO\Account */
    protected $account;

    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed(self::ACCOUNT_SEED);
    }

    public function testConstruct()
    {
        $transaction = new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);

        $this->assertEquals(10000, $transaction->amount);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', $transaction->recipient);
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

    public function attachmentProvider()
    {
        return [
            'raw' => ["Hello", 'raw'],
            'hex' => [bin2hex("Hello"), 'hex'],
            'base58' => [base58_encode("Hello"), 'base58'],
            'base64' => [base64_encode("Hello"), 'base64'],
        ];
    }

    /**
     * @dataProvider attachmentProvider
     */
    public function testSetAttachment(string $message, string $encoding)
    {
        $transaction = new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
        $transaction->setAttachment($message, $encoding);

        $this->assertEquals('9Ajdvzr', $transaction->attachment);
    }


    public function versionProvider()
    {
        return [
            'v2' => [2],
            'v3' => [3],
        ];
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinaryNoSender(int $version)
    {
        $transaction = new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
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
        $transaction = new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
        $transaction->version = $version;
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
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
        $transaction = new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
        $transaction->version = $version;
        $transaction->timestamp = strtotime('2018-03-01T00:00:00+00:00') * 1000;

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);

        // Unchanged
        $this->assertEquals(strtotime('2018-03-01T00:00:00+00:00') * 1000, $transaction->timestamp);

        $this->assertTrue($this->account->verify($transaction->proofs[0], $transaction->toBinary()));
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
            'attachment' => '9Ajdvzr',
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
        $this->assertEquals('9Ajdvzr', $transaction->attachment);
        $this->assertEquals(
            ['57Ysp2ugieiidpiEtutzyfJkEugxG43UXXaKEqzU3c2oLmN8qd3hzEFQoNL93R1SvyXemnnTBNtfhfCM2PenmQqa'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: amount, recipient, attachment, version, sender, senderPublicKey, timestamp, fee, proofs");

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
        $transaction->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
        $transaction->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
        $transaction->fee = 100000000;
        $transaction->timestamp = 1609773456000;
        $transaction->attachment = '9Ajdvzr';
        $transaction->proofs[] = '57Ysp2ugieiidpiEtutzyfJkEugxG43UXXaKEqzU3c2oLmN8qd3hzEFQoNL93R1SvyXemnnTBNtfhfCM2PenmQqa';
        $transaction->height = $height;

        $this->assertEquals($data, $transaction->jsonSerialize());
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
