<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use Alcaeus\MongoDbAdapter\TypeInterface;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoCodeTest extends TestCase
{
    public function testCreate()
    {
        $code = new \MongoCode('code', ['scope' => 'bleh']);

        self::assertSame('code', $this->getAttributeValue($code, 'code'));
        self::assertSame(['scope' => 'bleh'], $this->getAttributeValue($code, 'scope'));

        self::assertSame('code', (string) $code);

        return $code;
    }

    public function testCreateWithoutScope()
    {
        $code = new \MongoCode('code');

        self::assertSame('code', $this->getAttributeValue($code, 'code'));
        self::assertSame([], $this->getAttributeValue($code, 'scope'));

        self::assertSame('code', (string) $code);

        return $code;
    }

    public function testConvertToBson(): void
    {
        $code = new \MongoCode('code', ['scope' => 'bleh']);

        $this->skipTestUnless($code instanceof TypeInterface);

        $bsonCode = $code->toBSONType();
        self::assertInstanceOf('MongoDB\BSON\Javascript', $bsonCode);
        self::assertSame('code', $bsonCode->getCode());
        self::assertEquals((object) ['scope' => 'bleh'], $bsonCode->getScope());
    }

    public function testConvertToBsonWithoutScope(): void
    {
        $code = new \MongoCode('code');

        $this->skipTestUnless($code instanceof TypeInterface);

        $bsonCode = $code->toBSONType();
        self::assertInstanceOf('MongoDB\BSON\Javascript', $bsonCode);
        self::assertSame('code', $bsonCode->getCode());
        self::assertNull($bsonCode->getScope());
    }

    public function testCreateWithBsonObject(): void
    {
        $this->skipTestUnless(\in_array(TypeInterface::class, class_implements('MongoCode'), true));

        $bsonCode = new \MongoDB\BSON\Javascript('code', ['scope' => 'bleh']);
        $code = new \MongoCode($bsonCode);

        self::assertSame('code', $this->getAttributeValue($code, 'code'));
        self::assertSame(['scope' => 'bleh'], $this->getAttributeValue($code, 'scope'));
    }

    private function getAttributeValue(\MongoCode $code, $attribute)
    {
        $property = new \ReflectionProperty($code, $attribute);
        $property->setAccessible(true);

        return $property->getValue($code);
    }
}
