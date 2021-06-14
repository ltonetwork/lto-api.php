<?php

declare(strict_types=1);

namespace LTO\Transaction;

/**
 * Transaction to create a smart account by setting a script.
 *
 * Note: the script needs to be compiled before it's used.
 * This can be done by the public node via endpoint `/utils/script/compile`.
 */
class SetScript extends \LTO\Transaction
{
    /** Minimum transaction fee */
    public const MINIMUM_FEE = 500000000;

    /** Transaction type */
    public const TYPE = 13;

    /** Transaction version */
    public const DEFAULT_VERSION = 1;

    /**
     * Base64 encoded script
     * @var string
     */
    public $script;

    /**
     * Class constructor.
     *
     * @param string|null $compiledScript  Base64 encoded compiled script or NULL to remove the script
     */
    public function __construct(?string $compiledScript)
    {
        $this->version = self::DEFAULT_VERSION;
        $this->fee = self::MINIMUM_FEE;

        $this->script = $compiledScript !== null
            ? preg_replace('/^(base64:)?/', 'base64:', $compiledScript)
            : null;
    }

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 1:
                $pack = new Pack\SetScriptV1();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported set script tx version {$this->version}");
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
        static::assertNoMissingKeys($data, ['id', 'height', 'script']);
        static::assertType($data, static::TYPE);

        return static::createFromData($data);
    }
}
