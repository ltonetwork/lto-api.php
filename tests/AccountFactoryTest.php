<?php

namespace LTO;

use PHPUnit_Framework_TestCase as TestCase;
use LTO\Account;
use LTO\AccountFactory;

/**
 * @covers LTO\AccountFactory
 */
class AccountFactoryTest extends TestCase
{
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
        $base58 = new \StephenHill\Base58();
        $value = $base58->encode($actual);
        
        $constraint = new \PHPUnit_Framework_Constraint_IsEqual($encoded);

        static::assertThat($value, $constraint, $message);
    }
    
    
    /**
     * @see https://specs.livecontracts.io/cryptography.html#asymmetric-encryption
     */
    public function testCreateAccountSeed()
    {
        $base58 = new \StephenHill\Base58();
        
        $factory = new AccountFactory('W', 0);
        $seed = $factory->createAccountSeed($this->seedText);
        
        $this->assertEquals("49mgaSSVQw6tDoZrHSr9rFySgHHXwgQbCRwFssboVLWX", $base58->encode($seed));
    }

    
    public function createAddressProvider()
    {
        return [
            [ "3PPbMwqLtwBGcJrTA5whqJfY95GqnNnFMDX", 'W' ],
            [ "3PPbMwqLtwBGcJrTA5whqJfY95GqnNnFMDX", 0x57 ],
            [ "3NBaYzWT2odsyrZ2u1ghsrHinBm4xFRAgLX", 'T' ],
            [ "3NBaYzWT2odsyrZ2u1ghsrHinBm4xFRAgLX", 0x54 ],
        ];
    }
    
    /**
     * @dataProvider createAddressProvider
     * 
     * @param string     $expected
     * @param string|int $network
     */
    public function testCreateAddressEncrypt($expected, $network)
    {
        $base58 = new \StephenHill\Base58();

        $factory = new AccountFactory($network, 0);
        
        $publickey = $base58->decode("HBqhfdFASRQ5eBBpu2y6c6KKi1az6bMx8v1JxX4iW1Q8");
        $address = $factory->createAddress($publickey, "encrypt");
        
        $this->assertEquals($expected, $base58->encode($address));
    }
    
    /**
     * @dataProvider createAddressProvider
     * 
     * @param string     $expected
     * @param string|int $network
     */
    public function testCreateAddressSign($expected, $network)
    {
        $base58 = new \StephenHill\Base58();

        $factory = new AccountFactory($network, 0);
        
        $publickey = $base58->decode("BvEdG3ATxtmkbCVj9k2yvh3s6ooktBoSmyp8xwDqCQHp");
        $address = $factory->createAddress($publickey, "sign");
        
        $this->assertEquals($expected, $base58->encode($address));
    }

    public function convertSignToEncryptProvider()
    {
        return [
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
                (object)['secretkey' => "ACsYcMff8UPUc5dvuCMAkqZxcRTjXHMnCc29TZkWLQsZ"],
                (object)['secretkey' =>
                    "5DteGKYVUUSSaruCK6H8tpd4oYWfcyNohyhJiYGYGBVzhuEmAmRRNcUJQzA2bk4DqqbtpaE51HTD1i3keTvtbCTL"]
            ],
            [
                (object)['secretkey' => "BnjFJJarge15FiqcxrB7Mzt68nseBXXR4LQ54qFBsWJN"],
                (object)['secretkey' =>
                    "wJ4WH8dD88fSkNdFQRjaAhjFUZzZhV5yiDLDwNUnp6bYwRXrvWV8MJhQ9HL9uqMDG1n7XpTGZx7PafqaayQV8Rp"]
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
        $base58 = new \StephenHill\Base58();
        
        foreach ($sign as &$value) {
            $value = $base58->decode($value);
        }
        
        $factory = new AccountFactory('W', 0);

        $encrypt = $factory->convertSignToEncrypt($sign);
        
        foreach ($encrypt as &$value) {
            $value = $base58->encode($value);
        }
        
        $this->assertEquals($expected, $encrypt);
    }
    
    
    public function testSeed()
    {
        $factory = new AccountFactory('W', 0);
        
        $account = $factory->seed($this->seedText);
        
        $this->assertInstanceOf(Account::class, $account);
        
        $this->assertBase58Equals("FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y", $account->sign->publickey);
        $this->assertBase58Equals(
            "wJ4WH8dD88fSkNdFQRjaAhjFUZzZhV5yiDLDwNUnp6bYwRXrvWV8MJhQ9HL9uqMDG1n7XpTGZx7PafqaayQV8Rp",
            $account->sign->secretkey);
        
        $this->assertBase58Equals("BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6", $account->encrypt->publickey);
        $this->assertBase58Equals("BnjFJJarge15FiqcxrB7Mzt68nseBXXR4LQ54qFBsWJN", $account->encrypt->secretkey);
        
        $this->assertBase58Equals("3PLSsSDUn3kZdGe8qWEDak9y8oAjLVecXV1", $account->address);
    }

    
    public function createSecretProvider()
    {
        $sign = [
            'secretkey' => 'wJ4WH8dD88fSkNdFQRjaAhjFUZzZhV5yiDLDwNUnp6bYwRXrvWV8MJhQ9HL9uqMDG1n7XpTGZx7PafqaayQV8Rp',
            'publickey' => 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y'
        ];
        $signSecret = ['secretkey' => $sign['secretkey']];
        
        $encrypt = [
            'secretkey' => 'BnjFJJarge15FiqcxrB7Mzt68nseBXXR4LQ54qFBsWJN',
            'publickey' => 'BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6'
        ];
        $encryptSecret = ['secretkey' => $encrypt['secretkey']];
        
        $address = '3PLSsSDUn3kZdGe8qWEDak9y8oAjLVecXV1';
        
        return [
            [ compact('sign', 'encrypt', 'address'), true, true ],
            [ compact('sign', 'encrypt'), true, true ],
            [ compact('sign', 'address'), true, true ],
            [ compact('sign'), true, true ],
            [ compact('encrypt', 'address'), false, true ],
            [ compact('encrypt'), false, true ],
            [ compact('address'), false, false ],
            [ ['sign' => $signSecret, 'encrypt' => $encryptSecret, 'address' => $address], true, true ],
            [ ['sign' => $signSecret, 'encrypt' => $encryptSecret], true, true ],
            [ ['sign' => $signSecret], true, true ],
            [ $sign['secretkey'], true, true ],
            [ ['encrypt' => $encryptSecret], false, true ]
        ];
    }
    
    /**
     * @dataProvider createSecretProvider
     * 
     * @param array|string $data
     * @param boolean      $hasSign
     * @param boolean      $hasEncrypt
     */
    public function testCreate($data, $hasSign, $hasEncrypt)
    {
        $factory = new AccountFactory('W', 0);
        $account = $factory->create($data);
        
        $this->assertInstanceOf(Account::class, $account);

        if ($hasSign) {
            $this->assertInternalType('object', $account->sign);
            $this->assertBase58Equals(
                "wJ4WH8dD88fSkNdFQRjaAhjFUZzZhV5yiDLDwNUnp6bYwRXrvWV8MJhQ9HL9uqMDG1n7XpTGZx7PafqaayQV8Rp",
                $account->sign->secretkey);
            $this->assertBase58Equals("FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y", $account->sign->publickey);
        } else {
            $this->assertNull($account->sign);
        }
        
        if ($hasEncrypt) {
            $this->assertInternalType('object', $account->encrypt);
            $this->assertBase58Equals("BnjFJJarge15FiqcxrB7Mzt68nseBXXR4LQ54qFBsWJN", $account->encrypt->secretkey);
            $this->assertBase58Equals("BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6", $account->encrypt->publickey);
        } else {
            $this->assertNull($account->encrypt);
        }
        
        $this->assertBase58Equals("3PLSsSDUn3kZdGe8qWEDak9y8oAjLVecXV1", $account->address);
    }
    
    /**
     * @expectedException LTO\InvalidAccountException
     * @expectedExceptionMessage Public encrypt key doesn't match private encrypt key
     */
    public function testCreateEncryptKeyMismatch()
    {
        $factory = new AccountFactory('W', 0);
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
        $factory = new AccountFactory('W', 0);
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
        $factory = new AccountFactory('W', 0);
        $account = $factory->create([
            'encrypt' => ['publickey' => 'EZa2ndj6h95m3xm7DxPQhrtANvhymNC7nWQ3o1vmDJ4x'],
            'sign' => ['publickey' => 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y']
        ]);
        
        $this->assertInstanceOf(Account::class, $account);
    }
    
    /**
     * @expectedException LTO\InvalidAccountException
     * @expectedExceptionMessage Address doesn't match keypair; possible network mismatch
     */
    public function testCreateAddressMismatch()
    {
        $factory = new AccountFactory('W', 0);
        $account = $factory->create([
            'encrypt' => ['publickey' => 'EZa2ndj6h95m3xm7DxPQhrtANvhymNC7nWQ3o1vmDJ4x'],
            'sign' => ['publickey' => 'gVVExGUK4J5BsxwUfYsFkkjpn6A7BcvYdmARL28GBRc'],
            'address' => '3PLSsSDUn3kZdGe8qWEDak9y8oAjLVecXV1'
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
     * @param string $encode
     */
    public function testCreatePublic($signkey, $encryptkey, $encoding = 'base58')
    {
        $factory = new AccountFactory('W', 0);
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
        
        $this->assertBase58Equals("3PLSsSDUn3kZdGe8qWEDak9y8oAjLVecXV1", $account->address);
    }
}
