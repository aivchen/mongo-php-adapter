<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use Alcaeus\MongoDbAdapter\TypeInterface;
use MongoDB\BSON\Timestamp;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoTimestampTest extends TestCase
{
    public function testCreate()
    {
        $timestamp = new \MongoTimestamp(1234567890, 987654321);
        self::assertSame(1234567890, $timestamp->sec);
        self::assertSame(987654321, $timestamp->inc);

        self::assertSame('1234567890', (string) $timestamp);

        return $timestamp;
    }

    /**
     * @depends testCreate
     */
    public function testConvertToBson(\MongoTimestamp $timestamp): void
    {
        $this->skipTestUnless($timestamp instanceof TypeInterface);

        $bsonTimestamp = $timestamp->toBSONType();
        self::assertInstanceOf('MongoDB\BSON\Timestamp', $bsonTimestamp);
        self::assertSame('[987654321:1234567890]', (string) $bsonTimestamp);
    }

    public function testCreateWithGlobalInc(): void
    {
        $timestamp1 = new \MongoTimestamp(1234567890);
        $timestamp2 = new \MongoTimestamp(1234567890);

        self::assertSame(0, $timestamp1->inc);
        self::assertSame(1, $timestamp2->inc);
    }

    public function testCreateWithBsonTimestamp(): void
    {
        $this->skipTestUnless(\in_array(TypeInterface::class, class_implements('MongoTimestamp'), true));

        $bsonTimestamp = new Timestamp(987654321, 1234567890);
        $timestamp = new \MongoTimestamp($bsonTimestamp);

        self::assertSame(1234567890, $timestamp->sec);
        self::assertSame(987654321, $timestamp->inc);
    }

    public function testContructorArgumentOrderDiffers(): void
    {
        $this->skipTestUnless(\in_array(TypeInterface::class, class_implements('MongoTimestamp'), true));

        /* The legacy MongoTimestamp's constructor takes seconds before the
         * increment, while MongoDB\BSON\Timestamp takes the increment first.
         */
        $bsonTimestamp = new Timestamp(12345, 67890);
        $timestamp = new \MongoTimestamp(67890, 12345);

        self::assertSame((string) $bsonTimestamp, (string) $timestamp->toBSONType());
    }
}
