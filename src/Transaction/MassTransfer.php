<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Account;
use LTO\Binary;
use LTO\Transaction;
use function LTO\is_valid_address;

/**
 * LTO Mass transfer transaction.
 */
class MassTransfer extends Transaction
{
    /** Base transaction fee */
    public const BASE_FEE = 100000000;

    /** Transaction fee per transfer */
    public const ITEM_FEE = 10000000;

    /** Transaction type */
    public const TYPE = 11;

    /** Transaction version */
    public const DEFAULT_VERSION = 3;

    /**
     * @var array<array{recipient:string,amount:int}>
     */
    public array $transfers = [];

    public Binary $attachment;


    /**
     * Class constructor.
     *
     * @param array<array{recipient:string|Account,amount:int}> $transfers
     * @param string|Binary                             $attachment
     */
    public function __construct(array $transfers = [], $attachment = '')
    {
        $this->version = self::DEFAULT_VERSION;
        $this->transfers = $this->sanitizeTransfers($transfers);
        $this->fee = self::BASE_FEE + count($this->transfers) * self::ITEM_FEE;

        $this->attachment = $attachment instanceof Binary ? $attachment : new Binary($attachment);
    }

    /**
     * @param array<array{recipient:string|Account,amount:int}> $transfers
     * @return array<array{recipient:string,amount:int}>
     */
    protected function sanitizeTransfers(array $transfers): array
    {
        $result = [];

        foreach ($transfers as $tr) {
            if (!isset($tr['recipient']) || !isset($tr['amount'])) {
                throw new \InvalidArgumentException("Transfer should specify recipient and amount");
            }

            $result[] = [
                'recipient' => $tr['recipient'] instanceof Account ? $tr['recipient']->getAddress() : $tr['recipient'],
                'amount' => $tr['amount']
            ];
        }

        return $result;
    }

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 1:
                $pack = new Pack\MassTransferV1();
                break;
            case 3:
                $pack = new Pack\MassTransferV3();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported mass transfer tx version {$this->version}");
        }

        return $pack($this);

    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return ['type' => static::TYPE] + parent::jsonSerialize();
    }

    /**
     * @inheritDoc
     */
    public static function fromData(array $data)
    {
        static::assertNoMissingKeys($data);
        static::assertType($data, static::TYPE);

        $data['attachment'] = Binary::fromBase58($data['attachment'] ?? '');

        return static::createFromData($data);
    }

    /**
     * Add a transfer
     *
     * @param string|Account $recipient   Recipient address (base58 encoded)
     * @param int            $amount      Amount of LTO (*10^8)
     * @return $this
     */
    public function addTransfer($recipient, int $amount): self
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Invalid amount; should be greater than 0");
        }

        if ($recipient instanceof Account) {
            $recipient = $recipient->getAddress();
        } elseif (!is_valid_address($recipient)) {
            throw new \InvalidArgumentException("Invalid recipient address; is it base58 encoded?");
        }

        $this->transfers[] = [
            'amount' => $amount,
            'recipient' => $recipient,
        ];

        $this->fee += self::ITEM_FEE;

        return $this;
    }
}
