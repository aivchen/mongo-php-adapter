<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;

class MongoLogTest extends TestCase
{
    public function testSetCallback(): void
    {
        $foo = static function (): void {};
        self::assertTrue(\MongoLog::setCallback($foo));
        self::assertSame($foo, \MongoLog::getCallback());
    }

    public function testLevel(): void
    {
        \MongoLog::setLevel(2);
        self::assertSame(2, \MongoLog::getLevel());
    }

    public function testModule(): void
    {
        \MongoLog::setModule(2);
        self::assertSame(2, \MongoLog::getModule());
    }
}
