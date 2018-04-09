<?php

namespace LTO;

use PHPUnit_Framework_TestCase as TestCase;
use LTO\Account;
use LTO\EventChain;

/**
 * @covers LTO\EventChain
 */
class EventChainTest extends TestCase
{
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
    
    
    public function testInitFor()
    {
        $base58 = new \StephenHill\Base58();
        
        $account = $this->createMock(Account::class);
        $account->sign = (object)['publickey' => $base58->decode("8MeRTc26xZqPmQ3Q29RJBwtgtXDPwR7P9QNArymjPLVQ")];
        
        $chain = $this->createPartialMock(EventChain::class, ['getNonce']);
        $chain->method('getNonce')->willReturn(str_repeat("\0", 8));
        
        $chain->initFor($account);
        
        $this->assertAttributeEquals('L1hGimV7Pp2CFNUnTCitqWDbk9Zng3r3uc66dAG6hLwEx', 'id', $chain);
        $this->assertEquals('9HM1ykH7AxLgdCqBBeUhvoTH4jkq3zsZe4JGTrjXVENg', $chain->getLatestHash());
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
}
