<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use Alcaeus\MongoDbAdapter\TypeInterface;
use MongoDB\BSON\UTCDateTime;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoDateTest extends TestCase
{
    public function testTimeZoneDoesNotAlterReturnedDateTime(): void
    {
        $initialTZ = \ini_get('date.timezone');

        ini_set('date.timezone', 'UTC');

        // Today at 8h 8m 8s
        $timestamp = mktime(8, 8, 8);
        $date = new \MongoDate($timestamp);

        self::assertSame('08:08:08', $date->toDateTime()->format('H:i:s'));

        ini_set('date.timezone', 'Europe/Paris');

        self::assertSame('08:08:08', $date->toDateTime()->format('H:i:s'));

        ini_set('date.timezone', $initialTZ);
    }

    public function testCreate()
    {
        $date = new \MongoDate(1234567890, 123456);
        self::assertSame(1234567890, $date->sec);
        self::assertSame(123000, $date->usec);

        self::assertSame('0.12300000 1234567890', (string) $date);
        $dateTime = $date->toDateTime();

        self::assertSame(1234567890, $dateTime->getTimestamp());
        self::assertSame('123000', $dateTime->format('u'));

        return $date;
    }

    /**
     * @depends testCreate
     */
    public function testConvertToBson(\MongoDate $date): void
    {
        $this->skipTestUnless($date instanceof TypeInterface);

        $dateTime = $date->toDateTime();

        $bsonDate = $date->toBSONType();
        self::assertInstanceOf('MongoDB\BSON\UTCDateTime', $bsonDate);
        self::assertSame('1234567890123', (string) $bsonDate);

        $bsonDateTime = $bsonDate->toDateTime();

        // Compare timestamps to avoid issues with DateTime
        $timestamp = $dateTime->format('U') . '.' . $dateTime->format('U');
        $bsonTimestamp = $bsonDateTime->format('U') . '.' . $bsonDateTime->format('U');
        self::assertSame((float) $timestamp, (float) $bsonTimestamp);
    }

    public function testCreateWithString(): void
    {
        $date = new \MongoDate('1234567890', '123456');
        self::assertSame(1234567890, $date->sec);
        self::assertSame(123000, $date->usec);
    }

    public function testCreateWithBsonDate(): void
    {
        $this->skipTestUnless(\in_array(TypeInterface::class, class_implements('MongoDate'), true));

        $bsonDate = new UTCDateTime(1234567890123);
        $date = new \MongoDate($bsonDate);

        self::assertSame(1234567890, $date->sec);
        self::assertSame(123000, $date->usec);
    }

    public function testSupportMillisecondsWithLeadingZeroes(): void
    {
        $date = new \MongoDate('1234567890', '012345');
        self::assertSame(1234567890, $date->sec);
        self::assertSame(12000, $date->usec);

        self::assertSame('0.01200000 1234567890', (string) $date);
        $dateTime = $date->toDateTime();

        self::assertSame(1234567890, $dateTime->getTimestamp());
        self::assertSame('012000', $dateTime->format('u'));
    }

    public function testDSTTransitionDoesNotProduceWrongResults(): void
    {
        $initialTZ = \ini_get('date.timezone');

        ini_set('date.timezone', 'Europe/Madrid');

        $date = new \MongoDate(1603584000);
        $dateTime = $date->toDateTime();

        self::assertSame(1603584000, $dateTime->getTimestamp());

        ini_set('date.timezone', $initialTZ);
    }

    public function testDSTTransitionDoesNotProduceWrongResultsWithMicroSeconds(): void
    {
        $initialTZ = \ini_get('date.timezone');

        ini_set('date.timezone', 'Europe/Madrid');

        $date = new \MongoDate(1603584000, 123456);
        $dateTime = $date->toDateTime();

        self::assertSame(1603584000, $dateTime->getTimestamp());
        self::assertSame('123000', $dateTime->format('u'));

        ini_set('date.timezone', $initialTZ);
    }
}
