<?php

namespace LTO;

use LTO\Account;
use LTO\Keccak;

/**
 * Create new account (aka wallet)
 */
class AccountFactory
{
    const ADDRESS_VERSION = 0x1;
    
    /**
     * Address scheme
     * @var string 
     */
    protected $network;
    
    /**
     * Incrementing nonce (4 bytes)
     * @var string 
     */
    protected $nonce;
    
    /**
     * Class constructor
     * 
     * @param int|string $network 'W' or 'T' (1 byte)
     * @param int        $nonce   (4 bytes)
     */
    public function __construct($network, $nonce = null)
    {
        $this->network = is_int($network) ? chr($network) : substr($network, 0, 1);
        $this->nonce = isset($nonce) ? $nonce : 0;
    }
    
    /**
     * Get the new nonce.
     * 
     * @return int
     */
    protected function getNonce()
    {
        return $this->nonce;
    }
    
    /**
     * Create the account seed using several hashing algorithms.
     * 
     * @param string $seedText  Brainwallet seed string
     * @return string  raw seed (not encoded)
     */
    public function createAccountSeed($seedText)
    {
        $seedBase = pack('La*', $this->getNonce(), $seedText);
        
        $secureSeed = Keccak::hash(\sodium\crypto_generichash($seedBase, null, 32), 256, true);
        $seed = hash('sha256', $secureSeed, true);
        
        return $seed;
    }
    
    /**
     * Create ED25519 sign keypairs
     * 
     * @param string $seed
     * @return object
     */
    protected function createSignKeys($seed)
    {
        $keypair = \sodium\crypto_sign_seed_keypair($seed);
        $publickey = \sodium\crypto_sign_publickey($keypair);
        $secretkey = \sodium\crypto_sign_secretkey($keypair);

        return (object)compact('publickey', 'secretkey');
    }
    
    /**
     * Create X25519 encrypt keypairs
     * 
     * @param string $seed
     * @return object
     */
    protected function createEncryptKeys($seed)
    {
        $keypair = \sodium\crypto_box_seed_keypair($seed);
        $publickey = \sodium\crypto_box_publickey($keypair);
        $secretkey = \sodium\crypto_box_secretkey($keypair);
        
        return (object)compact('publickey', 'secretkey');
    }

    /**
     * Create an address from a public key
     * 
     * @param string $publickey  Raw public key (not encoded)
     * @param string $type       Type of key 'sign' or 'encrypt'
     * @return string  raw (not encoded)
     */
    public function createAddress($publickey, $type = 'encrypt')
    {
        if ($type === 'sign') {
            $publickey = \sodium\crypto_sign_ed25519_pk_to_curve25519($publickey);
        }
        
        $publickeyHash = substr(Keccak::hash(\sodium\crypto_generichash($publickey, null, 32), 256), 0, 40);
        
        $packed = pack('CaH40', self::ADDRESS_VERSION, $this->network, $publickeyHash);
        $chksum = substr(Keccak::hash(\sodium\crypto_generichash($packed), 256), 0, 8);
        
        return pack('CaH40H8', self::ADDRESS_VERSION, $this->network, $publickeyHash, $chksum);
    }
    
    /**
     * Create a new account from a seed
     * 
     * @param string $seedText  Brainwallet seed string
     * @return Account
     */
    public function seed($seedText)
    {
        $seed = $this->createAccountSeed($seedText);
        
        $account = new Account();
        
        $account->sign = $this->createSignKeys($seed);
        $account->encrypt = $this->createEncryptKeys($seed);
        $account->address = $this->createAddress($account->sign->publickey, 'sign');
        
        return $account;
    }
    
    
    /**
     * Convert sign keys to encrypt keys.
     * 
     * @param object|string $sign
     * @return object
     */
    public function convertSignToEncrypt($sign)
    {
        $encrypt = (object)[];
        
        if (isset($sign->secretkey)) {
            $secretkey = \sodium\crypto_sign_ed25519_sk_to_curve25519($sign->secretkey);

            // Swap bits, on uneven???
            $bytes = unpack('C*', $secretkey);
            $i = count($bytes); // 1 based array
            $bytes[$i] = $bytes[$i] % 2 ? ($bytes[$i] | 0x80) & ~0x40 : $bytes[$i];
            
            $encrypt->secretkey = pack('C*', ...$bytes);
        }
        
        if (isset($sign->publickey)) {
            $encrypt->publickey = \sodium\crypto_sign_ed25519_pk_to_curve25519($sign->publickey);
        }
        
        return $encrypt;
    }
    
