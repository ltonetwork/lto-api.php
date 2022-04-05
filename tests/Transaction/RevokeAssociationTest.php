<?php

declare(strict_types=1);

namespace LTO\Tests\Transaction;

use LTO\Account;
use LTO\AccountFactory;
use LTO\Binary;
use LTO\PublicNode;
use LTO\Tests\CustomAsserts;
use LTO\Transaction;
use LTO\Transaction\RevokeAssociation;
use PHPUnit\Framework\TestCase;
use function LTO\decode;
use function LTO\encode;

/**
 * @covers \LTO\Transaction
 * @covers \LTO\Transaction\AbstractAssociation
 * @covers \LTO\Transaction\RevokeAssociation
 * @covers \LTO\Transaction\Pack\AssociationV1
 * @covers \LTO\Transaction\Pack\RevokeAssociationV3
 */
class RevokeAssociationTest extends TestCase
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

    public function hashProvider()
    {
        return [
            'no hash' => [null],
            'with hash' => [Binary::hash('sha256', '')],
        ];
    }

    /**
     * @dataProvider hashProvider
     */
    public function testConstruct(?Binary $hash)
    {
        $transaction = new RevokeAssociation($this->recipient, $this->associationType, $hash);

        $this->assertEquals(3, $transaction->version);
        $this->assertEquals(100000000, $transaction->fee);
        $this->assertEquals($this->recipient, $transaction->recipient);
        $this->assertEquals($this->associationType, $transaction->associationType);
        $this->assertEquals($hash, $transaction->hash);
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
    public function testConstructInvalidRecipient($recipient)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid recipient address; is it base58 encoded?");

        new RevokeAssociation($recipient, 42);
    }

    public function binaryProvider()
    {
        return [
            'v1 no hash' =>    [1, null, '4cPA5ptXZVH1cyKv9QsbuwEnyYDC4fkhsWGXBiAqg3J2GwvSJGfWbFJZyrQFv4YFiLmRk1bujJ5SRnxPvmpL7ZFp2YBwcWsXGZ131keSm9FGjar3'],
            'v1 empty hash' => [1, Binary::fromRaw(''), '2DL6ESPfaqqeyymuf7DXDhv3ymMAMhhmpG9jN1aMrcEzUfbvJBizCrBJJLMYhykHfVRFByFm5uNnJ6Gd5s4S27QyLBu9sb9iVHjuTtEG9BoCkB8TBaX'],
            'v1 with hash' =>  [1, Binary::hash('sha256', ''), 'Mtk66u6Lgfp2fXdDBc6iJdy3kkqXGd2nKwJ9za7Bn89L35oac1cYDo5qEBKEYtppLX7Ejz7wvSCVwGgPZ4pf5dyfwCuURnQPDXseyMeifnbx43WZKqGK79LXrM4yRhWVtuBDCwDS2rTuxKhCTEfspkdDZMyKLj'],
            'v3 no hash' =>    [3, null, '2DMxufSJvg2zrpkUtNDWGqFVvoRmaMbBX4FHpRANPMcoK942dTgut3aoNpZ3fnX9V7UGgLdamn5b6qWC7C3gpVccXQwij7952sdSs5GgAK2oybEFJZV'],
            'v3 empty hash' => [3, Binary::fromRaw(''), '2DMxufSJvg2zrpkUtNDWGqFVvoRmaMbBX4FHpRANPMcoK942dTgut3aoNpZ3fnX9V7UGgLdamn5b6qWC7C3gpVccXQwij7952sdSs5GgAK2oybEFJZV'],
            'v3 with hash' =>  [3, Binary::hash('sha256', ''), 'MuJNYwF55DHZPH5tYRqWDwmUN4jJf7NPAvZv1Usrg1QG2LeiUZPeJgzvAUbfv8eD9hsXPK7RZ8VBSR75yZkAPuN34jCTRo76gZzFNWWD75q1sePthZSGdwFFoyixZjEsHzqbPnX7p9juESrhDi8bYzJvuWJSYL'],
        ];
    }

    /**
     * @dataProvider binaryProvider
     */
    public function testToBinary(int $version, ?Binary $hash, string $binary)
    {
        $transaction = new RevokeAssociation($this->recipient, $this->associationType, $hash);
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
        $transaction = new RevokeAssociation('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 42);
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
        $transaction = new RevokeAssociation('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 42);
        $transaction->version = $version;
        $transaction->senderPublicKey = '4EcSxUkMxqxBEBUBL2oKz3ARVsbyRJTivWpNrYQGdguz';

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Timestamp not set");

        $transaction->toBinary();
    }

    public function testToBinaryWithUnsupportedVersion()
    {
        $transaction = new RevokeAssociation('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 42);
        $transaction->version = 99;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Unsupported revoke association tx version 99");

        $transaction->toBinary();
    }


    public function signProvider()
    {
        return [
            'v1 with hash' => [1, Binary::hash('sha256', 'foo')],
            'v1 no hash' => [1, null],
            'v3 with hash' => [3, Binary::hash('sha256', 'foo')],
            'v3 no hash' => [3, null],
        ];
    }

    /**
     * @dataProvider signProvider
     */
    public function testSign(int $version, ?Binary $hash)
    {
        $transaction = new RevokeAssociation($this->recipient, $this->associationType, $hash);
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
            "type" => 17,
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

        /** @var RevokeAssociation $transaction */
        $transaction = Transaction::fromData($data);

        $this->assertInstanceOf(RevokeAssociation::class, $transaction);

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

        Transaction::fromData(['type' => RevokeAssociation::TYPE]);
    }

    public function testFromDataWithIncorrectType()
    {
        $data = $this->dataProvider()['confirmed'][0];
        $data['type'] = 99;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 99, should be 17");

        RevokeAssociation::fromData($data);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testJsonSerialize(array $data, ?string $id, ?int $height)
    {
        if ($id !== null) $data += ['id' => $id];
        if ($height !== null) $data += ['height' => $height];

        $transaction = new RevokeAssociation('3N9ChkxWXqgdWLLErWFrSwjqARB6NtYsvZh', 42, Binary::hash('sha256', 'foo'));
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
        $transaction = new RevokeAssociation('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 42);

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
