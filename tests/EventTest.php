<?php

namespace LTO;

use PHPUnit_Framework_TestCase as TestCase;
use LTO\Account;
use LTO\Event;

/**
 * @covers LTO\Event
 */
class EventTest extends TestCase
{
    public function testConstruct()
    {
        $data = ['foo' => 'bar', 'color' => 'red'];
        $event = new Event($data, "72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW");
        
        $this->assertAttributeEquals('HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv', 'body', $event);
        $this->assertAttributeInstanceOf(\DateTime::class, 'timestamp', $event);
        $this->assertAttributeEquals("72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW", 'previous', $event);
        
        return $event;
    }
    
    /**
     * @depends testConstruct
     */
    public function testGetMessage(Event $event)
    {
        $event->timestamp = new \DateTime('2018-03-01T00:00:00+00:00');
        $event->signkey = "FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y";
        
        $expected = join("\n", [
            "HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv",
            '2018-03-01T00:00:00+00:00',
            "72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW",
            "FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y"
        ]);

        $this->assertEquals($expected, $event->getMessage());
        
        return $event;
    }
    
    /**
     * @depends testGetMessage
     */
    public function testGetHash(Event $event)
    {
        $this->assertEquals('47FmxvJ4v1Bnk4SGSwrHcncX5t5u3eAjmc6QJgbR5nn8', $event->getHash());
    }
    
    /**
     * @depends testGetMessage
     */
    public function testVerifySignature($event)
    {
        $event->signature = "Szr7uLhwirqEuVJ9SBPuAgvFAbuiMG23FbCsVNbptLbMH7uzrR5R23Yze83YGe98HawMzjvEMWgsJhdRQDXw8Br";
        
        $this->assertTrue($event->verifySignature());
    }
    
    /**
     * @depends testGetMessage
     */
    public function testVerifySignatureFail($event)
    {
        $event->timestamp = new \DateTime('2018-02-20T00:00:00+00:00'); // Back dated
        $event->signature = "Szr7uLhwirqEuVJ9SBPuAgvFAbuiMG23FbCsVNbptLbMH7uzrR5R23Yze83YGe98HawMzjvEMWgsJhdRQDXw8Br";
        
        $this->assertFalse($event->verifySignature());
    }
    
    public function testSignWith()
    {
        $event = new Event([], '');
        
        $account = $this->createMock(Account::class);
        $account->expects($this->once())->method('sign')->with($this->identicalTo($event))->willReturn($event);
        
        $ret = $event->signWith($account);
        
        $this->assertSame($event, $ret);
    }
}
