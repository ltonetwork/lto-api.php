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
        $event->expects($this->atLeastOnce())->method('getHash')
            ->willReturn("J26EAStUDXdRUMhm1UcYXUKtJWTkcZsFpxHRzhkStzbS");

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

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Chain id not set
     */
    public function testCreateResourceIdWithoutId()
    {
        $chain = new EventChain();
        $chain->createResourceId();
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
            [false, '2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF'],
            [false, '= incorrectly encoded']
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

    protected function createTestChain()
    {
        $chain = new EventChain('JEKNVnkbo3jqSHT8tfiAKK4tQTFK7jbx8t18wEEnygya');

        $chain->events[0] = new Event();
        $chain->events[0]->previous = 'BRhevpwYsXv7LD1N4kodG7P6fJrRhPPxqFe4RDq8MwJv';
        $chain->events[0]->hash = '3yMApqCuCjXDWPrbjfR5mjCPTHqFG8Pux1TxQrEM35jj';

        $chain->events[1] = new Event();
        $chain->events[1]->previous = '3yMApqCuCjXDWPrbjfR5mjCPTHqFG8Pux1TxQrEM35jj';
        $chain->events[1]->hash = 'J26EAStUDXdRUMhm1UcYXUKtJWTkcZsFpxHRzhkStzbS';

        $chain->events[2] = new Event();
        $chain->events[2]->previous = 'J26EAStUDXdRUMhm1UcYXUKtJWTkcZsFpxHRzhkStzbS';
        $chain->events[2]->hash = '3HZd1nBeva2fLUUEygGakdCQr84dcUz6J3wGTUsHdnhq';

        return $chain;
    }

    /**
     * Create an event, setting the properties.
     *
     * @param array $data
     * @return Event
     */
    protected function createEvent(array $data): Event
    {
        $event = new Event();

        foreach ($data as $key => $value) {
            $event->$key = $value;
        }

        return $event;
    }

    public function getEventsAfterProvider()
    {
        return [
            [
                'BRhevpwYsXv7LD1N4kodG7P6fJrRhPPxqFe4RDq8MwJv',
                [
                    '3yMApqCuCjXDWPrbjfR5mjCPTHqFG8Pux1TxQrEM35jj',
                    'J26EAStUDXdRUMhm1UcYXUKtJWTkcZsFpxHRzhkStzbS',
                    '3HZd1nBeva2fLUUEygGakdCQr84dcUz6J3wGTUsHdnhq'
                ]
            ],
            [
                '3yMApqCuCjXDWPrbjfR5mjCPTHqFG8Pux1TxQrEM35jj',
                [
                    'J26EAStUDXdRUMhm1UcYXUKtJWTkcZsFpxHRzhkStzbS',
                    '3HZd1nBeva2fLUUEygGakdCQr84dcUz6J3wGTUsHdnhq'
                ]
            ],
            [
                'J26EAStUDXdRUMhm1UcYXUKtJWTkcZsFpxHRzhkStzbS',
                [
                    '3HZd1nBeva2fLUUEygGakdCQr84dcUz6J3wGTUsHdnhq'
                ]
            ],
            [
                '3HZd1nBeva2fLUUEygGakdCQr84dcUz6J3wGTUsHdnhq',
                []
            ]
        ];
    }

    /**
     * @dataProvider getEventsAfterProvider
     *
     * @param string   $hash
     * @param string[] $expected
     */
    public function testGetPartial($hash, $expected)
    {
        $chain = $this->createTestChain();
        $partial = $chain->getPartialAfter($hash);

        $this->assertInstanceOf(EventChain::class, $partial);

        $actual = array_map(static function($event) {
            return $event->hash;
        }, $partial->events);

        $this->assertSame($expected, $actual);
        $this->assertEquals('3HZd1nBeva2fLUUEygGakdCQr84dcUz6J3wGTUsHdnhq', $partial->getLatestHash());
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testGetPartialOutOfBounds()
    {
        $chain = $this->createTestChain();
        $chain->getPartialAfter('Aw2Rum85dWFcUKnY6wZPmpoJXK54zENePuLPKjvjhviU');
    }

    public function testJsonSerialize()
    {
        $dataEvent1 = [
            'body' => '2V8NsSXqmzDhh9vqZVMZtArjkWGBV57YkWww8G6YX55',
            'timestamp' => (new \DateTime('2018-01-01T00:00:00+00:00'))->getTimestamp(),
            'previous' => 'BRhevpwYsXv7LD1N4kodG7P6fJrRhPPxqFe4RDq8MwJv',
            'signkey' => '8TxFbgGPKVhuauHJ47vn3C74eVugAghTGou35Wtd51Mj',
            'hash' => 'BjZQ4HrN8nHEUVAzcujtv4SyDrzLTND11ZRHzeowMH1J',
            'signature' => '3S72dRFjpdnbrdBneRpBxzGb99eEE6X3wCnKC4GiN2MwE1i3Xx1zVtzFeeUVwq3qMTECn8HzEJPJZCgU2iEE7227',
        ];
        $dataEvent2 = [
            'body' => 'HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv',
            'timestamp' => (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(),
            'previous' => 'BjZQ4HrN8nHEUVAzcujtv4SyDrzLTND11ZRHzeowMH1J',
            'signkey' => 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y',
            'hash' => '7LrHo9aBhxjjSmLrZxhU6o7J6zW8z96NtFcmL65Pbko',
            'signature' => '258KnaZxcx4cA9DUWSPw8QwBokRGzFDQmB4BH9MRJhoPJghsXoAZ7KnQ2DWR7ihtjXzUjbsXtSeup4UDcQ2L6RDL',
        ];

        $chain = new EventChain('JEKNVnkbo3jqSHT8tfiAKK4tQTFK7jbx8t18wEEnygya');
        $chain->events[0] = $this->createEvent($dataEvent1);
        $chain->events[1] = $this->createEvent($dataEvent2);

        $expected = (object)[
            'id' => 'JEKNVnkbo3jqSHT8tfiAKK4tQTFK7jbx8t18wEEnygya',
            'events' => [(object)$dataEvent1, (object)$dataEvent2],
            'latest_hash' => '7LrHo9aBhxjjSmLrZxhU6o7J6zW8z96NtFcmL65Pbko',
        ];

        $data = $chain->jsonSerialize();

        $this->assertEquals($expected, $data);
    }
}
