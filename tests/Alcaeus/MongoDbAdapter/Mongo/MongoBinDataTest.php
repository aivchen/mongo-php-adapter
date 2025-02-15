<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use Alcaeus\MongoDbAdapter\TypeInterface;
use MongoDB\BSON\Binary;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoBinDataTest extends TestCase
{
    public const GUID = '0123456789abcdef';

    public function testCreate()
    {
        $bin = new \MongoBinData(self::GUID, \MongoBinData::FUNC);
        self::assertSame(self::GUID, $bin->bin);
        self::assertSame(\MongoBinData::FUNC, $bin->type);

        self::assertSame('<Mongo Binary Data>', (string) $bin);

        return $bin;
    }

    /**
     * @depends testCreate
     */
    public function testConvertToBson(\MongoBinData $bin): void
    {
        $this->skipTestUnless($bin instanceof TypeInterface);

        $bsonBinary = $bin->toBSONType();
        self::assertInstanceOf('MongoDB\BSON\Binary', $bsonBinary);

        self::assertSame(self::GUID, $bsonBinary->getData());
        self::assertSame(Binary::TYPE_FUNCTION, $bsonBinary->getType());
    }

    public function testCreateWithBsonBinary(): void
    {
        $this->skipTestUnless(\in_array(TypeInterface::class, class_implements('MongoBinData'), true));

        $bsonBinary = new Binary(self::GUID, Binary::TYPE_UUID);
        $bin = new \MongoBinData($bsonBinary);

        self::assertSame(self::GUID, $bin->bin);
        self::assertSame(\MongoBinData::UUID_RFC4122, $bin->type);
    }
}
