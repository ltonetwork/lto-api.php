LegalThings One client for PHP
===

[![Build Status](https://travis-ci.org/legalthings/lto-client.php.svg?branch=master)](https://travis-ci.org/legalthings/lto-client.php)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/legalthings/lto-client.php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/legalthings/lto-client.php/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/legalthings/lto-client.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/legalthings/lto-client.php/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/legalthings/lto-client.php.svg)](https://packagist.org/packages/legalthings/lto-client.php)
[![Packagist License](https://img.shields.io/packagist/l/legalthings/lto-client.php.svg)](https://packagist.org/packages/legalthings/lto-client.php)


Installation
---

    composer require legalthings/lto-client

Usage
---

### Create an account

```php
$accountInfo = [
  'address' => '3PLSsSDUn3kZdGe8qWEDak9y8oAjLVecXV1',
  'sign' => [
    'secretkey' => 'wJ4WH8dD88fSkNdFQRjaAhjFUZzZhV5yiDLDwNUnp6bYwRXrvWV8MJhQ9HL9uqMDG1n7XpTGZx7PafqaayQV8Rp',
    'publickey' => 'FkU1XyfrCftc4pQKXCrrDyRLSnifX1SMvmx1CYiiyB3Y'
  ],
  'encrypt' => [
    'secretkey' => 'BnjFJJarge15FiqcxrB7Mzt68nseBXXR4LQ54qFBsWJN',
    'publickey' => 'BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6'
  ]
];

$factory = new LTO\AccountFactory('T'); // 'T' for testnet and 'W' for mainnet
$account = $factory->create($accountInfo);
```

### Create an account with only a secret sign key

```php
$secretKey = 'wJ4WH8dD88fSkNdFQRjaAhjFUZzZhV5yiDLDwNUnp6bYwRXrvWV8MJhQ9HL9uqMDG1n7XpTGZx7PafqaayQV8Rp';

$factory = new LTO\AccountFactory('T');
$account = $factory->create($secretKey);
```

### Create an account from seed

_Currently the seeded keyset doesn't match when seeded using the Waves API. Seeded results may change to match Waves._

```php
$seedText = "manage manual recall harvest series desert melt police rose hollow moral pledge kitten position add";

$factory = new LTO\AccountFactory('T');
$account = $factory->seed($seedText);
```

### Sign a message

```php
$signature = $account->sign('hello world'); // Base58 encoded signature
```

### Encrypt a message for another account

```php
$message = 'hello world';
$recipientPublicKey = "HBqhfdFASRQ5eBBpu2y6c6KKi1az6bMx8v1JxX4iW1Q8";

$recipient = $factory->create(['encrypt' => [ 'publickey' => $recipientPublicKey ]]);

$cyphertext = $account->encryptFor($recipient, $message); // Raw binary, not encoded
```

You can use `$account->encryptFor($account, $message);` to encrypt a message for yourself.

### Decrypt a message received from another account

```php
$senderPublicKey = "HBqhfdFASRQ5eBBpu2y6c6KKi1az6bMx8v1JxX4iW1Q8";

$sender = $factory->create(['encrypt' => [ 'publickey' => $senderPublicKey ]]);

$message = $account->decryptFrom($sender, $cyphertext);
```

You can use `$account->decryptFrom($account, $message);` to decrypt a message from yourself.
