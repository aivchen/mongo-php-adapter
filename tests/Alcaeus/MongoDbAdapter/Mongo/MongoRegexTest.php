<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use Alcaeus\MongoDbAdapter\TypeInterface;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoRegexTest extends TestCase
{
    public function testCreate()
    {
        $regex = new \MongoRegex('/abc/i');
        self::assertSame('abc', $regex->regex);
        self::assertSame('i', $regex->flags);

        self::assertSame('/abc/i', (string) $regex);

        return $regex;
    }

    /**
     * @depends testCreate
     */
    public function testConvertToBson(\MongoRegex $regex): void
    {
        $this->skipTestUnless($regex instanceof TypeInterface);

        $bsonRegex = $regex->toBSONType();
        self::assertInstanceOf('MongoDB\BSON\Regex', $bsonRegex);
        self::assertSame('abc', $bsonRegex->getPattern());
        self::assertSame('i', $bsonRegex->getFlags());
    }

    public function testCreateWithBsonType(): void
    {
        $this->skipTestUnless(\in_array(TypeInterface::class, class_implements('MongoRegex'), true));

        $bsonRegex = new \MongoDB\BSON\Regex('abc', 'i');
        $regex = new \MongoRegex($bsonRegex);

        self::assertSame('abc', $regex->regex);
        self::assertSame('i', $regex->flags);
    }
}
