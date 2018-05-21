<?php

namespace LTO;

use PHPUnit\Framework\TestCase;
use LTO\Account;
use LTO\Event;
use LTO\EventChain;

/**
 * @covers \LTO\Event
 */
class EventTest extends TestCase
{
    public function testConstruct()
    {
        $data = ['foo' => 'bar', 'color' => 'red'];
        $event = new Event($data, "72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW");
        
        $this->assertAttributeEquals('HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv', 'body', $event);
        $this->assertAttributeInternalType('int', 'timestamp', $event);
        $this->assertAttributeEquals("72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW", 'previous', $event);
        
        return $event;
    }
    
    /**
     * @depends testConstruct
     */
    public function testGetMessage(Event $event)
    {
        $event->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();
        $event->signkey = "FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y";
        
        $expected = join("\n", [
            "HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv",
            '1519862400',
            "72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW",
            "FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y"
        ]);

        $this->assertEquals($expected, $event->getMessage());
        
        return $event;
    }
    
    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Body unknown
     */
    public function testGetMessageNoBody()
    {
        $event = new Event();
        $event->getMessage();
    }
    
    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage First set signkey before creating message
     */
    public function testGetMessageNoSignkey()
    {
        $event = new Event(['foo' => 'bar', 'color' => 'red']);
        $event->getMessage();
    }
    
    /**
     * @depends testGetMessage
     */
    public function testGetHash(Event $event)
    {
        $this->assertEquals('Bpq9rZt12Gv44dkXFw8RmLYzbaH2HBwPQJ6KihdLe5LG', $event->getHash());
    }

    /**
     * @depends testGetMessage
     */
    public function testVerifySignature($event)
    {
        $event->signature = "258KnaZxcx4cA9DUWSPw8QwBokRGzFDQmB4BH9MRJhoPJghsXoAZ7KnQ2DWR7ihtjXzUjbsXtSeup4UDcQ2L6RDL";
        
        $this->assertTrue($event->verifySignature());
    }
    
    /**
     * @depends testGetMessage
     */
    public function testVerifySignatureFail($event)
    {
        $event->timestamp = (new \DateTime('2018-02-20T00:00:00+00:00'))->getTimestamp(); // Back dated
        $event->signature = "258KnaZxcx4cA9DUWSPw8QwBokRGzFDQmB4BH9MRJhoPJghsXoAZ7KnQ2DWR7ihtjXzUjbsXtSeup4UDcQ2L6RDL";
        
        $this->assertFalse($event->verifySignature());
    }

    /**
     * @depends testGetMessage
     */
    public function testGetResourceVersion(Event $event)
    {
        $this->assertEquals('Bpq9rZt1', $event->getResourceVersion());
    }
    
    /**
     * @expectedException BadMethodCallException
     */
    public function testVerifySignatureNoSignature()
    {
        $event = new Event();
        $event->signkey = 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y';
        
        $event->verifySignature();
    }
    
    /**
     * @expectedException BadMethodCallException
     */
    public function testVerifySignatureNoSignkey()
    {
        $event = new Event();
        $event->signature = "258KnaZxcx4cA9DUWSPw8QwBokRGzFDQmB4BH9MRJhoPJghsXoAZ7KnQ2DWR7ihtjXzUjbsXtSeup4UDcQ2L6RDL";
        
        $event->verifySignature();
    }
    
    public function testSignWith()
    {
        $event = new Event([], '');
        
        $account = $this->createMock(Account::class);
        $account->expects($this->once())->method('signEvent')->with($this->identicalTo($event))->willReturn($event);
        
        $ret = $event->signWith($account);
        
        $this->assertSame($event, $ret);
    }
    
    public function testAddTo()
    {
        $event = new Event([], '');
        
        $chain = $this->createMock(EventChain::class);
        $chain->expects($this->once())->method('add')->with($this->identicalTo($event))->willReturn($event);
        
        $ret = $event->addTo($chain);
        
        $this->assertSame($event, $ret);
    }
}
