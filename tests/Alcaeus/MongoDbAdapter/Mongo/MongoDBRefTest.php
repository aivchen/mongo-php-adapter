<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoDBRefTest extends TestCase
{
    public static function provideCreateThroughMongoDBCases(): iterable
    {
        $id = new \MongoId();
        $validRef = ['$ref' => 'test', '$id' => $id];

        $object = new \stdClass();
        $object->_id = $id;

        $objectWithoutId = new \stdClass();

        return [
            'simpleId' => [$validRef, $id],
            'arrayWithIdProperty' => [$validRef, ['_id' => $id]],
            'objectWithIdProperty' => [$validRef, $object],
            'arrayWithoutId' => [null, []],
            'objectWithoutId' => [['$ref' => 'test', '$id' => $objectWithoutId], $objectWithoutId],
        ];
    }

    public static function provideIsRefCases(): iterable
    {
        $objectRef = new \stdClass();
        $objectRef->{'$ref'} = 'coll';
        $objectRef->{'$id'} = 'id';

        return [
            'validRef' => [true, ['$ref' => 'coll', '$id' => 'id']],
            'validRefWithDatabase' => [true, ['$ref' => 'coll', '$id' => 'id', '$db' => 'db']],
            'refMissing' => [false, ['$id' => 'id']],
            'idMissing' => [false, ['$ref' => 'coll']],
            'objectRef' => [true, $objectRef],
            'int' => [false, 5],
        ];
    }

    public function testCreate(): void
    {
        $id = new \MongoId();
        $ref = \MongoDBRef::create('foo', $id);
        self::assertSame(['$ref' => 'foo', '$id' => $id], $ref);
    }

    public function testCreateWithDatabase(): void
    {
        $id = new \MongoId();
        $ref = \MongoDBRef::create('foo', $id, 'database');
        self::assertSame(['$ref' => 'foo', '$id' => $id, '$db' => 'database'], $ref);
    }

    /**
     * @dataProvider provideCreateThroughMongoDBCases
     */
    public function testCreateThroughMongoDB($expected, $document_or_id): void
    {
        $ref = $this->getDatabase()->createDBRef('test', $document_or_id);

        self::assertEquals($expected, $ref);
    }

    /**
     * @dataProvider provideIsRefCases
     */
    public function testIsRef($expected, $ref): void
    {
        self::assertSame($expected, \MongoDBRef::isRef($ref));
    }

    public function testGet(): void
    {
        $id = new \MongoId();

        $db = $this->getDatabase();

        $document = ['_id' => $id, 'foo' => 'bar'];
        $db->selectCollection('test')->insert($document);

        $fetchedRef = \MongoDBRef::get($db, ['$ref' => 'test', '$id' => $id]);
        self::assertIsArray($fetchedRef);
        self::assertEquals($document, $fetchedRef);
    }

    public function testGetThroughMongoDB(): void
    {
        $id = new \MongoId();

        $db = $this->getDatabase();

        $document = ['_id' => $id, 'foo' => 'bar'];
        $db->selectCollection('test')->insert($document);

        $fetchedRef = $db->getDBRef(['$ref' => 'test', '$id' => $id]);
        self::assertIsArray($fetchedRef);
        self::assertEquals($document, $fetchedRef);
    }

    public function testGetWithNonExistingDocument(): void
    {
        $db = $this->getDatabase();

        self::assertNull(\MongoDBRef::get($db, ['$ref' => 'test', '$id' => 'foo']));
    }

    public function testGetWithInvalidRef(): void
    {
        $db = $this->getDatabase();

        self::assertNull(\MongoDBRef::get($db, []));
    }

    public function testGetWithDifferentDatabase(): void
    {
        $database = $this->getDatabase();
        $collection = $this->getCollection();

        $document = ['foo' => 'bar'];

        $collection->insert($document);

        $ref = [
            '$ref' => $collection->getName(),
            '$id' => $document['_id'],
            '$db' => (string) $database,
        ];

        $referencedDocument = $this->getClient()->selectDB('foo')->getDBRef($ref);

        self::assertEquals($document, $referencedDocument);
    }
}
