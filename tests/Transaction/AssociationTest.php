<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\Account;
use LTO\AccountFactory;
use LTO\Binary;
use LTO\PublicNode;
use LTO\Tests\CustomAsserts;
use LTO\Transaction;
use LTO\Transaction\Association;
use LTO\UnsupportedFeatureException;
use PHPUnit\Framework\TestCase;
use function LTO\decode;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\AbstractAssociation
 * @covers \LTO\Transaction\Association
 * @covers \LTO\Transaction\Pack\AssociationV1
 * @covers \LTO\Transaction\Pack\AssociationV3
 */
class AssociationTest extends TestCase
{
    use CustomAsserts;

    protected Account $account;
    protected Binary $hash;
    protected string $recipient = "3NACnKFVN2DeFYjspHKfa2kvDqnPkhjGCD2";
    protected int $associationType = 10;


    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed('test');
        $this->hash = Binary::hash('sha256', ''); // e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
    }

    public function testConstruct()
    {
        $transaction = new Association($this->recipient, $this->associationType);

        $this->assertEquals(3, $transaction->version);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals($this->recipient, $transaction->recipient);
        $this->assertEquals($this->associationType, $transaction->associationType);
    }

    public function invalidPartyProvider()
    {
        return [
            'test' => ['test'],
            'hello' => ['hello'],
            'raw' => [decode('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 'base58')],
        ];
    }

    /**
     * @dataProvider invalidPartyProvider
     */
    public function testConstructInvalidrecipient($recipient)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid recipient address; is it base58 encoded?");

        new Association($recipient, 42);
    }

    public function binaryProvider()
    {
        return [
            'v1 no hash' =>    [1, null, '4Q51irdRQbjQiipcsMeZDtuXXVHLhuNveAYajPDc2mBmqVoGrUXkAK4v1H8R6Z8A8Diiqqn1XowxrWbhKMpCg13BSzPG62S7mXCAqTmYwc4gm3fm'],
            'v1 empty hash' => [1, Binary::fromRaw(''), '29CDanR1A162rCCurXboGC1Nn6suPnYeYZNPdzQw2cujqnkiYfox5mSN1QmCMwYUEH2JyKfvYFDYbPkEv5uFBA33UQopajqmVBkgXXC9m7Cxeyxw8Zd'],
            'v1 with hash' =>  [1, Binary::hash('sha256', ''), 'LfVAjhtHfNcJXUepVzXgegX6NehpnYva5gtA7RZypw36qSAj2Aqz4ZgUWZLeqsDYZSfJ8LsFJx7uQKvGvcr2q7KZNqdFqdvpHGs7dCigiu7ncYiGHUuD6TDarhLw5bCHAx4gTPSFWftBKB5yVRhwL6Lryj6xYP'],
            'v3 no hash' =>    [3, null, 'qtju6ycVnChTwuYQnDo24E7gM5zytcKYcZVFybs8Tq2oZ4QQrUcDMM3G32PZDJkQuorqLmx8XF4pk6njcpVSxkAum3z1Y4cpEj144tYG9bACRdTspxtPxsi5jzYV5'],
            'v3 empty hash' => [3, Binary::fromRaw(''), 'qtju6ycVnChTwuYQnDo24E7gM5zytcKYcZVFybs8Tq2oZ4QQrUcDMM3G32PZDJkQuorqLmx8XF4pk6njcpVSxkAum3z1Y4cpEj144tYG9bACRdTspxtPxsi5jzYV5'],
            'v3 with hash' =>  [3, Binary::hash('sha256', ''), 'FXPf4kBRFURpiKRzWut1C8JhPsBDdQpoaUd43DvjaWfs5GFaovqSYGm1bNhdFKJXh1Rto6ZUojYvyKiekG5kQe3QbS1HXF9GXS7voTuzf3xt5K49tUf33gWD1TZDFAg4MBNEJpbWsqSrxQkHPaKF5x25KY6R26aqMmAdwQTXi'],
        ];
    }

    /**
     * @dataProvider binaryProvider
     */
    public function testToBinary(int $version, ?Binary $hash, string $binary)
    {
        $transaction = new Association($this->recipient, $this->associationType, $hash);
        $transaction->sender = $this->account->getAddress();
        $transaction->senderPublicKey = $this->account->getPublicSignKey();
        $transaction->version = $version;
        $transaction->timestamp = strtotime('2018-03-01T00:00:00+00:00') * 1000;

        $this->assertEqualsBase58($binary, $transaction->toBinary());
    }

    public function versionProvider()
    {
        return [
            'v1' => [1],
            'v3' => [3],
        ];
    }

    /**
     * @dataProvider versionProvider
     */
    public function testToBinaryNoSender(int $version)
    {
        $transaction = new Association($this->recipient, $this->associationType);
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
        $transaction = new Association($this->recipient, $this->associationType);
        $transaction->version = $version;
        $transaction->sender = $this->account->getAddress();
        $transaction->senderPublicKey = $this->account->getPublicSignKey();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new Association($this->recipient, $this->associationType);
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported association tx version 99");

        $transaction->toBinary();
    }


    public function signProvider()
    {
        return [
            'v1 with hash' => [1, Binary::hash('sha256', '')],
            'v1 no hash' => [1, null],
            'v3 with hash' => [3, Binary::hash('sha256', '')],
            'v3 no hash' => [3, null],
        ];
    }

    /**
     * @dataProvider signProvider
     */
    public function testSign(int $version, ?Binary $hash)
    {
        $transaction = new Association($this->recipient, $this->associationType, $hash);
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

    public function expiresProvider()
    {
        return [
            'miliseconds' => [strtotime('2021-09-01 12:00:00') * 1000],
            'DateTime' => [new \DateTime('2021-09-01 12:00:00')],
        ];
    }

    /**
     * @dataProvider expiresProvider
     */
    public function testExpires($expires)
    {
        $transaction = new Association($this->recipient, $this->associationType);
        $transaction->version = 3;
        $transaction->sender = $this->account->getAddress();
        $transaction->senderPublicKey = $this->account->getPublicSignKey();
        $transaction->timestamp = strtotime('2018-03-01T00:00:00+00:00') * 1000;

        $ret = $transaction->expires($expires);
        $this->assertSame($transaction, $ret);

        $this->assertEquals(strtotime('2021-09-01 12:00:00') * 1000, $transaction->expire);

        $this->assertEqualsBase58(
            'qtju6ycVnChTwuYQnDo24E7gM5zytcKYcZVFybs8Tq2oZ4QQrUcDMM3G32PZDJkQuorqLmx8XF4pk6njcpVSxkAum3z1Y4cpEj144tYG9bACRdTspxteMH7eGBD6F',
            $transaction->toBinary()
        );
    }

    public function testExpiresInvalidArgument()
    {
        $transaction = new Association($this->recipient, $this->associationType);
        $transaction->version = 3;

        $this->expectException(\InvalidArgumentException::class);

        $transaction->expires("2021-09-01 12:00:00");
    }

    public function testExpiresUnsupportedVersion()
    {
        $transaction = new Association($this->recipient, $this->associationType);
        $transaction->version = 1;

        $this->expectException(UnsupportedFeatureException::class);

        $transaction->expires(new \DateTime("2021-09-01 12:00:00"));
    }

    public function dataProvider()
    {
        $data = [
            "type" => 16,
            "version" => 1,
            "recipient" => "3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh",
            "associationType" => 42,
            "hash" => "3yMApqCuCjXDWPrbjfR5mjCPTHqFG8Pux1TxQrEM35jj",
            "sender" => "3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM",
            'senderKeyType' => 'ed25519',
            "senderPublicKey" => "7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7",
            "timestamp" => 1610154732000,
            "fee" => 100000000,
            "proofs" => [
                "4NrsjbkkWyH4K57jf9MQ5Ya9ccvXtCg2BQV2LsHMMacZZojbcRgesB1MruVQtCaZdvFSswwju5zCxisG3ZaQ2LKF"
            ],
        ];

        return [
            'new' => [$data, null, null],
            'unconfirmed' => [$data, 'UMkS6oU6GfhhZngST6opVQYvCbLMnWVL4q6SC46F7ch', null],
            'confirmed' => [$data, 'UMkS6oU6GfhhZngST6opVQYvCbLMnWVL4q6SC46F7ch', 1221474],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFromData(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        /** @var Association $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(Association::class, $transaction);

        $this->assertEquals($id, $transaction->id);
        $this->assertEquals('3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $transaction->sender);
        $this->assertEquals('7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7', $transaction->senderPublicKey);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals(1610154732000, $transaction->timestamp);
        $this->assertEquals('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', $transaction->recipient);
        $this->assertEquals(
            ['4NrsjbkkWyH4K57jf9MQ5Ya9ccvXtCg2BQV2LsHMMacZZojbcRgesB1MruVQtCaZdvFSswwju5zCxisG3ZaQ2LKF'],
            $transaction->proofs
        );
        $this->assertEquals($height, $transaction->height);
    }

    public function testFromDataWithMissingKeys()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data, missing keys: recipient, associationType, version, sender, senderPublicKey, timestamp, fee, proofs");

        Transaction::fromData(['type' => Association::TYPE]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 16");

        Association::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new Association('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', 42, Binary::hash('sha256', 'foo'));
        $transaction->id = $id;
        $transaction->version = 1;
        $transaction->sender = '3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM';
        $transaction->senderPublicKey = '7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7';
        $transaction->fee = 100000000;
        $transaction->timestamp = 1610154732000;
        $transaction->proofs[] = '4NrsjbkkWyH4K57jf9MQ5Ya9ccvXtCg2BQV2LsHMMacZZojbcRgesB1MruVQtCaZdvFSswwju5zCxisG3ZaQ2LKF';
        $transaction->height = $height;

        $this->assertEqualsAsJson($data, $transaction);
    }

    public function testBroadcast()
    {
        $transaction = new Association($this->recipient, $this->associationType);

        $broadcastedTransaction = clone $transaction;
        $broadcastedTransaction->id = 'UMkS6oU6GfhhZngST6opVQYvCbLMnWVL4q6SC46F7ch';

        $node = $this->createMock(PublicNode::class);
        $node->expects($this->once())->method('broadcast')
            ->with($this->identicalTo($transaction))
            ->willReturn($broadcastedTransaction);

        $ret = $transaction->broadcastTo($node);

        $this->assertSame($broadcastedTransaction, $ret);
    }
}
