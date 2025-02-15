<?php

namespace Alcaeus\MongoDbAdapter\Tests;

use Alcaeus\MongoDbAdapter\ExceptionConverter;
use MongoDB\Driver\Exception;
use MongoDB\Exception\BadMethodCallException;
use MongoDB\Exception\InvalidArgumentException;
use MongoDB\Exception\UnexpectedValueException;
use PHPUnit\Framework\TestCase;

class ExceptionConverterTest extends TestCase
{
    /**
     * @dataProvider provideConvertExceptionCases
     */
    public function testConvertException($e, $expectedClass): void
    {
        $exception = ExceptionConverter::toLegacy($e);
        self::assertInstanceOf($expectedClass, $exception);
        self::assertSame($e->getMessage(), $exception->getMessage());
        self::assertSame($e->getCode(), $exception->getCode());
        self::assertSame($e, $exception->getPrevious());
    }

    public function provideConvertExceptionCases(): iterable
    {
        return [
            // Driver
            [
                new Exception\AuthenticationException('message', 1),
                'MongoConnectionException',
            ],
            [
                new Exception\BulkWriteException('message', 2),
                'MongoCursorException',
            ],
            [
                new Exception\ConnectionException('message', 2),
                'MongoConnectionException',
            ],
            [
                new Exception\ConnectionTimeoutException('message', 2),
                'MongoConnectionException',
            ],
            [
                new Exception\ExecutionTimeoutException('message', 2),
                'MongoExecutionTimeoutException',
            ],
            [
                new Exception\InvalidArgumentException('message', 2),
                'MongoException',
            ],
            [
                new Exception\LogicException('message', 2),
                'MongoException',
            ],
            [
                new Exception\RuntimeException('message', 2),
                'MongoException',
            ],
            [
                new Exception\SSLConnectionException('message', 2),
                'MongoConnectionException',
            ],
            [
                new Exception\UnexpectedValueException('message', 2),
                'MongoException',
            ],

            // Library
            [
                new BadMethodCallException('message', 2),
                'MongoException',
            ],
            [
                new InvalidArgumentException('message', 2),
                'MongoException',
            ],
            [
                new UnexpectedValueException('message', 2),
                'MongoException',
            ],
        ];
    }
}
