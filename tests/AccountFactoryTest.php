<?php

namespace LTO\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Constraint\IsEqual as IsEqualConstraint;
use LTO\Account;
use LTO\AccountFactory;

/**
 * @covers \LTO\AccountFactory
 */
class AccountFactoryTest extends TestCase
{
    /**
     * @var string
     */
    public $seedText = "manage manual recall harvest series desert melt police rose hollow moral pledge kitten"
            . " position add";
    
    /**
     * Asserts variable is equals to Base58 encoded string.
     *
     * @param mixed  $encoded
     * @param mixed  $actual
     * @param string $message
     */
    public static function assertBase58Equals($encoded, $actual, $message = '')
    {
        $value = is_string($actual) ? base58_encode($actual) : $actual;
        
        $constraint = new IsEqualConstraint($encoded);

        static::assertThat($value, $constraint, $message);
    }
    
    
    /**
     * @see https://specs.livecontracts.io/cryptography.html#asymmetric-encryption
     */
    public function testCreateAccountSeed()
    {
        $factory = new AccountFactory('L', 0);
        $seed = $factory->createAccountSeed($this->seedText);
        
        $this->assertEquals("ETYQWXzC2h8VXahYdeUTXNPXEkan3vi9ikXbn912ijiw", base58_encode($seed));
    }

    
    public function createAddressProvider()
    {
        return [
            [ "3JmCa4jLVv7Yn2XkCnBUGsa7WNFVEMxAfWe", 'L' ],
            [ "3JmCa4jLVv7Yn2XkCnBUGsa7WNFVEMxAfWe", 0x4C ],
            [ "3MyuPwbiobZFnZzrtyY8pkaHoQHYmyQxxY1", 'T' ],
            [ "3MyuPwbiobZFnZzrtyY8pkaHoQHYmyQxxY1", 0x54 ]
        ];
    }
    
    /**
     * @dataProvider createAddressProvider
     * 
     * @param string     $expected
     * @param string|int $network
     */
    public function testCreateAddress($expected, $network)
    {
        $factory = new AccountFactory($network, 0);
        
        $publickey = base58_decode("GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY");
        $address = $factory->createAddress($publickey);
        
        $this->assertEquals($expected, base58_encode($address));
    }

    public function convertSignToEncryptProvider()
    {
        return [
            [
                (object)['publickey' => '6fDod1xcVj4Zezwyy3tdPGHkuDyMq8bDHQouyp5BjXsX'],
                (object)['publickey' => 'GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY']
            ],
            [
                (object)['publickey' => "EZa2ndj6h95m3xm7DxPQhrtANvhymNC7nWQ3o1vmDJ4x"],
                (object)['publickey' => "gVVExGUK4J5BsxwUfYsFkkjpn6A7BcvYdmARL28GBRc"]
            ],
            [
                (object)['publickey' => "BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6"],
                (object)['publickey' => "FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y"]
            ],
            [
                (object)['publickey' => "4Xpf8guEGD3ZnRJLuEu8JjpmKnHpXR49mFE4Zm9m9P1z"],
                (object)['publickey' => "96yeNG1KYJKAVnfKqfkfktkXuPj1CLPEsgCDkm42VcaT"]
            ],
            [
                (object)['publickey' => "Efv4wPdjfyVNvbp21xwiTXnirQti7jJy56W9doDVzfhG"],
                (object)['publickey' => "7TecQdLbPuxt3mWukbZ1g1dTZeA6rxgjMxfS9MRURaEP"]
            ],
            [
                (object)['secretkey' => "4q7HKMbwbLcG58iFV3pz4vkRnPTwbrY9Q5JrwnwLEZCC"],
                (object)[
                    'secretkey' => "4zsR9xoFpxfnNwLcY4hdRUarwf5xWtLj6FpKGDFBgscPxecPj2qgRNx4kJsFCpe9YDxBRNoeBWTh2SDAdwTySomS"
                ]
            ],
            [
                (object)['secretkey' => "ACsYcMff8UPUc5dvuCMAkqZxcRTjXHMnCc29TZkWLQsZ"],
                (object)[
                    'secretkey' => "5DteGKYVUUSSaruCK6H8tpd4oYWfcyNohyhJiYGYGBVzhuEmAmRRNcUJQzA2bk4DqqbtpaE51HTD1i3keTvtbCTL"
                ]
            ],
            [
                (object)['secretkey' => "BnjFJJarge15FiqcxrB7Mzt68nseBXXR4LQ54qFBsWHG"],
                (object)[
                    'secretkey' => "wJ4WH8dD88fSkNdFQRjaAhjFUZzZhV5yiDLDwNUnp6bYwRXrvWV8MJhQ9HL9uqMDG1n7XpTGZx7PafqaayQV8Rp"
                ]
            ]
        ];
    }
    
