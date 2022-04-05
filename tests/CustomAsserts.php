<?php

declare(strict_types=1);

namespace LTO\Tests;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\GreaterThan;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\Constraint\JsonMatches;

trait CustomAsserts
{
    public static function assertEqualsAsJson($expected, $actual, string $message = ''): void
    {
        $expectedJson = json_encode($expected);
        $actualJson = json_encode($actual);

        Assert::assertThat($actualJson, new JsonMatches($expectedJson), $message);
    }

    public static function assertEqualsBase58(string $base58, string $binary, string $message = ''): void
    {
        Assert::assertThat(base58_encode($binary), new IsEqual($base58), $message);
    }

    public static function assertTimestampIsNow(int $timestamp, string $message = ''): void
    {
        Assert::assertThat($timestamp, new IsType('int'), $message);
        Assert::assertThat($timestamp, new GreaterThan((time() - 5) * 1000), $message);
    }
}
