LegalThings One client for PHP
===

[![Build Status](https://travis-ci.org/legalthings/lto-api.php.svg?branch=master)](https://travis-ci.org/legalthings/lto-api.php)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/legalthings/lto-api.php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/legalthings/lto-api.php/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/legalthings/lto-api.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/legalthings/lto-api.php/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/legalthings/lto-api.svg)](https://packagist.org/packages/legalthings/lto-api)
[![Packagist License](https://img.shields.io/packagist/l/legalthings/lto-api.svg)](https://packagist.org/packages/legalthings/lto-api)


Installation
---

    composer require legalthings/lto-api

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

$recipientPublicKey = "HBqhfdFASRQ5eBBpu2y6c6KKi1az6bMx8v1JxX4iW1Q8"; // base58 encoded X25519 public key
$recipient = $factory->createPublic(null, $recipientPublicKey);

$cyphertext = $account->encryptFor($recipient, $message); // Raw binary, not encoded
```

You can use `$account->encryptFor($account, $message);` to encrypt a message for yourself.

### Decrypt a message received from another account

```php
$senderPublicKey = "HBqhfdFASRQ5eBBpu2y6c6KKi1az6bMx8v1JxX4iW1Q8"; // base58 encoded X25519 public key
$sender = $factory->createPublic(null, $senderPublicKey);

$message = $account->decryptFrom($sender, $cyphertext);
```

You can use `$account->decryptFrom($account, $message);` to decrypt a message from yourself.

### Create a new event chain

```php
$chain = $account->createEventChain(); // Creates an empty event chain with a valid id and last hash
```

_Note: You need to add an identity as first event on the chain. This is **not** done automatically._

### Create and sign an event and add it to an existing event chain

```php
$body = [
  "$schema": "http://specs.livecontracts.io/01-draft/12-comment/schema.json#",
  "identity": {
    "$schema": "http://specs.livecontracts.io/01-draft/02-identity/schema.json#",
    "id": "1bb5a451-d496-42b9-97c3-e57404d2984f"
  },
  "content_media_type": "text/plain",
  "content": "Hello world!"
];

$chainId = "JEKNVnkbo3jqSHT8tfiAKK4tQTFK7jbx8t18wEEnygya";
$chainLastHash" = "3yMApqCuCjXDWPrbjfR5mjCPTHqFG8Pux1TxQrEM35jj";

$chain = new EventChain($chainId, $chainLastHash);

$chain->add(new Event($body))->signWith($account);
```

You need the chain id and the hash of the last event to use an existing chain.
