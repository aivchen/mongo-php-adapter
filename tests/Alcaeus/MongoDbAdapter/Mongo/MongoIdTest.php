<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use MongoDB\BSON\ObjectID;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoIdTest extends TestCase
{
    public static function provideIsValidCases(): iterable
    {
        $original = '54203e08d51d4a1f868b456e';

        return [
            'validId' => [true, '' . $original . ''],
            'MongoId' => [true, new \MongoId($original)],
            'ObjectID' => [true, new ObjectID($original)],
            'invalidString' => [false, 'abc'],
            'object' => [false, new \stdClass()],
        ];
    }

    public function testCreateWithoutParameter(): void
    {
        $id = new \MongoId();
        $stringId = (string) $id;

        self::assertSame(24, \strlen($stringId));
        self::assertSame($stringId, $id->{'$id'});

        $serialized = serialize($id);
        self::assertSame(sprintf('O:7:"MongoId":1:{i:0;s:24:"%s";}', $stringId), $serialized);

        $unserialized = unserialize($serialized);
        self::assertInstanceOf('MongoId', $unserialized);
        self::assertSame($stringId, (string) $unserialized);

        $json = json_encode($id);
        self::assertSame(sprintf('{"$id":"%s"}', $stringId), $json);
    }

    public function testCreateWithString(): void
    {
        $original = '54203e08d51d4a1f868b456e';
        $id = new \MongoId($original);
        self::assertSame($original, (string) $id);

        self::assertSame(9127278, $id->getInc());
        self::assertSame(1411399176, $id->getTimestamp());
        self::assertSame(34335, $id->getPID());
    }

    public function testCreateWithInvalidStringThrowsMongoException(): void
    {
        $this->expectException('\MongoException');
        $this->expectExceptionMessage('Invalid object ID');

        new \MongoId('invalid');
    }

    public function testCreateWithObjectId(): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $original = '54203e08d51d4a1f868b456e';
        $objectId = new ObjectID($original);

        $id = new \MongoId($objectId);
        self::assertSame($original, (string) $id);

        self::assertNotSame($objectId, $this->getAttributeValue($id, 'objectID'));
    }

    /**
     * @dataProvider provideIsValidCases
     */
    public function testIsValid($expected, $value): void
    {
        $this->skipTestIf($value instanceof ObjectID && \extension_loaded('mongo'));
        self::assertSame($expected, \MongoId::isValid($value));
    }

    private function getAttributeValue(\MongoId $id, $attribute)
    {
        $property = new \ReflectionProperty($id, $attribute);
        $property->setAccessible(true);

        return $property->getValue($id);
    }
}
