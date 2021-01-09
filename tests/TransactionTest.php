<?php

declare(strict_types=1);

namespace LTO\Tests;

use LTO\AccountFactory;
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

    public function testSignSetTimestamp()
    {
        $transaction = (new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000))
            ->signWith($this->account);

        $this->assertIsInt($transaction->timestamp);
        $this->assertGreaterThan((time() - 5) * 1000, $transaction->timestamp);
    }

    public function testMultiSig()
    {
        $accountFactory = new AccountFactory('T');
        $account0 = $accountFactory->seed(self::ACCOUNT_SEED, 0);
        $account1 = $accountFactory->seed(self::ACCOUNT_SEED, 1);
        $account2 = $accountFactory->seed(self::ACCOUNT_SEED, 2);

        $transaction = new Transfer('3N3Cn2pYtqzj7N9pviSesNe8KG9Cmb718Y1', 10000);
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
