<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\AccountFactory;
use LTO\PublicNode;
use LTO\Transaction;
use LTO\Transaction\Anchor;
use PHPUnit\Framework\TestCase;
use function LTO\decode;
use function LTO\encode;
use function LTO\sha256;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\Anchor
 */
class AnchorTest extends TestCase
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

    public function encodedHashProvider()
    {
        return [
            'raw' => [hash('sha256', 'foo', true), 'raw'],
            'hex' => [hash('sha256', 'foo'), 'hex'],
            'base58' => [encode(hash('sha256', 'foo', true), 'base58'), 'base58'],
            'base64' => [encode(hash('sha256', 'foo', true), 'base64'), 'base64'],
        ];
    }

    /**
     * @dataProvider encodedHashProvider
     */
    public function testConstruct(string $hash, string $encoding)
    {
        $transaction = new Anchor($hash, $encoding);

        $this->assertCount(1, $transaction->anchors);
        $this->assertEquals(
            base58_encode(hash('sha256', 'foo', true)),
            $transaction->anchors[0]
        );
        $this->assertEquals(35000000, $transaction->fee);
    }

    public function testToBinaryNoSender()
    {
        $transaction = new Anchor(hash('sha256', 'foo'));
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Sender public key not set");

        $transaction->toBinary();
    }

    public function testToBinaryNoTimestamp()
    {
        $transaction = new Anchor(hash('sha256', 'foo'));
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }


    public function testSign()
    {
        $transaction = new Anchor(hash('sha256', 'foo'));
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);
        $this->assertEquals(
            '3HxFrkj3EdJFTjq2RRYFbKMhok5NhUzEiAEhvXprgJnFMdstGHzgqyHXLyChfhNU14zozbU3Mw4fQc5dBKTCeDPe',
            $transaction->proofs[0]
        );

        // Unchanged
        $this->assertEquals((new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(), $transaction->timestamp);

        $this->assertTrue($this->account->verify($transaction->proofs[0], $transaction->toBinary()));
    }

    public function testSignWithMultiAnchorTx()
    {
        $transaction = (new Anchor())
            ->addHash(hash('sha256', 'one'))
            ->addHash(hash('sha256', 'two'));
        $transaction->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $this->assertFalse($transaction->isSigned());

        $ret = $transaction->signWith($this->account);
        $this->assertSame($transaction, $ret);

        $this->assertTrue($transaction->isSigned());

        $this->assertEquals('3MtHYnCkd3oFZr21yb2vEdngcSGXvuNNCq2', $transaction->sender);
        $this->assertEquals('4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz', $transaction->senderPublicKey);
        $this->assertEquals(
            '5kuUZbzvUUqtB1zrHfH3RZpAd31EV2mgwcB6z8jk7XwAQJ3JRw47tc8NbRhP28tQ491ycKaQRSTFuRdLkz8CiGb2',
            $transaction->proofs[0]
        );

        // Unchanged
        $this->assertEquals((new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(), $transaction->timestamp);

        $this->assertTrue($this->account->verify($transaction->proofs[0], $transaction->toBinary()));
    }

    public function dataProvider()
    {
        $data = [
            'type' => 15,
            'version' => 1,
            'sender' => '3Jq8mnhRquuXCiFUwTLZFVSzmQt3Fu6F7HQ',
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
        $this->assertCount(1, $transaction->anchors);
        $this->assertEquals('3mM7VirFP1LfJ5kGeWs9uTnNrM2APMeCcmezBEy8o8wk', $transaction->anchors[0]);
        $this->assertEquals(
            ['24MvxB1i8nCm96Yhqf26pRFVrJwstrHWHbSGCXWPM2VTu3WfZa9TCX7r3KjwjeHAX711cYWt9owjd2mAbk1KtQm3'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: anchors, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => 15]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 15");

        Anchor::fromData($data);
    }

    public function testFromDataWithIncorrectVersion()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['version'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid version 99, should be 1");

        Anchor::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new Anchor("3mM7VirFP1LfJ5kGeWs9uTnNrM2APMeCcmezBEy8o8wk", 'base58');
        $transaction->id = $id;
        $transaction->sender = '3Jq8mnhRquuXCiFUwTLZFVSzmQt3Fu6F7HQ';
        $transaction->senderPublicKey = 'AJVNfYjTvDD2GWKPejHbKPLxdvwXjAnhJzo6KCv17nne';
        $transaction->fee = 35000000;
        $transaction->timestamp = 1610142631066;
        $transaction->proofs[] = '24MvxB1i8nCm96Yhqf26pRFVrJwstrHWHbSGCXWPM2VTu3WfZa9TCX7r3KjwjeHAX711cYWt9owjd2mAbk1KtQm3';
        $transaction->height = $height;

        $this->assertEquals($data, $transaction->jsonSerialize());
    }

    public function testBroadcast()
    {
        $transaction = new Anchor(hash('sha256', 'foo'));

        $broadcastedTransaction = clone $transaction;
        $broadcastedTransaction->id = 'ADyNA3AuVDDhKyu4ZWPp7j7fcb1JYdjP8wUdpoRUzGnU';

        $node = $this->createMock(PublicNode::class);
        $node->expects($this->once())->method('broadcast')
            ->with($this->identicalTo($transaction))
            ->willReturn($broadcastedTransaction);

        $ret = $transaction->broadcastTo($node);

        $this->assertSame($broadcastedTransaction, $ret);
    }

    /**
     * @dataProvider encodedHashProvider
     */
    public function testGetHash(string $hash, string $encoding)
    {
        $transaction = new Anchor(hash('sha256', 'foo'));

        $this->assertEquals($hash, $transaction->getHash($encoding));
    }

    public function testGetHashWithMultiAnchorTx()
    {
        $transaction = (new Anchor())
            ->addHash(hash('sha256', 'one'))
            ->addHash(hash('sha256', 'two'));

        $this->expectException(\BadMethodCallException::class);
        $transaction->getHash();
    }

    public function testGetHashWithEmptyMultiAnchorTx()
    {
        $transaction = new Anchor();

        $this->expectException(\BadMethodCallException::class);
        $transaction->getHash();
    }


    public function encodedMultiHashProvider()
    {
        return [
            'raw' => [
                [hash('sha256', 'one', true), hash('sha256', 'two', true)],
                'raw'
            ],
            'hex' => [
                [hash('sha256', 'one'), hash('sha256', 'two')],
                'hex'
            ],
            'base58' => [
                [
                    encode(hash('sha256', 'one', true), 'base58'),
                    encode(hash('sha256', 'two', true), 'base58')
                ],
                'base58'
            ],
            'base64' => [
                [
                    encode(hash('sha256', 'one', true), 'base64'),
                    encode(hash('sha256', 'two', true), 'base64'),
                ],
                'base64'
            ],
        ];
    }

    /**
     * @dataProvider encodedMultiHashProvider
     */
    public function testGetHashes(array $hashes, string $encoding)
    {
        $transaction = (new Anchor())
            ->addHash(hash('sha256', 'one'))
            ->addHash(hash('sha256', 'two'));

        $this->assertEquals($hashes, $transaction->getHashes($encoding));
    }
}