    /**
     * Get and verify the raw public and private key.
     * 
     * @param array  $keys
     * @param string $type  'sign' or 'encrypt'
     * @return object
     * @throws InvalidAccountException  if keys don't match
     */
    protected function calcKeys($keys, $type)
    {
        if (!isset($keys['secretkey'])) {
            return (object)['publickey' => $keys['publickey']];
        }
        
        $secretkey = $keys['secretkey'];
        
        $publickey = $type === 'sign' ?
            \sodium\crypto_sign_publickey_from_secretkey($secretkey) :
            \sodium\crypto_box_publickey_from_secretkey($secretkey);
        
        if (isset($keys['publickey']) && $keys['publickey'] !== $publickey) {
            throw new InvalidAccountException("Public {$type} key doesn't match private {$type} key");
        }
        
        return (object)compact('secretkey', 'publickey');
    }
    
    /**
     * Get and verify raw address.
     * 
     * @param string $address  Raw address
     * @param object $sign     Sign keys
     * @param object $encrypt  Encrypt keys
     * @return string
     * @throws InvalidAccountException  if address doesn't match
     */
    protected function calcAddress($address, $sign, $encrypt)
    {
        $addrSign = isset($sign->publickey) ? $this->createAddress($sign->publickey, 'sign') : null;
        $addrEncrypt = isset($encrypt->publickey) ? $this->createAddress($encrypt->publickey, 'encrypt') : null;
        
        if (isset($addrSign) && isset($addrEncrypt) && $addrSign !== $addrEncrypt) {
            throw new InvalidAccountException("Sign key doesn't match encrypt key");
        }
        
        if (isset($address)) {
            if ((isset($addrSign) && $address !== $addrSign) || (isset($addrEncrypt) && $address !== $addrEncrypt)) {
                throw new InvalidAccountException("Address doesn't match keypair; possible network mismatch");
            }
        } else {
            $address = $addrSign ?: $addrEncrypt;
        }
        
        return $address;
    }
     
    /**
     * Create an account from base58 encoded keys.
     * 
     * @param array|string $keys  All keys (array) or private sign key (string)
     * @return Account
     */
    public function create($keys, $encoding = 'base58')
    {
        $data = self::decode($keys, $encoding);
        
        if (is_string($data)) {
            $data = ['sign' => ['secretkey' => $data]];
        }
        
        $account = new Account();
        
        $account->sign = isset($data['sign']) ? $this->calcKeys($data['sign'], 'sign') : null;
        
        $account->encrypt = isset($data['encrypt']) ?
            $this->calcKeys($data['encrypt'], 'encrypt') :
            (isset($account->sign) ? $this->convertSignToEncrypt($account->sign) : null);
        
        $address = isset($data['address']) ? $data['address'] : null;
        $account->address = $this->calcAddress($address, $account->sign, $account->encrypt);
        
        return $account;
    }
    
    /**
     * Create an account from public keys.
     * 
     * @param string $sign
     * @param string $encrypt
     * @param string $encoding  Encoding of keys 'raw', 'base58' or 'base64'
     * @return Account
     */
    public function createPublic($sign = null, $encrypt = null, $encoding = 'base58')
    {
        $data = [];
        
        if (isset($sign)) {
            $data['sign'] = ['publickey' => $sign];
        }
        
        if (isset($encrypt)) {
            $data['encrypt'] = ['publickey' => $encrypt];
        }
        
        return $this->create($data, $encoding);
    }
    
    
    /**
     * Base58 or base64 decode, recursively
     * 
     * @param string|array $data
     * @param string       $encoding  'raw', 'base58' or 'base64'
     * @return string|array
     */
    protected static function decode($data, $encoding = 'base58')
    {
        if ($encoding === 'raw') {
            return $data;
        }
        
        if (is_array($data)) {
            return array_map(function ($item) use ($encoding) { return self::decode($item, $encoding); }, $data);
        }
        
        if ($encoding === 'base58') {
            $base58 = new \StephenHill\Base58();
            $data = $base58->decode($data);
        }
        
        if ($encoding === 'base64') {
            $data = base64_decode($data);
        }

        return $data;
    }
}
