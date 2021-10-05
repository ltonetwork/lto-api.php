<?php

declare(strict_types=1);

namespace LTO\Tests;

use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    public function encodeProvider()
    {
        return [
            'raw' => ['raw', 'test'],
            'base58' => ['base58', base58_encode('test')],
            'base64' => ['base64', base64_encode('test')],
            'hex' => ['hex', bin2hex('test')],
        ];
    }

    /**
     * @covers \LTO\encode
     * @dataProvider encodeProvider
     */
    public function testEncode(string $encoding, string $expected)
    {
        $this->assertEquals($expected, \LTO\encode('test', $encoding));
    }

    /**
     * @covers \LTO\decode
     * @dataProvider encodeProvider
     */
    public function testDecode(string $encoding, string $encoded)
    {
        $this->assertEquals('test', \LTO\decode($encoded, $encoding));
    }

    /**
     * @covers \LTO\decode
     * @dataProvider encodeProvider
     */
    public function testDecodeFailure(string $encoding)
    {
        if ($encoding === 'raw') {
            $this->expectNotToPerformAssertions();
            return;
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Failed to decode from '$encoding'.");

        \LTO\decode('@', $encoding);
    }

    public function valueProvider()
    {
        $binary = random_bytes(16);

        return [
            'empty string' => ['', hash('sha256', '', true)],
            '"foo"' => ['foo', hash('sha256', 'foo', true)],
            'binary' => [$binary, hash('sha256', $binary, true)],
        ];
    }

    /**
     * @covers \LTO\sha256
     * @dataProvider valueProvider
     */
    public function testSha256(string $input, string $hash)
    {
        $this->assertEquals($hash, \LTO\sha256($input));
    }


    public function validAddressProvider()
    {
        return [
            'base58 T' => ['3MqXcb6KxDmqxGpTRkh3wEQTHiZNoAe1toF', 'base58'],
            'base58' => ['3JhtAMEGwVA1ZQCpmZHdkiB1QSXu51i2chM', 'base58'],
            'base64' => ['AUxJLFht2i1MBCh6MHk5axWhwZU8Vt8eRcg=', 'base64'],
            'raw' => [base58_decode('3MqXcb6KxDmqxGpTRkh3wEQTHiZNoAe1toF'), 'raw'],
        ];
    }

    /**
     * @covers \LTO\is_valid_address
     * @dataProvider validAddressProvider
     */
    public function testValidAddress(string $address, string $encoding)
    {
        $this->assertTrue(\LTO\is_valid_address($address, $encoding));
    }

    public function invalidAddressProvider()
    {
        return [
            'empty string' => ['', 'base58'],
            'base58' => ['HpGAY9xsEZcJhNpo5pWeJn', 'base58'],
            'base64' => ['iCuFawHy0nMBHxyI3qzLMw==', 'base64'],
            'not base58' => ['foo bar @', 'base58'],
            'not base64' => ['foo bar @', 'base64'],
            'random bytes' => [random_bytes(16), 'raw'],
        ];
    }

    /**
     * @covers \LTO\is_valid_address
     * @dataProvider invalidAddressProvider
     */
    public function testInvalidAddress(string $address, string $encoding)
    {
        $this->assertFalse(\LTO\is_valid_address($address, $encoding));
    }

    public function testGetPublicProperties()
    {
        $object = new class {
            private $foo = 1;
            protected $bar = 2;
            public $qux = 3;
        };

        $this->assertEquals(['qux' => 3], \LTO\get_public_properties($object));
    }
}
