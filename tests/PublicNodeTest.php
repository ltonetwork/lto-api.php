<?php

declare(strict_types=1);

namespace LTO\Tests;

use LTO\PublicNode;
use LTO\Transaction\SetScript;
use LTO\Transaction\Transfer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LTO\PublicNode
 */
class PublicNodeTest extends TestCase
{
    use CustomAsserts;

    protected const TX_DATA = [
        'id' => '7cCeL1qwd9i6u8NgMNsQjBPxVhrME2BbfZMT1DF9p4Yi',
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
        'height' => 1215007,
    ];

    /** @var PublicNode&MockObject */
    protected $node;

    public function setUp(): void
    {
        $this->node = $this->getMockBuilder(PublicNode::class)
            ->onlyMethods(['doHttpRequest'])
            ->setConstructorArgs(['http://example.com', 'secret'])
            ->getMock();
    }

    public function testConstruct()
    {
        $this->assertEquals('http://example.com', $this->node->getUrl());
        $this->assertEquals('secret', $this->node->getApiKey());
    }

    public function testGetRequest()
    {
        $this->node->expects($this->once())->method('doHttpRequest')
            ->with(
                'http://example.com/wallet/addresses',
                [
                    'method' => 'GET',
                    'header' => join("\r\n", [
                        'Accept: application/json',
                        'X-Api-Key: secret',
                    ]),
                    'content' => null,
                ],
            )
            ->willReturn([
                'header' => [
                    "HTTP/1.1 200 Success",
                    "Content-Type: application/json"
                ],
                'content' => json_encode(["3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM"])
            ]);

        $result = $this->node->get('/wallet/addresses');

        $this->assertEquals(["3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM"], $result);
    }

    public function testPostRequest()
    {
        $data = [
            "message" => "hello",
            "signature" => "4mubkkCgFjv7KVGQNF3tRHpkvyEmsmcVNPqzMfU6B1jnmsbLu8tYRat7NrQ5ViKSumW5eNBK2sudwZ45P22PJV1x",
            "publickey" => "7gghhSwKRvshZwwh6sG97mzo1qoFtHEQK7iM4vGcnEt7",
        ];

        $this->node->expects($this->once())->method('doHttpRequest')
            ->with(
                'http://example.com/addresses/verify/3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM',
                [
                    'method' => 'POST',
                    'header' => join("\r\n", [
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'X-Api-Key: secret',
                    ]),
                    'content' => json_encode($data),
                ],
            )
            ->willReturn([
                'header' => [
                    "HTTP/1.1 200 Success",
                    "Content-Type: application/json"
                ],
                'content' => json_encode(42)
            ]);

        $result = $this->node->post('/addresses/verify/3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM', $data);

        $this->assertEquals(42, $result);
    }

    public function testDeleteRequest()
    {
        $this->node->expects($this->once())->method('doHttpRequest')
            ->with(
                'http://example.com/addresses/3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM',
                [
                    'method' => 'DELETE',
                    'header' => join("\r\n", [
                        'Accept: application/json',
                        'X-Api-Key: secret',
                    ]),
                    'content' => null,
                ],
            )
            ->willReturn([
                'header' => [
                    "HTTP/1.1 200 Success",
                    "Content-Type: application/json"
                ],
                'content' => json_encode(42)
            ]);

        $result = $this->node->delete('/addresses/3NBcx7AQqDopBj3WfwCVARNYuZyt1L9xEVM');

        $this->assertEquals(42, $result);
    }


    public function testGetTransaction()
    {
        $this->node->expects($this->once())->method('doHttpRequest')
            ->with(
                'http://example.com/transactions/info/7cCeL1qwd9i6u8NgMNsQjBPxVhrME2BbfZMT1DF9p4Yi',
                [
                    'method' => 'GET',
                    'header' => join("\r\n", [
                        'Accept: application/json',
                        'X-Api-Key: secret',
                    ]),
                    'content' => null,
                ]
            )
            ->willReturn([
                'header' => [
                    "HTTP/1.1 200 Success",
                    "Content-Type: application/json"
                ],
                'content' => json_encode(self::TX_DATA),
            ]);

        $transaction = $this->node->getTransaction("7cCeL1qwd9i6u8NgMNsQjBPxVhrME2BbfZMT1DF9p4Yi");

        $this->assertInstanceOf(Transfer::class, $transaction);
        $this->assertEqualsAsJson(self::TX_DATA, $transaction);
    }

