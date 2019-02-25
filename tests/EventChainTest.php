<?php

namespace LTO\Tests;

use LTO\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use LTO\Account;
use LTO\EventChain;

/**
 * @covers \LTO\EventChain
 */
class EventChainTest extends TestCase
{
    use \Jasny\TestHelper;
    
    public function testConstruct()
    {
        $chain = new EventChain();
        
        $this->assertNull($chain->getLatestHash());
    }
    
    public function testConstructId()
    {
        $chain = new EventChain('L1hGimV7Pp2CFNUnTCitqWDbk9Zng3r3uc66dAG6hLwEx');
        
        $this->assertAttributeEquals('L1hGimV7Pp2CFNUnTCitqWDbk9Zng3r3uc66dAG6hLwEx', 'id', $chain);
        $this->assertEquals('9HM1ykH7AxLgdCqBBeUhvoTH4jkq3zsZe4JGTrjXVENg', $chain->getLatestHash());
    }
    
    public function testConstructLatestHash()
    {
        $chain = new EventChain('L1hGimV7Pp2CFNUnTCitqWDbk9Zng3r3uc66dAG6hLwEx', '3yMApqCuCjXDWPrbjfR5mjCPTHqFG8Pux1TxQrEM35jj');
        
        $this->assertAttributeEquals('L1hGimV7Pp2CFNUnTCitqWDbk9Zng3r3uc66dAG6hLwEx', 'id', $chain);
        $this->assertEquals('3yMApqCuCjXDWPrbjfR5mjCPTHqFG8Pux1TxQrEM35jj', $chain->getLatestHash());
    }
    
    public function testAdd()
    {
        $event = $this->createMock(Event::class);
        $event->method('getHash')->willReturn("J26EAStUDXdRUMhm1UcYXUKtJWTkcZsFpxHRzhkStzbS");
        
        $chain = new EventChain('L1hGimV7Pp2CFNUnTCitqWDbk9Zng3r3uc66dAG6hLwEx', '3yMApqCuCjXDWPrbjfR5mjCPTHqFG8Pux1TxQrEM35jj');
        
        $chain->add($event);
        $this->assertEquals('J26EAStUDXdRUMhm1UcYXUKtJWTkcZsFpxHRzhkStzbS', $chain->getLatestHash());
    }
    
    
    public function testGetRandomNonce()
    {
        $chain = new EventChain();
        
        $nonce = $this->callPrivateMethod($chain, 'getRandomNonce');
        $this->assertEquals(20, strlen($nonce));
    }
    
    public function testInitForSeedNonce()
    {
        $account = $this->createMock(Account::class);
        $account->sign = (object)['publickey' => base58_decode("GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY")];

        /** @var EventChain&MockObject $chain */
        $chain = $this->createPartialMock(EventChain::class, ['getRandomNonce']);
        $chain->expects($this->never())->method('getRandomNonce');
        
        $chain->initFor($account, 'foo');
        
        $this->assertAttributeEquals('2b6QYLttL2R3CLGL4fUB9vaXXX4c5PRhHhCS51CZQodgu7ay9BpMNdJ6mZ8hyF', 'id', $chain);
        $this->assertEquals('5S5qWhWs228toGUXX9DULHLF8Xfr7Xd8R2Lc4zQd4krj', $chain->getLatestHash());
    }
    
    public function testInitFor()
    {
        $account = $this->createMock(Account::class);
        $account->sign = (object)['publickey' => base58_decode("GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY")];

        /** @var EventChain&MockObject $chain */
        $chain = $this->createPartialMock(EventChain::class, ['getRandomNonce']);
        $chain->expects($this->once())->method('getRandomNonce')->willReturn(str_repeat("\0", 20));
        
        $chain->initFor($account);
        
        $this->assertAttributeEquals('2ar3wSjTm1fA33qgckZ5Kxn1x89gKRPpbR2EE61c5rRMnNk4cedDhYQxBE1E7k', 'id', $chain);
        $this->assertEquals('3mG9WaAizdw15Xouv4adFGY131Bims8m5BTQVyv1YU7n', $chain->getLatestHash());
    }
    
    /**
     * @expectedException \BadMethodCallException
     */
    public function testInitForExisting()
    {
        $account = $this->createMock(Account::class);
        
        $chain = $this->createPartialMock(EventChain::class, ['getNonce']);
        $chain->id = '123';
        
        $chain->initFor($account);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInitForInvalidAccount()
    {
        $account = $this->createMock(Account::class);
        
        $chain = $this->createPartialMock(EventChain::class, ['getNonce']);
        
        $chain->initFor($account);
    }


    public function testCreateResourceIdSeedNonce()
    {
        $chain = $this->createPartialMock(EventChain::class, ['getRandomNonce']);
        $chain->expects($this->never())->method('getRandomNonce');
        $chain->id = '2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF';

        $this->assertEquals('2z4AmxL122aaTLyVy6rhEfXHGJMGuUnViUhw3D7XC4VcycnkEwkHXXdxg73vLb', $chain->createResourceId('foo'));
    }

    public function testCreateResourceId()
    {
        $chain = $this->createPartialMock(EventChain::class, ['getRandomNonce']);
        $chain->expects($this->once())->method('getRandomNonce')->willReturn(str_repeat("\0", 20));
        $chain->id = '2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF';

        $this->assertEquals('2yopB4AaT1phJ4YrXBwbQhimguSM9Wkd2CXjCHvZs7HHrswqiQZ9rSkp5cGwJG', $chain->createResourceId());
    }

    public function projectionIdProvider()
    {
        return [
            [true, '2z4AmxL122aaTLyVy6rhEfXHGJMGuUnViUhw3D7XC4VcycnkEwkHXXdxg73vLb'],
            [true, '2yopB4AaT1phJ4YrXBwbQhimguSM9Wkd2CXjCHvZs7HHrswqiQZ9rSkp5cGwJG'],
            [true, '315qxHsiGMAHxHVjiNN2aU2MWthjDTJr4Z71cNFgppCpZPhtX237eJ97itWHSX'],
            [false, '2z4AmxL122aaTLyVy6rhEfXHGJMGueqGvF1FmfWVHECt7xEc6VSSqCCSZUfq7D'],
            [false, '2yopB4AaT1phJ4YrXBwbQhimguSM9goQDxq3vkKXxGzZ1DPhZxFKA7KHvVwSKf'],
            [false, '2z4AmxL12'],
            [false, '2ytJBjPHGLuEKYD5oQs54a367ucFkZaKYSXXkzjHbqHMS3kBknSUtrmosTNHKL'],
            [false, '2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF']
        ];
    }

    /**
     * @dataProvider projectionIdProvider
     */
    public function testIsValidResourceId($expected, $projectionId)
    {
        $chain = new EventChain();
        $chain->id = '2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF';

        $this->assertEquals($expected, $chain->isValidResourceId($projectionId));
    }
}
