<?php

declare(strict_types=1);

namespace LTO\Tests;

use LTO\Account;
use LTO\AccountFactory;
use LTO\Binary;
use LTO\Transaction;
use LTO\Transaction\Transfer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LTO\Transaction
 */
class TransactionTest extends TestCase
{
    protected const ACCOUNT_SEED = "df3dd6d884714288a39af0bd973a1771c9f00f168cf040d6abb6a50dd5e055d8";

    /** @var \LTO\Account */
    protected $account;

    public function setUp(): void
    {
        $this->account = (new AccountFactory('T'))->seed(self::ACCOUNT_SEED);
    }

    /**
     * Create a number of accounts.
     *
     * @param int $count  Number of accounts to create
     * @return Account[]
     */
    protected function getAccounts(int $count): array
    {
        $accountFactory = new AccountFactory('T');
        $accounts = [];

        for ($nonce = 0; $nonce < $count; $nonce++) {
            $accounts[] = $accountFactory->seed(self::ACCOUNT_SEED, $nonce);
        }

        return $accounts;
    }

    public function testSignSetTimestamp()
    {
        $transaction = (new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000))
            ->signWith($this->account);

        $this->assertIsInt($transaction->timestamp);
        $this->assertGreaterThan((time() - 5) * 1000, $transaction->timestamp);
    }

    public function testMultiSig()
    {
        [$account0, $account1, $account2] = $this->getAccounts(3);

        $transaction = (new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000))
            ->signWith($account0)
            ->signWith($account1)
            ->signWith($account2);

        $this->assertEquals($account0->getAddress(), $transaction->sender);
        $this->assertEquals($account0->getPublicSignKey(), $transaction->senderPublicKey);

        $this->assertCount(3, $transaction->proofs);
        $this->assertTrue($account0->verify($transaction->toBinary(), Binary::fromBase58($transaction->proofs[0])));
        $this->assertTrue($account1->verify($transaction->toBinary(), Binary::fromBase58($transaction->proofs[1])));
        $this->assertTrue($account2->verify($transaction->toBinary(), Binary::fromBase58($transaction->proofs[2])));
    }

    public function testSponsor()
    {
        [$sender, $sponsor] = $this->getAccounts(3);

        $transaction = (new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000))
            ->signWith($sender)
            ->sponsorWith($sponsor);

        $this->assertEquals($sender->getAddress(), $transaction->sender);
        $this->assertEquals($sender->getPublicSignKey(), $transaction->senderPublicKey);

        $this->assertEquals($sponsor->getAddress(), $transaction->sponsor);
        $this->assertEquals($sponsor->getPublicSignKey(), $transaction->sponsorPublicKey);

        $this->assertCount(2, $transaction->proofs);
        $this->assertTrue($sender->verify($transaction->toBinary(), Binary::fromBase58($transaction->proofs[0])));
        $this->assertTrue($sponsor->verify($transaction->toBinary(), Binary::fromBase58($transaction->proofs[1])));
    }

    public function testSponsorUnsignedTransaction()
    {
        $transaction = new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Transaction isn't signed");

        $transaction->sponsorWith($this->account);
    }

    public function testFromDataWithoutType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid data; type field is missing");

        Transaction::fromData(['id' => '7cCeL1qwd9i6u8NgMNsQjBPxVhrME2BbfZMT1DF9p4Yi']);
    }

    public function testFromDataWithUnsupportedType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported transaction type 99");

        Transaction::fromData(['id' => '7cCeL1qwd9i6u8NgMNsQjBPxVhrME2BbfZMT1DF9p4Yi', 'type' => 99]);
    }

    public function addressProvider()
    {
        return [
            'T' => ['3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', 'T'],
            'L' => ['3Jxv8EJ2XYN7BBaPyjqpcYNNcXwpTb8uiDD', 'L'],
        ];
    }

    /**
     * @dataProvider addressProvider
     */
    public function testGetNetwork(string $address, string $network)
    {
        $transaction = (new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000));
        $transaction->sender = $address;

        $this->assertEquals($network, $transaction->getNetwork());
    }

    public function testGetNetworkWithoutSender()
    {
        $transaction = (new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Sender not set");

        $transaction->getNetwork();
    }
}