    /**
     * @dataProvider convertSignToEncryptProvider
     * 
     * @param object $expected
     * @param object $sign
     */
    public function testConvertSignToEncrypt($expected, $sign)
    {
        foreach ($sign as &$value) {
            $value = base58_decode($value);
        }
        
        $factory = new AccountFactory('L', 0);

        $encrypt = $factory->convertSignToEncrypt($sign);
        
        foreach ($encrypt as &$value) {
            $value = base58_encode($value);
        }
        
        $this->assertEquals($expected, $encrypt);
    }

    public function seedProvider()
    {
        return [
            [ "3JmCa4jLVv7Yn2XkCnBUGsa7WNFVEMxAfWe", 'L' ],
            [ "3MyuPwbiobZFnZzrtyY8pkaHoQHYmyQxxY1", 'T' ],
        ];
    }

    /**
     * @dataProvider seedProvider
     *
     * @param $expectedAddress
     * @param $network
     */
    public function testSeed($expectedAddress, $network)
    {
        $factory = new AccountFactory($network, 0);
        
        $account = $factory->seed($this->seedText);
        
        $this->assertInstanceOf(Account::class, $account);
        
        $this->assertBase58Equals(
            "4zsR9xoFpxfnNwLcY4hdRUarwf5xWtLj6FpKGDFBgscPxecPj2qgRNx4kJsFCpe9YDxBRNoeBWTh2SDAdwTySomS",
            $account->sign->secretkey
        );
        $this->assertBase58Equals("GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY", $account->sign->publickey);

        $this->assertBase58Equals("4q7HKMbwbLcG58iFV3pz4vkRnPTwbrY9Q5JrwnwLEZCC", $account->encrypt->secretkey);
        $this->assertBase58Equals("6fDod1xcVj4Zezwyy3tdPGHkuDyMq8bDHQouyp5BjXsX", $account->encrypt->publickey);

        $this->assertBase58Equals($expectedAddress, $account->address);
    }


    public function createSecretProvider()
    {
        $sign = [
            'secretkey' => '4zsR9xoFpxfnNwLcY4hdRUarwf5xWtLj6FpKGDFBgscPxecPj2qgRNx4kJsFCpe9YDxBRNoeBWTh2SDAdwTySomS',
            'publickey' => 'GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY'
        ];
        $signSecret = ['secretkey' => $sign['secretkey']];
        
        $encrypt = [
            'secretkey' => '4q7HKMbwbLcG58iFV3pz4vkRnPTwbrY9Q5JrwnwLEZCC',
            'publickey' => '6fDod1xcVj4Zezwyy3tdPGHkuDyMq8bDHQouyp5BjXsX'
        ];
        $encryptSecret = ['secretkey' => $encrypt['secretkey']];
        
        $address = '3JmCa4jLVv7Yn2XkCnBUGsa7WNFVEMxAfWe';
        
        return [
            [ compact('sign', 'encrypt', 'address'), true, true, true ],
            [ compact('sign', 'encrypt'), true, true, true ],
            [ compact('sign', 'address'), true, true, true ],
            [ compact('sign'), true, true, true ],
            [ compact('encrypt', 'address'), false, true, true ],
            [ compact('encrypt'), false, true, false ],
            [ compact('address'), false, false, true ],
            [ ['sign' => $signSecret, 'encrypt' => $encryptSecret, 'address' => $address], true, true, true ],
            [ ['sign' => $signSecret, 'encrypt' => $encryptSecret], true, true, true ],
            [ ['sign' => $signSecret], true, true, true ],
            [ $sign['secretkey'], true, true, true ],
            [ ['encrypt' => $encryptSecret], false, true, false ]
        ];
    }
    
    /**
     * @dataProvider createSecretProvider
     * 
     * @param array|string $data
     * @param bool         $hasSign
     * @param bool         $hasEncrypt
     * @param bool         $hasAddress
     */
    public function testCreate($data, $hasSign, $hasEncrypt, $hasAddress)
    {
        $factory = new AccountFactory('L', 0);
        $account = $factory->create($data);
        
        $this->assertInstanceOf(Account::class, $account);

        if ($hasSign) {
            $this->assertInternalType('object', $account->sign);
            $this->assertBase58Equals(
                "4zsR9xoFpxfnNwLcY4hdRUarwf5xWtLj6FpKGDFBgscPxecPj2qgRNx4kJsFCpe9YDxBRNoeBWTh2SDAdwTySomS",
                $account->sign->secretkey);
            $this->assertBase58Equals("GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY", $account->sign->publickey);
        } else {
            $this->assertNull($account->sign);
        }
        
        if ($hasEncrypt) {
            $this->assertInternalType('object', $account->encrypt);
            $this->assertBase58Equals("4q7HKMbwbLcG58iFV3pz4vkRnPTwbrY9Q5JrwnwLEZCC", $account->encrypt->secretkey);
            $this->assertBase58Equals("6fDod1xcVj4Zezwyy3tdPGHkuDyMq8bDHQouyp5BjXsX", $account->encrypt->publickey);
        } else {
            $this->assertNull($account->encrypt);
        }

        if ($hasAddress) {
            $this->assertBase58Equals("3JmCa4jLVv7Yn2XkCnBUGsa7WNFVEMxAfWe", $account->address);
        }
    }
    
