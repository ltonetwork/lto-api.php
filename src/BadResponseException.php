<?php

declare(strict_types=1);

namespace LTO;

/**
 * Exception throw on a HTTP response with a status code of 4xx or 5xx.
 */
class BadResponseException extends \RuntimeException
{
    /**
     * @param string          $message
     * @param \Throwable|null $cause
     */
    public function __construct(string $message, ?\Throwable $cause = null)
    {
        parent::__construct($message, 0, $cause);
    }
}
