<?php

declare(strict_types=1);

namespace LTO\Transaction;

use LTO\Transaction;
use function LTO\decode;
use function LTO\encode;
use function LTO\is_valid_address;

/**
 * LTO transaction to revoke an association.
 */
class RevokeAssociation extends AbstractAssociation
{
    /** Transaction type */
    public const TYPE = 17;

    /**
     * Prepare signing the transaction.
     */
    public function toBinary(): string
    {
        switch ($this->version) {
            case 1:
                $pack = new Pack\AssociationV1();
                break;
            case 3:
                $pack = new Pack\RevokeAssociationV3();
                break;
            default:
                throw new \UnexpectedValueException("Unsupported revoke association tx version $this->version");
        }

        return $pack($this);
    }
}
