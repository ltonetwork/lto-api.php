<?php

namespace LTO\Tests;

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
        $event = new Event($data, '72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW');
        
        $this->assertAttributeEquals('HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv', 'body', $event);
        $this->assertAttributeInternalType('int', 'timestamp', $event);
        $this->assertAttributeEquals('72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW', 'previous', $event);
        
        return $event;
    }
    
    /**
     * @depends testConstruct
     */
    public function testGetMessage(Event $event)
    {
        $event->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();
        $event->signkey = 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y';
        
        $expected = join("\n", [
            'HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv',
            '1519862400',
            '72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW',
            'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y'
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
        $event->signature = '258KnaZxcx4cA9DUWSPw8QwBokRGzFDQmB4BH9MRJhoPJghsXoAZ7KnQ2DWR7ihtjXzUjbsXtSeup4UDcQ2L6RDL';
        
        $this->assertTrue($event->verifySignature());
    }
    
    /**
     * @depends testGetMessage
     */
    public function testVerifySignatureFail($event)
    {
        $event->timestamp = (new \DateTime('2018-02-20T00:00:00+00:00'))->getTimestamp(); // Back dated
        $event->signature = '258KnaZxcx4cA9DUWSPw8QwBokRGzFDQmB4BH9MRJhoPJghsXoAZ7KnQ2DWR7ihtjXzUjbsXtSeup4UDcQ2L6RDL';
        
        $this->assertFalse($event->verifySignature());
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
        $event->signature = '258KnaZxcx4cA9DUWSPw8QwBokRGzFDQmB4BH9MRJhoPJghsXoAZ7KnQ2DWR7ihtjXzUjbsXtSeup4UDcQ2L6RDL';
        
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

    public function eventProvider()
    {
        $data = [
            'body' => 'HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv',
            'timestamp' => (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp(),
            'previous' => '72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW',
            'signkey' => 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y',
            'hash' => 'Bpq9rZt12Gv44dkXFw8RmLYzbaH2HBwPQJ6KihdLe5LG',
            'signature' => '258KnaZxcx4cA9DUWSPw8QwBokRGzFDQmB4BH9MRJhoPJghsXoAZ7KnQ2DWR7ihtjXzUjbsXtSeup4UDcQ2L6RDL',
        ];

        $original = [
            'timestamp' => (new \DateTime('2018-01-01T00:00:00+00:00'))->getTimestamp(),
            'previous' => 'H8qGksJvpAS77cjoTDfmabuob4KHtQCQeqS5s915WQmd',
            'signkey' => '8TxFbgGPKVhuauHJ47vn3C74eVugAghTGou35Wtd51Mj',
            'hash' => 'H3gbBd2sUczYCEqPK6LUPvVLqKqHdRNFEaaqAQe83mRQ',
            'signature' => '3S72dRFjpdnbrdBneRpBxzGb99eEE6X3wCnKC4GiN2MwE1i3Xx1zVtzFeeUVwq3qMTECn8HzEJPJZCgU2iEE7227',
        ];

        return [
            [$this->createEvent($data), (object)$data],
            [
                $this->createEvent($data + ['original' => $this->createEvent($original + ['body' => $data['body']])]),
                (object)($data + ['original' => (object)$original])
            ],
        ];
    }

    /**
     * @dataProvider eventProvider
     */
    public function testJsonSerialize(Event $event, \stdClass $expected)
    {
        $data = $event->jsonSerialize();

        $this->assertEquals($expected, $data);
    }
}
