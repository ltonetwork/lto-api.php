<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\Account;
use LTO\AccountFactory;
use LTO\Binary;
use LTO\PublicNode;
use LTO\Tests\CustomAsserts;
use LTO\Transaction;
use LTO\Transaction\CancelLease;
use LTO\Transaction\Lease;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\CancelLease
 * @covers \LTO\Transaction\Pack\CancelLeaseV2
 * @covers \LTO\Transaction\Pack\CancelLeaseV3
 */
class CancelLeaseTest extends TestCase
{
    use CustomAsserts;

    protected Account $account;
    protected string $leaseId = "ELtXhrFTCRJSEweYAAaVTuv9wGjNzwHYUDnH6UT1JxmB";

    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed('test');
    }

    public function testConstruct()
    {
        $transaction = new CancelLease($this->leaseId);

        $this->assertEquals(3, $transaction->version);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals($this->leaseId, $transaction->leaseId);
    }

    public function versionProvider()
    {
        return [
            'v2' => [2, '9ScRzCmHigdLyhgUpUYu9W5ug9QEqKuE676MP7q1Lfdb3AS4GzcwLN24u6KBiaEGQdJTQSW6Vg83jQCLZKX8pKDZR2fTuJP6H4L3nawtjsykEd59w'],
            'v3' => [3, 'eGyiRd1Vx21nYCWTjHichz6KVfhs2LBMRza2FrwmSxnE8GQdLNcuPfK3RpEuUtc1QJnw9y7fCsLt3xQWKVdMXm7iBhVK8mtTzLPCfGenFWYxjVPsUH'],
        ];
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinary(int $version, string $binary)
    {
        $transaction = new CancelLease($this->leaseId);
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
        $transaction = new CancelLease($this->leaseId);
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
        $transaction = new CancelLease($this->leaseId);
        $transaction->version = $version;
        $transaction->sender = $this->account->getAddress();
        $transaction->senderPublicKey = $this->account->getPublicSignKey();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new CancelLease($this->leaseId);
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported cancel lease tx version 99");

        $transaction->toBinary();
    }

    /**
     * @dataProvider versionProvider
     */
    public function testSign(int $version)
    {
        $transaction = new CancelLease($this->leaseId);
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
            "type" => 9,
            "sender" => "3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM",
            'senderKeyType' => 'ed25519',
            "senderPublicKey" => "7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7",
            "fee" => 100000000,
            "timestamp" => 1610149943000,
            "proofs" => [
                "4WK1dhRbLCgFCRDYYTLqRiUxLPATG8Bb9vgQoUN9oorTK6ez22jbGnVo4eUqv9YTEvmtbyKwsrBKo2uSZPDWaWgY"
            ],
            "chainId" => 84,
            "version" => 2,
            "leaseId" => "B22YzYdNv7DCqMqdK2ckpt53gQuYq2v997N7g8agZoHo",
        ];

        $lease = [
            "type" => 8,
            "id" => "AfanxjNfgtdmaJ4bz4dDg5e5ELUvXtRnuWe6Q49K6u3v",
            "sender" => "3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM",
            'senderKeyType' => 'ed25519',
            "senderPublicKey" => "7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7",
            "fee" => 100000000,
            "timestamp" => 1610148915000,
            "proofs" => [
                "5MXTj9WfF3nWeJe5VCqRamXexhRR3sJxbSDbvtSzaFaPSdD6RYpDU4BfDEYaSzwfsTgp4iUfLhevpVxZr6yTdUYs"
            ],
            "version" => 2,
            "amount" => 120000000,
            "recipient" => "3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh"
        ];

        return [
            'new' => [$data, null, null, null],
            'unconfirmed' => [$data, null, 'B22YzYdNv7DCqMqdK2ckpt53gQuYq2v997N7g8agZoHo', null],
            'confirmed' => [$data, null, 'B22YzYdNv7DCqMqdK2ckpt53gQuYq2v997N7g8agZoHo', 1221386],
            'full' => [$data, $lease, 'B22YzYdNv7DCqMqdK2ckpt53gQuYq2v997N7g8agZoHo', 1221386]
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFromData(array $data, ?array $lease, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];
        if ($lease !== null) $data += ['lease' => $lease];

        /** @var CancelLease $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(CancelLease::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->sender);
        $this->assertEquals('7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7', $transaction->senderPublicKey);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals(1610149943000, $transaction->timestamp);
        $this->assertEquals(
            ['4WK1dhRbLCgFCRDYYTLqRiUxLPATG8Bb9vgQoUN9oorTK6ez22jbGnVo4eUqv9YTEvmtbyKwsrBKo2uSZPDWaWgY'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);

        if ($lease !== null) {
            $this->assertInstanceOf(Lease::class, $transaction->lease);
            $this->assertEquals("AfanxjNfgtdmaJ4bz4dDg5e5ELUvXtRnuWe6Q49K6u3v", $transaction->lease->id);
            $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->lease->sender);
            $this->assertEquals('7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7', $transaction->lease->senderPublicKey);
            $this->assertEquals(100000000, $transaction->lease->fee);
            $this->assertEquals(1610148915000, $transaction->lease->timestamp);
            $this->assertEquals(120000000, $transaction->lease->amount);
            $this->assertEquals('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', $transaction->lease->recipient);
            $this->assertEquals(
                ['5MXTj9WfF3nWeJe5VCqRamXexhRR3sJxbSDbvtSzaFaPSdD6RYpDU4BfDEYaSzwfsTgp4iUfLhevpVxZr6yTdUYs'],
                $transaction->lease->proofs
            );
        }
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: leaseId, version, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => CancelLease::TYPE]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 9");

        CancelLease::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?array $lease, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];
        if ($lease !== null) $data += ['lease' => $lease];

        $transaction = new CancelLease('AfanxjNfgtdmaJ4bz4dDg5e5ELUvXtRnuWe6Q49K6u3v');
        $transaction->id = $id;
        $transaction->version = 2;
        $transaction->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
        $transaction->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
        $transaction->fee = 100000000;
        $transaction->timestamp = 1610149943000;
        $transaction->leaseId = 'B22YzYdNv7DCqMqdK2ckpt53gQuYq2v997N7g8agZoHo';
        $transaction->proofs[] = '4WK1dhRbLCgFCRDYYTLqRiUxLPATG8Bb9vgQoUN9oorTK6ez22jbGnVo4eUqv9YTEvmtbyKwsrBKo2uSZPDWaWgY';
        $transaction->height = $height;

        if ($lease !== null) {
            $transaction->lease = new Lease('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', 120000000);
            $transaction->lease->version = 2;
            $transaction->lease->id = 'AfanxjNfgtdmaJ4bz4dDg5e5ELUvXtRnuWe6Q49K6u3v';
            $transaction->lease->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
            $transaction->lease->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
            $transaction->lease->fee = 100000000;
            $transaction->lease->timestamp = 1610148915000;
            $transaction->lease->proofs[] = '5MXTj9WfF3nWeJe5VCqRamXexhRR3sJxbSDbvtSzaFaPSdD6RYpDU4BfDEYaSzwfsTgp4iUfLhevpVxZr6yTdUYs';
        }

        $this->assertEqualsAsJson($data, $transaction);
    }

    public function testBroadcast()
    {
        $transaction = new CancelLease('B22YzYdNv7DCqMqdK2ckpt53gQuYq2v997N7g8agZoHo');

        $broadcastedTransaction = clone $transaction;
        $broadcastedTransaction->id = 'B22YzYdNv7DCqMqdK2ckpt53gQuYq2v997N7g8agZoHo';

        $node = $this->createMock(PublicNode::class);
        $node->expects($this->once())->method('broadcast')
            ->with($this->identicalTo($transaction))
            ->willReturn($broadcastedTransaction);

        $ret = $transaction->broadcastTo($node);

        $this->assertSame($broadcastedTransaction, $ret);
    }
}