    /**
     * @expectedException LTO\InvalidAccountException
     * @expectedExceptionMessage Public encrypt key doesn't match private encrypt key
     */
    public function testCreateEncryptKeyMismatch()
    {
        $factory = new AccountFactory('L', 0);
        $account = $factory->create([
            'encrypt' => [
                'publickey' => 'BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6',
                'secretkey' => 'ACsYcMff8UPUc5dvuCMAkqZxcRTjXHMnCc29TZkWLQsZ'
            ]
        ]);
        
        $this->assertInstanceOf(Account::class, $account);
    }
    
    /**
     * @expectedException LTO\InvalidAccountException
     * @expectedExceptionMessage Public sign key doesn't match private sign key
     */
    public function testCreateSignKeyMismatch()
    {
        $factory = new AccountFactory('L', 0);
        $account = $factory->create([
            'sign' => [
                'publickey' => 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y',
                'secretkey' =>
                    '5DteGKYVUUSSaruCK6H8tpd4oYWfcyNohyhJiYGYGBVzhuEmAmRRNcUJQzA2bk4DqqbtpaE51HTD1i3keTvtbCTL'
            ]
        ]);
        
        $this->assertInstanceOf(Account::class, $account);
    }
    
    /**
     * @expectedException LTO\InvalidAccountException
     * @expectedExceptionMessage Sign key doesn't match encrypt key
     */
    public function testCreateKeyMismatch()
    {
        $factory = new AccountFactory('L', 0);
        $account = $factory->create([
            'encrypt' => ['publickey' => 'EZa2ndj6h95m3xm7DxPQhrtANvhymNC7nWQ3o1vmDJ4x'],
            'sign' => ['publickey' => 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y']
        ]);
        
        $this->assertInstanceOf(Account::class, $account);
    }
    
    /**
     * @expectedException LTO\InvalidAccountException
     * @expectedExceptionMessage Address is of network 'T', not of 'L'
     */
    public function testCreateAddressMismatch()
    {
        $factory = new AccountFactory('L', 0);
        $account = $factory->create([
            'encrypt' => ['publickey' => '6fDod1xcVj4Zezwyy3tdPGHkuDyMq8bDHQouyp5BjXsX'],
            'sign' => ['publickey' => 'GjSacB6a5DFNEHjDSmn724QsrRStKYzkahPH67wyrhAY'],
            'address' => '3MyuPwbiobZFnZzrtyY8pkaHoQHYmyQxxY1'
        ]);
        
        $this->assertInstanceOf(Account::class, $account);
    }
    

    public function createPublicProvider()
    {
        return [
            [ 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y', 'BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6' ],
            [ 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y', null ],
            [ null, 'BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6' ],
            [ '2yYhlEGdosg7QZC//hibHiZ1MHk2m7jp/EbUeFdzDis=', null, 'base64' ],
            [ pack('H*', 'DB262194419DA2C83B4190BFFE189B1E26753079369BB8E9FC46D47857730E2B'), null, 'raw' ]
        ];
    }
    
    /**
     * @dataProvider createPublicProvider
     * 
     * @param string $signkey
     * @param string $encryptkey
     * @param string $encoding
     */
    public function testCreatePublic($signkey, $encryptkey, $encoding = 'base58')
    {
        $factory = new AccountFactory('L', 0);
        $account = $factory->createPublic($signkey, $encryptkey, $encoding);
        
        $this->assertInstanceOf(Account::class, $account);

        if (isset($signkey)) {
            $this->assertObjectNotHasAttribute('secretkey', $account->sign);
            $this->assertObjectHasAttribute('publickey', $account->sign);
            $this->assertBase58Equals("FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y", $account->sign->publickey);
        } else {
            $this->assertNull($account->sign);
        }
        
        $this->assertObjectNotHasAttribute('secretkey', $account->encrypt);
        $this->assertObjectHasAttribute('publickey', $account->encrypt);
        $this->assertBase58Equals("BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6", $account->encrypt->publickey);

        if (isset($signkey)) {
            $this->assertBase58Equals("3JoXfhxrA8Mvw7CvQowiNPTAzvgNYYXcn5q", $account->address);
        } else {
            $this->assertNull($account->address);
        }
    }
}
