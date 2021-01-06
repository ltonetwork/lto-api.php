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

    public function assertTimestampIsNow($timestamp)
    {
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan((time() - 5) * 1000, $timestamp);
    }

    public function testConstruct()
    {
        $transaction = new Transfer(10000, '3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');

        $this->assertEquals(10000, $transaction->amount);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', $transaction->recipient);
    }

    public function testConstructInvalidAmount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid amount; should be greater than 0");

        new Transfer(-100, '3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
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

        new Transfer(10000, $recipient);
    }


    public function testToBinaryNoSender()
    {
        $transaction = new Transfer(10000, '3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Sender public key not set");

        $transaction->toBinary();
    }

    public function testToBinaryNoTimestamp()
    {
        $transaction = new Transfer(10000, '3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }


    public function testSign()
    {
        $transaction = new Transfer(10000, '3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);
        $this->assertEquals('fn8c7LUg6pTEkrK9C69E8fhhkdv4jeFrB8qWKfMf51rv79p21DoytK2vH8cJKFVSWE5V2tTrXcFtxbAyg2PXbHo', $transaction->proofs[0]);

        // Unchanged
        $this->assertEquals((new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(), $transaction->timestamp);

        $this->assertTrue($this->account->verify($transaction->proofs[0], $transaction->toBinary()));
    }

    public function testSignSetTimestamp()
    {
        $transaction = (new Transfer(10000, '3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1'))
            ->signWith($this->account);

        $this->assertTimestampIsNow($transaction->timestamp);
    }

    public function testMultiSig()
    {
        $accountFactory = new AccountFactory('T');
        $account0 = $accountFactory->seed(self::ACCOUNT_SEED, 0);
        $account1 = $accountFactory->seed(self::ACCOUNT_SEED, 1);
        $account2 = $accountFactory->seed(self::ACCOUNT_SEED, 2);

        $transaction = new Transfer(10000, '3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $transaction
            ->signWith($account0)
            ->signWith($account1)
            ->signWith($account2);

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);

        $this->assertCount(3, $transaction->proofs);
        $this->assertEquals('fn8c7LUg6pTEkrK9C69E8fhhkdv4jeFrB8qWKfMf51rv79p21DoytK2vH8cJKFVSWE5V2tTrXcFtxbAyg2PXbHo', $transaction->proofs[0]);
        $this->assertEquals('4TjN2rncEDRWfmWiYpku3HVuwkPwV4VkLjWL9C7t1cL3yho6ksPRxv2RNiuFM9Mr8oH891TigNiBQF3KAmNbYYPN', $transaction->proofs[1]);
        $this->assertEquals('2ngdvNhkTMkNK3hvhUcU9gqcJB2sRbZD7av9GygzhCk1xQuJ38gPnpNPjksedUqsrprr5Lnrj1JxE5WcCNzrc4y4', $transaction->proofs[2]);

        $this->assertTrue($account0->verify($transaction->proofs[0], $transaction->toBinary()));
        $this->assertTrue($account1->verify($transaction->proofs[1], $transaction->toBinary()));
        $this->assertTrue($account2->verify($transaction->proofs[2], $transaction->toBinary()));
    }

    public function dataProvider()
    {
        $data = [
            'type' => 4,
            'version' => 2,
            'sender' => '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM',
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

        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(Transfer::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->sender);
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
        $this->expectExceptionMessage("Invalid data, missing keys: amount, recipient, attachment, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => 4]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 12;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 12, should be 4");

        Transfer::fromData($data);
    }

    public function testFromDataWithIncorrectVersion()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['version'] = 1;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid version 1, should be 2");

        Transfer::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new Transfer(120000000, '3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh');
        $transaction->id = $id;
        $transaction->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
        $transaction->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
        $transaction->fee = 100000000;
        $transaction->timestamp = 1609773456000;
        $transaction->amount = 120000000;
        $transaction->recipient = '3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh';
        $transaction->attachment = '9Ajdvzr';
        $transaction->proofs[] = '57Ysp2ugieiidpiEtutzyfJkEugxG43UXXaKEqzU3c2oLmN8qd3hzEFQoNL93R1SvyXemnnTBNtfhfCM2PenmQqa';
        $transaction->height = $height;

        $this->assertEquals($data, $transaction->jsonSerialize());
    }

    public function testBroadcast()
    {
        $transaction = new Transfer(10000, '3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1');
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

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
