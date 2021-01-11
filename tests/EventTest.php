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
        
        $this->assertSame('HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv', $event->body);
        $this->assertIsInt($event->timestamp);
        $this->assertSame('72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW', $event->previous);
        
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

        $this->assertSame($expected, $event->getMessage());
        
        return $event;
    }
    
    public function testGetMessageNoBody()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Body unknown');

        $event = new Event();
        $event->getMessage();
    }
    
    public function testGetMessageNoSignkey()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('First set signkey before creating message');

        $event = new Event(['foo' => 'bar', 'color' => 'red']);
        $event->getMessage();
    }
    
    /**
     * @depends testGetMessage
     */
    public function testGetHash(Event $event)
    {
        $this->assertSame('Bpq9rZt12Gv44dkXFw8RmLYzbaH2HBwPQJ6KihdLe5LG', $event->getHash());
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

    public function testVerifySignatureNoSignature()
    {
        $this->expectException(\BadMethodCallException::class);

        $event = new Event();
        $event->signkey = 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y';
        
        $event->verifySignature();
    }
    
    public function testVerifySignatureNoSignkey()
    {
        $this->expectException(\BadMethodCallException::class);

        $event = new Event();
        $event->signature = '258KnaZxcx4cA9DUWSPw8QwBokRGzFDQmB4BH9MRJhoPJghsXoAZ7KnQ2DWR7ihtjXzUjbsXtSeup4UDcQ2L6RDL';
        
        $event->verifySignature();
    }

    /**
     * @depends testConstruct
     */
    public function testSignWith(Event $event)
    {
        $event->timestamp = (new \DateTime('2018-03-01T00:00:00+00:00'))->getTimestamp();

        $expected = join("\n", [
            'HeFMDcuveZQYtBePVUugLyWtsiwsW4xp7xKdv',
            '1519862400',
            '72gRWx4C1Egqz9xvUBCYVdgh7uLc5kmGbjXFhiknNCTW',
            'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y'
        ]);

        $account = $this->createMock(Account::class);
        $account->expects($this->once())->method('getPublicSignKey')
            ->willReturn('FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y');
        $account->expects($this->once())->method('sign')
            ->with($expected, 'base58')
            ->willReturn('4pwrLbWSYqE7st7fCGc2fW2eA33DP1uE4sBm6onfwYNk4M8Av9u4Mqx1R77sVzRRofQgoHGTLRh8pRBRzp5JGBo9');

        $ret = $event->signWith($account);
        $this->assertSame($event, $ret);

        $this->assertEquals('FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y', $event->signkey);
        $this->assertEquals('4pwrLbWSYqE7st7fCGc2fW2eA33DP1uE4sBm6onfwYNk4M8Av9u4Mqx1R77sVzRRofQgoHGTLRh8pRBRzp5JGBo9', $event->signature);
        $this->assertEquals('Bpq9rZt12Gv44dkXFw8RmLYzbaH2HBwPQJ6KihdLe5LG', $event->hash);
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
            $event->{$key} = $value;
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
