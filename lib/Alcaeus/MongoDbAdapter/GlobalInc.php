<?php

declare(strict_types=1);

namespace Alcaeus\MongoDbAdapter;

/**
 * @internal
 */
final class GlobalInc
{
    private static int $globalInc = 0;

    public static function next(): int
    {
        return self::$globalInc++;
    }

    public static function reset(): void
    {
        self::$globalInc = 0;
    }
}
