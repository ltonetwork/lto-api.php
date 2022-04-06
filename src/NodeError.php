<?php

declare(strict_types=1);

namespace LTO;

class NodeError extends \RuntimeException
{
    public function __construct(string $json, ?\Throwable $previous = null)
    {
        try {
            $info = json_decode($json, true, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $info = null;
        }

        if (isset($info['error']) && isset($info['message'])) {
            $err = $info['error'];
            $message = $info['message'] . (isset($info['cause']) ? "\n" . $info['cause'] : '');
        } else {
            $err = 0;
            $message = $json;
        }

        parent::__construct($message, $err, $previous);
    }
}
