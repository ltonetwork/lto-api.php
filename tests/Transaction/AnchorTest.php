<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\Account;
use LTO\AccountFactory;
use LTO\Binary;
use LTO\PublicNode;
use LTO\Tests\CustomAsserts;
use LTO\Transaction;
use LTO\Transaction\Anchor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\Anchor
 * @covers \LTO\Transaction\Pack\AnchorV1
 * @covers \LTO\Transaction\Pack\AnchorV3
 */
class AnchorTest extends TestCase
{
    use CustomAsserts;

    protected Account $account;
    protected Binary $hash;

    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed("test");
        $this->hash = Binary::hash('sha256', ''); // e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
    }

    public function encodedHashProvider()
    {
        return [
            'none' => [],
            'one' => [Binary::hash('sha256', '')],
            'multiple' => [
                Binary::hash('sha256', 'one'),
                Binary::hash('sha256', 'two'),
                Binary::hash('sha256', 'three'),
            ],
        ];
    }

    /**
     * @dataProvider encodedHashProvider
     */
    public function testConstruct(...$anchors)
    {
        $transaction = new Anchor(...$anchors);

        $this->assertEquals(3, $transaction->version);
        $this->assertEquals(25000000 + count($anchors) * 10000000, $transaction->fee);
        $this->assertSame($anchors, $transaction->anchors);
    }

    public function versionProvider()
    {
        return [
            'v1' => [1, "MquGbi8ADEhTeqTgfXUdud2D1oKPTYrGRXaJ4BcmirU3V3LEQPfzckNyjHaHiNKyDyVhUZQ1LnnkbLgpdQZhkpyHGApnfD92bh9bXrSQdFXTuKBvPpGZD"],
            'v3' => [3, "81J6diQthLjberPHzN29R18kwViAdLqdDom3Vaso9MhEAt6uH3CM9sz9NMYjR21PFCJEbojd4jhG1izqz1QM5C1ea9ZpqN5RrY3hrrvVkRBeGDtxyEJEcKMW"],
        ];
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinary(int $version, string $binary)
    {
        $transaction = new Anchor($this->hash);
        $transaction->version = $version;
        $transaction->timestamp = strtotime('2018-03-01T00:00:00+00:00') * 1000;
        $transaction->sender = $this->account->getAddress();
        $transaction->senderPublicKey = $this->account->getPublicSignKey();

        $this->assertEqualsBase58($binary, $transaction->toBinary());
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinaryNoSender(int $version)
    {
        $transaction = new Anchor($this->hash);
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
        $transaction = new Anchor($this->hash);
        $transaction->version = $version;
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new Anchor($this->hash);
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported anchor tx version 99");

        $transaction->toBinary();
    }

    /**
     * @dataProvider versionProvider
     */
    public function testSign(int $version)
    {
        $transaction = new Anchor($this->hash);
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

    public function testSignWithMultiAnchorTx()
    {
        $transaction = new Anchor(
            Binary::hash('sha256', 'one'),
            Binary::hash('sha256', 'two'),
        );
        $transaction->timestamp = strtotime('2018-03-01T00:00:00+00:00') * 1000;

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals($this->account->getAddress(), $transaction->sender);
        $this->assertEquals($this->account->getPublicSignKey(), $transaction->senderPublicKey);

        // Unchanged
        $this->assertEquals(strtotime('2018-03-01T00:00:00+00:00') * 1000, $transaction->timestamp);

        $this->assertTrue(
            $this->account->verify($transaction->toBinary(), Binary::fromBase58($transaction->proofs[0]))
        );
    }

    public function dataProvider()
    {
        $data = [
            'type' => 15,
            'version' => 1,
            'sender' => '3Jq8mnhRquuXCiFUwTLZFVSzmQt3Fu6F7HQ',
            'senderKeyType' => 'ed25519',
            'senderPublicKey' => 'AJVNfYjTvDD2GWKPejHbKPLxdvwXjAnhJzo6KCv17nne',
            'fee' => 35000000,
            'timestamp' => 1610142631066,
            'anchors' => [
                "3mM7VirFP1LfJ5kGeWs9uTnNrM2APMeCcmezBEy8o8wk",
            ],
            'proofs' => [
                '24MvxB1i8nCm96Yhqf26pRFVrJwstrHWHbSGCXWPM2VTu3WfZa9TCX7r3KjwjeHAX711cYWt9owjd2mAbk1KtQm3',
            ],
        ];

        return [
            'new' => [$data, null, null],
            'unconfirmed' => [$data, 'ADyNA3AuVDDhKyu4ZWPp7j7fcb1JYdjP8wUdpoRUzGnU', null],
            'confirmed' => [$data, 'ADyNA3AuVDDhKyu4ZWPp7j7fcb1JYdjP8wUdpoRUzGnU', 1215007],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFromData(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        /** @var Anchor $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(Anchor::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3Jq8mnhRquuXCiFUwTLZFVSzmQt3Fu6F7HQ', $transaction->sender);
        $this->assertEquals('AJVNfYjTvDD2GWKPejHbKPLxdvwXjAnhJzo6KCv17nne', $transaction->senderPublicKey);
        $this->assertEquals(35000000, $transaction->fee);
        $this->assertEquals(1610142631066, $transaction->timestamp);
        $this->assertContainsOnlyInstancesOf(Binary::class, $transaction->anchors);
        $this->assertCount(1, $transaction->anchors);
        $this->assertEquals('3mM7VirFP1LfJ5kGeWs9uTnNrM2APMeCcmezBEy8o8wk', $transaction->anchors[0]->base58());
        $this->assertEquals(
            ['24MvxB1i8nCm96Yhqf26pRFVrJwstrHWHbSGCXWPM2VTu3WfZa9TCX7r3KjwjeHAX711cYWt9owjd2mAbk1KtQm3'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: anchors, version, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => Anchor::TYPE]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 15");

        Anchor::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new Anchor(Binary::fromBase58("3mM7VirFP1LfJ5kGeWs9uTnNrM2APMeCcmezBEy8o8wk"));
        $transaction->id = $id;
        $transaction->version = 1;
        $transaction->sender = '3Jq8mnhRquuXCiFUwTLZFVSzmQt3Fu6F7HQ';
        $transaction->senderPublicKey = 'AJVNfYjTvDD2GWKPejHbKPLxdvwXjAnhJzo6KCv17nne';
        $transaction->fee = 35000000;
        $transaction->timestamp = 1610142631066;
        $transaction->proofs[] = '24MvxB1i8nCm96Yhqf26pRFVrJwstrHWHbSGCXWPM2VTu3WfZa9TCX7r3KjwjeHAX711cYWt9owjd2mAbk1KtQm3';
        $transaction->height = $height;

        $this->assertEqualsAsJson($data, $transaction);
    }

    public function testBroadcast()
    {
        $transaction = new Anchor($this->hash);

        $broadcastedTransaction = clone $transaction;
        $broadcastedTransaction->id = 'ADyNA3AuVDDhKyu4ZWPp7j7fcb1JYdjP8wUdpoRUzGnU';

        $node = $this->createMock(PublicNode::class);
        $node->expects($this->once())->method('broadcast')
            ->with($this->identicalTo($transaction))
            ->willReturn($broadcastedTransaction);

        $ret = $transaction->broadcastTo($node);

        $this->assertSame($broadcastedTransaction, $ret);
    }
}