    public function testGetUnconfirmed()
    {
        $data = self::TX_DATA;
        unset($data['height']);

        $this->node->expects($this->once())->method('doHttpRequest')
            ->with(
                'http://example.com/transactions/unconfirmed',
                [
                    'method' => 'GET',
                    'header' => join("\r\n", [
                        'Accept: application/json',
                        'X-Api-Key: secret',
                    ]),
                    'content' => null,
                ],
            )
            ->willReturn([
                'header' => [
                    "HTTP/1.1 200 Success",
                    "Content-Type: application/json"
                ],
                'content' => json_encode([$data]),
            ]);

        $transactions = $this->node->getUnconfirmed();

        $this->assertContainsOnlyInstancesOf(Transfer::class, $transactions);
        $this->assertCount(1, $transactions);
        $this->assertEqualsAsJson($data, $transactions[0]);
    }

    public function testBroadcast()
    {
        $data = self::TX_DATA;
        unset($data['id'], $data['height']);

        $transaction = $this->createMock(Transfer::class);
        $transaction->expects($this->once())->method('isSigned')->willReturn(true);
        $transaction->expects($this->once())->method('jsonSerialize')->willReturn($data);

        $this->node->expects($this->once())->method('doHttpRequest')
            ->with(
                'http://example.com/transactions/broadcast',
                [
                    'method' => 'POST',
                    'header' => join("\r\n", [
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'X-Api-Key: secret',
                    ]),
                    'content' => json_encode($data)
                ]
            )
            ->willReturn([
                'header' => [
                    "HTTP/1.1 200 Success",
                    "Content-Type: application/json"
                ],
                'content' => json_encode(self::TX_DATA),
            ]);

        $broadcastedTx = $this->node->broadcast($transaction);

        $this->assertInstanceOf(Transfer::class, $broadcastedTx);
        $this->assertEqualsAsJson(self::TX_DATA, $broadcastedTx);
    }

    public function testBroadcastUnsignedTransaction()
    {
        $transaction = $this->createMock(Transfer::class);
        $transaction->expects($this->once())->method('isSigned')->willReturn(false);
        $transaction->expects($this->never())->method('jsonSerialize');

        $this->node->expects($this->never())->method('doHttpRequest');

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Transaction is not signed");

        $this->node->broadcast($transaction);
    }

    public function testCompile()
    {
        $script = "sigVerify(tx.bodyBytes, tx.proofs[0], tx.senderPublicKey)";
        $compiledScript = "base64:AQkAAfQAAAADCAUAAAACdHgAAAAJYm9keUJ5dGVzCQABkQAAAAIIBQAAAAJ0eAAAAAZwcm9vZnMAAAAAAAAAAAAIBQAAAAJ0eAAAAA9zZW5kZXJQdWJsaWNLZXmmsz2x";

        $this->node->expects($this->once())->method('doHttpRequest')
            ->with(
                'http://example.com/utils/script/compile',
                [
                    'method' => 'POST',
                    'header' => join("\r\n", [
                        'Content-Type: text/plain',
                        'Accept: application/json',
                        'X-Api-Key: secret',
                    ]),
                    'content' => $script,
                ]
            )
            ->willReturn([
                'header' => [
                    "HTTP/1.1 200 Success",
                    "Content-Type: application/json"
                ],
                'content' => json_encode([
                    "script" => $compiledScript,
                    "complexity" => 115,
                    "extraFee" => 100000000,
                ]),
            ]);

        $setScriptTx = $this->node->compile($script);

        $this->assertInstanceOf(SetScript::class, $setScriptTx);
        $this->assertEquals($compiledScript, $setScriptTx->script);
    }
}
