<?php

namespace LTO;

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
        $base58 = new \StephenHill\Base58();
        
        $account = $this->createMock(Account::class);
        $account->sign = (object)['publickey' => $base58->decode("8MeRTc26xZqPmQ3Q29RJBwtgtXDPwR7P9QNArymjPLVQ")];
        
        $chain = $this->createPartialMock(EventChain::class, ['getRandomNonce']);
        $chain->expects($this->never())->method('getRandomNonce');
        
        $chain->initFor($account, 'foo');
        
        $this->assertAttributeEquals('2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF', 'id', $chain);
        $this->assertEquals('8FjrD9Req4C61RcawRC5HaTUvuetU2BwABTiQBVheU2R', $chain->getLatestHash());
    }
    
    public function testInitFor()
    {
        $base58 = new \StephenHill\Base58();
        
        $account = $this->createMock(Account::class);
        $account->sign = (object)['publickey' => $base58->decode("8MeRTc26xZqPmQ3Q29RJBwtgtXDPwR7P9QNArymjPLVQ")];
        
        $chain = $this->createPartialMock(EventChain::class, ['getRandomNonce']);
        $chain->expects($this->once())->method('getRandomNonce')->willReturn(str_repeat("\0", 20));
        
        $chain->initFor($account);
        
        $this->assertAttributeEquals('2ar3wSjTm1fA33qgckZ5Kxn1x89gKKGi6TJsZjRoqb7sjUE8GZXjLaYCbCa2GX', 'id', $chain);
        $this->assertEquals('3NTzfLcXq1D5BRzhj9EyVbmAcLsz1pa6ZjdxRySbYze1', $chain->getLatestHash());
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

    public function testCreateProjectionIdSeedNonce()
    {
        $chain = $this->createPartialMock(EventChain::class, ['getRandomNonce']);
        $chain->expects($this->never())->method('getRandomNonce');

        $chain->id = '2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF';

        $this->assertEquals('2z4AmxL122aaTLyVy6rhEfXHGJMGuMja5LBfCU536ksVgRi1oeuWDhLBEBRe1q', $chain->createProjectionId('foo'));
    }

    public function testCreateProjectionId()
    {
        $chain = $this->createPartialMock(EventChain::class, ['getRandomNonce']);
        $chain->expects($this->once())->method('getRandomNonce')->willReturn(str_repeat("\0", 20));

        $chain->id = '2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF';

        $this->assertEquals('2yopB4AaT1phJ4YrXBwbQhimguSM9PhhP41TMYt5mofAZgs7H7iNYcT2eKqS8W', $chain->createProjectionId());
    }

    public function projectionIdProvider()
    {
        return [
            [true, '2z4AmxL122aaTLyVy6rhEfXHGJMGuMja5LBfCU536ksVgRi1oeuWDhLBEBRe1q'],
            [true, '2yopB4AaT1phJ4YrXBwbQhimguSM9PhhP41TMYt5mofAZgs7H7iNYcT2eKqS8W'],
            [true, '31E2kKp5TtUGx3MxyX5e2WGqoCfgD4uK2ym1rVx7fmGMsLGVUyydHar4uzFnr3'],
            [false, '2z4AmxL122aaTLyVy6rhEfXHGJMGueqGvF1FmfWVHECt7xEc6VSSqCCSZUfq7D'],
            [false, '2yopB4AaT1phJ4YrXBwbQhimguSM9goQDxq3vkKXxGzZ1DPhZxFKA7KHvVwSKf'],
            [false, '2z4AmxL12'],
            [false, '2z4AmxL12lolololollolololollolololollolololollolololollolololo'],
            [false, '2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF']
        ];
    }

    /**
     * @dataProvider projectionIdProvider
     */
    public function testIsValidProjectionId($expected, $projectionId)
    {
        $chain = new EventChain();
        $chain->id = '2b6QYLttL2R3CLGL4fUB9vaXXX4c5HJanjV5QecmAYLCrD52o6is1fRMGShUUF';

        $this->assertEquals($expected, $chain->isValidProjectionId($projectionId));
    }
}
