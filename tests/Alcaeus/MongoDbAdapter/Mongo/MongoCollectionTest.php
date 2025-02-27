<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use MongoDB\BSON\Regex;
use MongoDB\Driver\ReadPreference;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoCollectionTest extends TestCase
{
    public function testSerialize(): void
    {
        self::assertIsString(serialize($this->getCollection()));
    }

    public function testGetNestedCollections(): void
    {
        $collection = $this->getCollection()->foo->bar;
        self::assertSame('mongo-php-adapter.test.foo.bar', (string) $collection);
    }

    public function testCreateRecord(): void
    {
        $collection = $this->getCollection();

        $expected = [
            'ok' => 1.0,
            'n' => 0,
            'err' => null,
            'errmsg' => null,
        ];
        $document = ['foo' => 'bar'];
        self::assertEquals($expected, $collection->insert($document));

        self::assertInstanceOf('MongoId', $document['_id']);
        $id = (string) $document['_id'];

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
        $object = $newCollection->findOne();

        self::assertNotNull($object);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $object->_id);
        self::assertSame($id, (string) $object->_id);
        self::assertNotNull($object->foo);
        self::assertSame('bar', $object->foo);
    }

    public function testInsertInvalidData(): void
    {
        // Dirty hack to support both PHPUnit 5.x and 6.x
        $this->expectWarning();
        $this->expectWarningMessage('MongoCollection::insert(): expects parameter 1 to be an array or object, integer given');

        $document = 8;
        $this->getCollection()->insert($document);
    }

    public function testInsertEmptyArray(): void
    {
        $document = [];
        $this->getCollection()->insert($document);

        self::assertSame(1, $this->getCollection()->count());
    }

    public function testInsertArrayWithNumericKeys(): void
    {
        $document = [1 => 'foo'];
        $this->getCollection()->insert($document);

        self::assertSame(1, $this->getCollection()->count(['_id' => $document['_id']]));
    }

    /**
     * @dataProvider provideInsertArrayWithEmptyIdsCases
     */
    public function testInsertArrayWithEmptyIds($id): void
    {
        $document = ['_id' => $id];
        $this->getCollection()->insert($document);

        self::assertSame(1, $this->getCollection()->count(['_id' => $id]));
    }

    public function provideInsertArrayWithEmptyIdsCases(): iterable
    {
        return [
            'Zero as string' => ['0'],
            'Zero as int' => [0],
            'Empty string' => [''],
        ];
    }

    public function testInsertArrayWithEmptyId(): void
    {
        $document = ['_id' => ''];
        $this->getCollection()->insert($document);

        self::assertSame(1, $this->getCollection()->count(['_id' => $document['_id']]));
    }

    public function testInsertEmptyObject(): void
    {
        $document = (object) [];
        $this->getCollection()->insert($document);

        self::assertSame(1, $this->getCollection()->count());
    }

    public function testInsertObjectWithPrivateProperties(): void
    {
        $this->expectException(\MongoException::class);
        $this->expectExceptionMessage('zero-length keys are not allowed, did you use $ with double quotes?');

        $document = new PrivatePropertiesStub();
        $this->getCollection()->insert($document);
    }

    public function testInsertArrayObjectWithProtectedProperties(): void
    {
        $document = new ArrayObjectWithProtectedProperties(['foo' => 'bar']);
        $this->getCollection()->insert($document);

        self::assertInstanceOf('MongoId', $document['_id']);
        self::assertEquals(['_id' => $document['_id'], 'foo' => 'bar'], $this->getCollection()->findOne(['_id' => $document['_id']]));
    }

    public function testInsertWithInvalidKey(): void
    {
        $document = ['*' => 'foo'];
        $this->getCollection()->insert($document);

        self::assertSame(1, $this->getCollection()->count(['*' => 'foo']));
    }

    /**
     * @dataProvider getDocumentsWithEmptyKey
     */
    public function testInsertWithEmptyKey($document): void
    {
        $this->expectException(\MongoException::class);
        $this->expectExceptionMessage('zero-length keys are not allowed, did you use $ with double quotes?');

        $this->getCollection()->insert($document);
    }

    public function testInsertWithNumericKey(): void
    {
        $document = ['foo'];
        $this->getCollection()->insert($document);

        self::assertSame(1, $this->getCollection()->count(['foo']));
    }

    public function testInsertWithAlphaNumericKey(): void
    {
        /**
         * Force the array to store the key as a string "0".
         * Initialising like ['0' => 'foo'] casts the string to an int.
         */
        $document = new \stdClass();
        $document->{'0'} = 'foo';
        $document = (array) $document;

        $this->getCollection()->insert($document);
        self::assertSame(1, $this->getCollection()->count(['0' => 'foo']));
    }

    public function testInsertDuplicate(): void
    {
        $collection = $this->getCollection();

        $collection->createIndex(['foo' => 1], ['unique' => true]);

        $document = ['foo' => 'bar'];
        $collection->insert($document);

        unset($document['_id']);

        $this->expectException(\MongoDuplicateKeyException::class);
        $this->expectErrorMessageMatches('/E11000 duplicate key error .* mongo-php-adapter\.test/');
        $this->expectExceptionCode(11000);
        $collection->insert($document);
    }

    public function testUnacknowledgedWrite(): void
    {
        $document = ['foo' => 'bar'];
        self::assertTrue($this->getCollection()->insert($document, ['w' => 0]));
    }

    public function testUnacknowledgedWriteWithBooleanValue(): void
    {
        $document = ['foo' => 'bar'];
        self::assertTrue($this->getCollection()->insert($document, ['w' => false]));
    }

    public function testAcknowledgedWriteConcernWithBool(): void
    {
        $document = ['foo' => 'bar'];
        self::assertEquals(
            [
                'ok' => 1.0,
                'n' => 0,
                'err' => null,
                'errmsg' => null,
            ],
            $this->getCollection()->insert($document, ['w' => true]),
        );
    }

    public function testInsertWriteConcernException(): void
    {
        $this->expectException(\MongoWriteConcernException::class);
        $this->expectExceptionMessage("cannot use 'w' > 1 when a host is not replicated");

        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document, ['w' => 2]);
    }

    public function testInsertMany(): void
    {
        $expected = [
            'ok' => 1.0,
            'n' => 0,
            'syncMillis' => 0,
            'writtenTo' => null,
            'err' => null,
        ];

        $documents = [
            ['foo' => 'bar'],
            ['bar' => 'foo'],
        ];
        $this->assertMatches($expected, $this->getCollection()->batchInsert($documents));

        foreach ($documents as $document) {
            self::assertInstanceOf('MongoId', $document['_id']);
        }
    }

    public function testInsertManyWithNonNumericKeys(): void
    {
        $expected = [
            'ok' => 1.0,
            'n' => 0,
            'syncMillis' => 0,
            'writtenTo' => null,
            'err' => null,
        ];

        $documents = [
            'a' => ['foo' => 'bar'],
            'b' => ['bar' => 'foo'],
        ];
        $this->assertMatches($expected, $this->getCollection()->batchInsert($documents));

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(2, $newCollection->count());
    }

    public function testBatchInsertContinuesOnError(): void
    {
        $expected = [
            'ok' => 1.0,
            'n' => 0,
            'syncMillis' => 0,
            'writtenTo' => null,
            'err' => null,
        ];

        $documents = [
            8,
            'b' => ['bar' => 'foo'],
        ];
        $this->assertMatches($expected, $this->getCollection()->batchInsert($documents, ['continueOnError' => true]));

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
    }

    public function testBatchInsertException(): void
    {
        $id = new \MongoId();
        $documents = [['_id' => $id, 'foo' => 'bar'], ['_id' => $id, 'foo' => 'bleh']];

        $this->expectException(\MongoDuplicateKeyException::class);
        $this->expectErrorMessageMatches('/E11000 duplicate key error .* mongo-php-adapter.test.*_id_/');
        $this->expectExceptionCode(11000);

        $this->getCollection()->batchInsert($documents);
    }

    public function testBatchInsertObjectWithPrivateProperties(): void
    {
        $this->expectException(\MongoException::class);
        $this->expectExceptionMessage('zero-length keys are not allowed, did you use $ with double quotes?');

        $documents = [new PrivatePropertiesStub()];
        $this->getCollection()->batchInsert($documents);
    }

    public function testBatchInsertWithInvalidKey(): void
    {
        $documents = [['*' => 'foo']];
        $this->getCollection()->batchInsert($documents);

        self::assertSame(1, $this->getCollection()->count(['*' => 'foo']));
    }

    /**
     * @dataProvider getDocumentsWithEmptyKey
     */
    public function testBatchInsertWithEmptyKey($document): void
    {
        $this->expectException(\MongoException::class);
        $this->expectExceptionMessage('zero-length keys are not allowed, did you use $ with double quotes?');

        $documents = [$document];
        $this->getCollection()->batchInsert($documents);
    }

    public function testBatchInsertWithNumericKey(): void
    {
        $documents = [['foo']];
        $this->getCollection()->batchInsert($documents);

        self::assertSame(1, $this->getCollection()->count(['foo']));
    }

    public function testBatchInsertEmptyBatchException(): void
    {
        $this->expectException(\MongoException::class);
        $this->expectExceptionMessage('No write ops were included in the batch');

        $documents = [];
        $this->getCollection()->batchInsert($documents, ['w' => 2]);
    }

    public function testUpdateWriteConcern(): void
    {
        $this->expectException(\MongoWriteConcernException::class);
        $this->expectExceptionMessage("cannot use 'w' > 1 when a host is not replicated");

        $this->getCollection()->update([], ['$set' => ['foo' => 'bar']], ['w' => 2]);
    }

    public function testUpdateOne(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);

        // Unset ID to re-insert
        unset($document['_id']);
        $this->getCollection()->insert($document);

        $expected = [
            'ok' => 1.0,
            'nModified' => 1,
            'n' => 1,
            'err' => null,
            'errmsg' => null,
            'updatedExisting' => true,
        ];

        $result = $this->getCollection()->update(['foo' => 'bar'], ['$set' => ['foo' => 'foo']]);
        self::assertEquals($expected, $result);

        self::assertSame(1, $this->getCheckDatabase()->selectCollection('test')->count(['foo' => 'foo']));
    }

    public function testUpdateReplaceOne(): void
    {
        $document = ['foo' => 'bar', 'bar' => 'foo'];
        $this->getCollection()->insert($document);

        // Unset ID to re-insert
        unset($document['_id']);
        $this->getCollection()->insert($document);

        $expected = [
            'ok' => 1.0,
            'nModified' => 1,
            'n' => 1,
            'err' => null,
            'errmsg' => null,
            'updatedExisting' => true,
        ];

        $result = $this->getCollection()->update(['foo' => 'bar'], ['foo' => 'foo']);
        self::assertEquals($expected, $result);

        self::assertSame(1, $this->getCheckDatabase()->selectCollection('test')->count(['foo' => 'foo']));
        self::assertSame(1, $this->getCheckDatabase()->selectCollection('test')->count(['bar' => 'foo']));
    }

    public function testUpdateReplaceMultiple(): void
    {
        $this->expectException(\MongoWriteConcernException::class);
        $this->expectErrorMessageMatches('/multi update only works with \$ operators/');
        $this->getCollection()->update(['foo' => 'bar'], ['foo' => 'foo'], ['multiple' => true]);
    }

    public function testUpdateDuplicate(): void
    {
        $collection = $this->getCollection();
        $collection->createIndex(['foo' => 1], ['unique' => 1]);

        $document = ['foo' => 'bar'];
        $collection->insert($document);
        $document = ['foo' => 'foo'];
        $collection->insert($document);

        $this->expectException(\MongoDuplicateKeyException::class);
        $collection->update(['foo' => 'bar'], ['$set' => ['foo' => 'foo']]);
    }

    public function testUpdateMany(): void
    {
        $document = ['change' => true, 'foo' => 'bar'];
        $this->getCollection()->insert($document);

        unset($document['_id']);
        $this->getCollection()->insert($document);

        $document = ['change' => true, 'foo' => 'foo'];
        $this->getCollection()->insert($document);
        $expected = [
            'ok' => 1.0,
            'nModified' => 2,
            'n' => 3,
            'err' => null,
            'errmsg' => null,
            'updatedExisting' => true,
        ];

        $result = $this->getCollection()->update(['change' => true], ['$set' => ['foo' => 'foo']], ['multiple' => true]);
        self::assertEquals($expected, $result);

        self::assertSame(3, $this->getCheckDatabase()->selectCollection('test')->count(['foo' => 'foo']));
    }

    public function testUpdateWhichDoesntMatchQuery(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);

        $expected = [
            'ok' => 1.0,
            'nModified' => 0,
            'n' => 0,
            'err' => null,
            'errmsg' => null,
            'updatedExisting' => false,
        ];

        $result = $this->getCollection()->update(['foo' => 'bar22'], ['$set' => ['foo' => 'foo']]);
        self::assertEquals($expected, $result);

        self::assertSame(1, $this->getCheckDatabase()->selectCollection('test')->count(['foo' => 'bar']));
    }

    public function testUnacknowledgedUpdate(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);
        unset($document['_id']);
        $this->getCollection()->insert($document);

        self::assertTrue($this->getCollection()->update($document, ['$set' => ['foo' => 'foo']], ['w' => 0]));
    }

    public function testUpdateWithInvalidKey(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);

        $update_document = ['*' => 'foo'];
        $this->getCollection()->update($document, $update_document);

        self::assertSame(1, $this->getCollection()->count(['*' => 'foo']));
    }

    /**
     * @dataProvider getDocumentsWithEmptyKey
     */
    public function testUpdateWithEmptyKey($updateDocument): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);

        $this->expectException(\MongoException::class);
        $this->expectExceptionMessage('zero-length keys are not allowed, did you use $ with double quotes?');

        $this->getCollection()->update($document, $updateDocument);
    }

    /**
     * @dataProvider getDocumentsWithEmptyKey
     */
    public function testAtomicUpdateWithEmptyKey($updateDocument): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);

        $this->expectException(\MongoException::class);
        $this->expectExceptionMessage('zero-length keys are not allowed, did you use $ with double quotes?');

        $this->getCollection()->update($document, ['$set' => $updateDocument]);
    }

    public function getDocumentsWithEmptyKey(): iterable
    {
        return [
            'array' => [['' => 'foo']],
            'object' => [(object) ['' => 'foo']],
        ];
    }

    public function testRemoveMultiple(): void
    {
        $document = ['change' => true, 'foo' => 'bar'];
        $this->getCollection()->insert($document);

        unset($document['_id']);
        $this->getCollection()->insert($document);

        $document = ['change' => true, 'foo' => 'foo'];
        $this->getCollection()->insert($document);
        $expected = [
            'ok' => 1.0,
            'n' => 2,
            'err' => null,
            'errmsg' => null,
        ];

        $result = $this->getCollection()->remove(['foo' => 'bar']);
        self::assertEquals($expected, $result);

        self::assertSame(1, $this->getCheckDatabase()->selectCollection('test')->count());
    }

    public function testRemoveSingle(): void
    {
        $document = ['change' => true, 'foo' => 'bar'];
        $this->getCollection()->insert($document);
        unset($document['_id']);
        $this->getCollection()->insert($document);
        unset($document['_id']);
        $this->getCollection()->insert($document);
        $expected = [
            'ok' => 1.0,
            'n' => 1,
            'err' => null,
            'errmsg' => null,
        ];

        $result = $this->getCollection()->remove(['foo' => 'bar'], ['justOne' => true]);
        self::assertEquals($expected, $result);

        self::assertSame(2, $this->getCheckDatabase()->selectCollection('test')->count());
    }

    public function testRemoveUnacknowledged(): void
    {
        $document = ['change' => true, 'foo' => 'bar'];
        $this->getCollection()->insert($document);
        unset($document['_id']);
        $this->getCollection()->insert($document);
        unset($document['_id']);
        $this->getCollection()->insert($document);

        self::assertTrue($this->getCollection()->remove(['foo' => 'bar'], ['w' => 0]));
    }

    public function testFindReturnsCursor(): void
    {
        $this->prepareData();
        $collection = $this->getCollection();

        self::assertInstanceOf('MongoCursor', $collection->find());
    }

    /**
     * @dataProvider provideFindWithProjectionCases
     */
    public function testFindWithProjection($projection): void
    {
        $document = ['foo' => 'foo', 'bar' => 'bar'];
        $this->getCollection()->insert($document);
        unset($document['_id']);
        $this->getCollection()->insert($document);

        $cursor = $this->getCollection()->find(['foo' => 'foo'], $projection);
        foreach ($cursor as $document) {
            self::assertCount(2, $document);
            self::assertArrayHasKey('_id', $document);
            $this->assertMatches(['bar' => 'bar'], $document);
        }
    }

    public static function provideFindWithProjectionCases(): iterable
    {
        return [
            'projection' => [['bar' => true]],
            'intProjection' => [['bar' => 1]],
            'legacyProjection' => [['bar']],
        ];
    }

    /**
     * @dataProvider provideFindWithProjectionAndNumericKeysCases
     */
    public function testFindWithProjectionAndNumericKeys($data, $projection, $expected): void
    {
        $this->getCollection()->insert($data);

        $document = $this->getCollection()->findOne([], $projection);
        unset($document['_id']);
        self::assertSame($expected, $document);
    }

    public static function provideFindWithProjectionAndNumericKeysCases(): iterable
    {
        return [
            'sequentialIntegersStartingWithOne' => [
                ['0' => 'foo', '1' => 'bar', '2' => 'foobar'],
                [1 => true, 2 => true],
                ['1' => 'bar', '2' => 'foobar'],
            ],
            'nonSequentialIntegers' => [
                ['0' => 'foo', '1' => 'bar', '2' => 'foobar', '3' => 'barfoo'],
                [1 => true, 3 => true],
                ['1' => 'bar', '3' => 'barfoo'],
            ],
        ];
    }

    public function testFindWithProjectionAndSequentialNumericKeys(): void
    {
        $this->expectException(\MongoException::class);
        $this->expectExceptionMessage('field names must be strings');
        $this->getCollection()->findOne([], [true, false]);
    }

    /**
     * @dataProvider provideFindWithProjectionExcludeIdCases
     */
    public function testFindWithProjectionExcludeId($projection): void
    {
        $document = ['foo' => 'foo', 'bar' => 'bar'];
        $this->getCollection()->insert($document);
        unset($document['_id']);
        $this->getCollection()->insert($document);

        $cursor = $this->getCollection()->find(['foo' => 'foo'], $projection);
        foreach ($cursor as $document) {
            self::assertCount(1, $document);
            self::assertArrayNotHasKey('_id', $document);
            $this->assertMatches(['bar' => 'bar'], $document);
        }
    }

    public static function provideFindWithProjectionExcludeIdCases(): iterable
    {
        return [
            'projection' => [['_id' => false, 'bar' => true]],
            'intProjection' => [['_id' => 0, 'bar' => 1]],
        ];
    }

    public function testCount(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();

        self::assertSame(3, $collection->count());
        self::assertSame(2, $collection->count(['foo' => 'bar']));
    }

    public function testCountWithLimit(): void
    {
        $this->prepareData();
        $collection = $this->getCollection();

        self::assertSame(2, $collection->count([], ['limit' => 2]));
        self::assertSame(1, $collection->count(['foo' => 'bar'], ['limit' => 1]));
    }

    public function testCountWithLimitLegacy(): void
    {
        $this->prepareData();
        $collection = $this->getCollection();

        self::assertSame(2, $collection->count([], 2));
        self::assertSame(1, $collection->count(['foo' => 'bar'], 1));
    }

    public function testCountWithSkip(): void
    {
        $this->prepareData();
        $collection = $this->getCollection();

        self::assertSame(2, $collection->count([], ['skip' => 1]));
        self::assertSame(1, $collection->count(['foo' => 'bar'], ['skip' => 1]));
    }

    public function testCountWithSkipLegacy(): void
    {
        $this->prepareData();
        $collection = $this->getCollection();

        self::assertSame(2, $collection->count([], null, 1));
        self::assertSame(1, $collection->count(['foo' => 'bar'], null, 1));
    }

    public function testCountWithLimitAndSkip(): void
    {
        $this->prepareData();
        $collection = $this->getCollection();

        self::assertSame(2, $collection->count([], ['skip' => 1, 'limit' => 2]));
        self::assertSame(1, $collection->count([], ['skip' => 1, 'limit' => 1]));
    }

    public function testCountWithLimitAndSkipLegacy(): void
    {
        $this->prepareData();
        $collection = $this->getCollection();

        self::assertSame(2, $collection->count([], 2, 1));
        self::assertSame(1, $collection->count([], 1, 1));
    }

    public function testCountTimeout(): void
    {
        $this->failMaxTimeMS();

        $this->expectException(\MongoExecutionTimeoutException::class);

        $this->getCollection()->count([], ['maxTimeMS' => 1]);
    }

    public function testFindOne(): void
    {
        $this->prepareData();

        $document = $this->getCollection()->findOne(['foo' => 'foo'], ['_id' => false]);
        self::assertSame(['foo' => 'foo'], $document);
    }

    public function testFindOneWithProjection(): void
    {
        $document = ['foo' => 'foo', 'bar' => 'bar'];
        $this->getCollection()->insert($document);

        $document = $this->getCollection()->findOne(['foo' => 'foo'], ['bar' => true]);
        self::assertCount(2, $document);
        $this->assertMatches(['bar' => 'bar'], $document);
    }

    public function testFindOneWithLegacyProjection(): void
    {
        $document = ['foo' => 'foo', 'bar' => 'bar'];
        $this->getCollection()->insert($document);

        $document = $this->getCollection()->findOne(['foo' => 'foo'], ['bar']);
        self::assertCount(2, $document);
        $this->assertMatches(['bar' => 'bar'], $document);
    }

    public function testFindOneNotFound(): void
    {
        $document = $this->getCollection()->findOne(['foo' => 'foo'], ['_id' => false]);
        self::assertNull($document);
    }

    public function testFindOneConnectionIssue(): void
    {
        $this->expectException(\MongoConnectionException::class);

        $client = $this->getClient([], 'mongodb://localhost:28888/?connectTimeoutMS=1');
        $collection = $client->selectCollection('mongo-php-adapter', 'test');

        $collection->findOne();
    }

    public function testDistinct(): void
    {
        $this->prepareData();

        $values = $this->getCollection()->distinct('foo');
        self::assertIsArray($values);

        sort($values);
        self::assertEquals(['bar', 'foo'], $values);
    }

    public function testDistinctWithQuery(): void
    {
        $this->prepareData();

        $values = $this->getCollection()->distinct('foo', ['foo' => 'bar']);
        self::assertIsArray($values);
        self::assertEquals(['bar'], $values);
    }

    public function testDistinctWithIdQuery(): void
    {
        $document1 = ['foo' => 'bar'];
        $document2 = ['foo' => 'bar'];
        $document3 = ['foo' => 'foo'];

        $collection = $this->getCollection();
        $collection->insert($document1);
        $collection->insert($document2);
        $collection->insert($document3);

        self::assertSame(
            ['bar'],
            $collection->distinct('foo', ['_id' => [
                '$in' => [$document1['_id'], $document2['_id']],
            ]]),
        );

        self::assertEquals(
            ['bar', 'foo'],
            $collection->distinct('foo', ['_id' => [
                '$in' => [$document1['_id'], $document3['_id']],
            ]]),
        );
    }

    public function testAggregate(): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $collection = $this->getCollection();

        $this->prepareData();

        $pipeline = [
            [
                '$group' => [
                    '_id' => '$foo',
                    'count' => ['$sum' => 1],
                ],
            ],
            [
                '$sort' => ['_id' => 1],
            ],
        ];

        $result = $collection->aggregate($pipeline, ['cursor' => true]);
        self::assertIsArray($result);
        self::assertArrayHasKey('result', $result);

        self::assertEquals([
            ['_id' => 'bar', 'count' => 2],
            ['_id' => 'foo', 'count' => 1],
        ], $result['result']);
    }

    public function testAggregateWithMultiplePilelineOperatorsAsArguments(): void
    {
        $this->skipTestIf(version_compare($this->getServerVersion(), '3.6.0', '>='), 'Test does not apply to MongoDB >= 3.6.');

        $collection = $this->getCollection();

        $this->prepareData();

        try {
            $result = $collection->aggregate(
                [
                    '$group' => [
                        '_id' => '$foo',
                        'count' => ['$sum' => 1],
                    ],
                ],
                [
                    '$sort' => ['_id' => 1],
                ],
            );
        } catch (\MongoResultException $ex) {
            $msg = 'MongoCollection::aggregate ( array $op [, array $op [, array $... ]] ) should accept variable amount of pipeline operators as argument'
                . "\n"
                . $ex;
            self::fail($msg);
        }

        self::assertIsArray($result);
        self::assertArrayHasKey('result', $result);

        self::assertEquals([
            ['_id' => 'bar', 'count' => 2],
            ['_id' => 'foo', 'count' => 1],
        ], $result['result']);
    }

    public function testAggregateInvalidPipeline(): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $collection = $this->getCollection();

        $pipeline = [
            [
                '$invalid' => [],
            ],
        ];

        $this->expectException(\MongoResultException::class);
        $this->expectExceptionMessage('Unrecognized pipeline stage name');
        $collection->aggregate($pipeline, ['cursor' => true]);
    }

    public function testAggregateTimeoutException(): void
    {
        $collection = $this->getCollection();

        $this->failMaxTimeMS();

        $this->expectException(\MongoExecutionTimeoutException::class);

        $pipeline = [
            [
                '$group' => [
                    '_id' => '$foo',
                    'count' => ['$sum' => 1],
                ],
            ],
            [
                '$sort' => ['_id' => 1],
            ],
        ];

        $collection->aggregate($pipeline, ['maxTimeMS' => 1, 'cursor' => true]);
    }

    public function testAggregateCursor(): void
    {
        $collection = $this->getCollection();

        $this->prepareData();

        $pipeline = [
            [
                '$group' => [
                    '_id' => '$foo',
                    'count' => ['$sum' => 1],
                ],
            ],
            [
                '$sort' => ['_id' => 1],
            ],
        ];

        $cursor = $collection->aggregateCursor($pipeline);
        self::assertInstanceOf('MongoCommandCursor', $cursor);

        self::assertEquals([
            ['_id' => 'bar', 'count' => 2],
            ['_id' => 'foo', 'count' => 1],
        ], iterator_to_array($cursor));
    }

    public function testReadPreference(): void
    {
        $collection = $this->getCollection();
        self::assertSame(['type' => \MongoClient::RP_PRIMARY], $collection->getReadPreference());
        self::assertFalse($collection->getSlaveOkay());

        self::assertTrue($collection->setReadPreference(\MongoClient::RP_SECONDARY, [['a' => 'b']]));
        self::assertSame(['type' => \MongoClient::RP_SECONDARY, 'tagsets' => [['a' => 'b']]], $collection->getReadPreference());
        self::assertTrue($collection->getSlaveOkay());

        self::assertTrue($collection->setSlaveOkay(true));
        self::assertSame(['type' => \MongoClient::RP_SECONDARY_PREFERRED, 'tagsets' => [['a' => 'b']]], $collection->getReadPreference());

        self::assertTrue($collection->setSlaveOkay(false));
        $this->assertMatches(['type' => \MongoClient::RP_PRIMARY], $collection->getReadPreference());
    }

    public function testReadPreferenceIsSetInDriver(): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $collection = $this->getCollection();

        self::assertTrue($collection->setReadPreference(\MongoClient::RP_SECONDARY, [['a' => 'b']]));

        // Only way to check whether options are passed down is through debugInfo
        $readPreference = $collection->getCollection()->__debugInfo()['readPreference'];

        self::assertSame(ReadPreference::SECONDARY, $readPreference->getModeString());
        self::assertSame([['a' => 'b']], $readPreference->getTagSets());
    }

    public function testReadPreferenceIsInherited(): void
    {
        $database = $this->getDatabase();
        $database->setReadPreference(\MongoClient::RP_SECONDARY, [['a' => 'b']]);

        $collection = $database->selectCollection('test');
        self::assertSame(['type' => \MongoClient::RP_SECONDARY, 'tagsets' => [['a' => 'b']]], $collection->getReadPreference());
    }

    public function testWriteConcern(): void
    {
        $collection = $this->getCollection();

        self::assertTrue($collection->setWriteConcern('majority', 100));
        self::assertSame(['w' => 'majority', 'wtimeout' => 100], $collection->getWriteConcern());
    }

    public function testWriteConcernIsSetInDriver(): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $collection = $this->getCollection();
        self::assertTrue($collection->setWriteConcern(2, 100));

        // Only way to check whether options are passed down is through debugInfo
        $writeConcern = $collection->getCollection()->__debugInfo()['writeConcern'];

        self::assertSame(2, $writeConcern->getW());
        self::assertSame(100, $writeConcern->getWtimeout());
    }

    public function testWriteConcernIsInherited(): void
    {
        $database = $this->getDatabase();
        $database->setWriteConcern('majority', 100);

        $collection = $database->selectCollection('test');
        self::assertSame(['w' => 'majority', 'wtimeout' => 100], $collection->getWriteConcern());
    }

    public function testSaveInsert(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $objectId = new \MongoId($id);
        $expected = [
            'ok' => 1.0,
            'nModified' => 0,
            'n' => 1,
            'err' => null,
            'errmsg' => null,
            'upserted' => $objectId,
            'updatedExisting' => false,
        ];

        $document = ['_id' => $objectId, 'foo' => 'bar'];
        self::assertEquals($expected, $collection->save($document));

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
        $object = $newCollection->findOne();

        self::assertNotNull($object);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $object->_id);
        self::assertSame($id, (string) $object->_id);
        self::assertSame('bar', $object->foo);
    }

    public function testRemoveOne(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $document = ['_id' => new \MongoId($id), 'foo' => 'bar'];
        $collection->insert($document);
        $collection->remove(['_id' => new \MongoId($id)]);

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(0, $newCollection->count());
    }

    public function testSaveUpdate(): void
    {
        $expected = [
            'ok' => 1.0,
            'nModified' => 1,
            'n' => 1,
            'err' => null,
            'errmsg' => null,
            'updatedExisting' => true,
        ];

        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $insertDocument = ['_id' => new \MongoId($id), 'foo' => 'bar'];
        $saveDocument = ['_id' => new \MongoId($id), 'foo' => 'foo'];
        $collection->insert($insertDocument);
        self::assertEquals($expected, $collection->save($saveDocument));

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
        $object = $newCollection->findOne();

        self::assertNotNull($object);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $object->_id);
        self::assertSame($id, (string) $object->_id);
        self::assertSame('foo', $object->foo);
    }

    public function testSavingShouldReplaceTheWholeDocument(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $insertDocument = ['_id' => new \MongoId($id), 'foo' => 'bar'];
        $saveDocument = ['_id' => new \MongoId($id)];

        $collection->insert($insertDocument);
        $collection->save($saveDocument);

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
        $object = $newCollection->findOne();

        self::assertNotNull($object);
        self::assertArrayNotHasKey('bar', $object);
    }

    public function testSaveDuplicate(): void
    {
        $collection = $this->getCollection();

        $collection->createIndex(['foo' => 1], ['unique' => true]);

        $document = ['foo' => 'bar'];
        $collection->save($document);

        $this->expectException(\MongoDuplicateKeyException::class);

        unset($document['_id']);
        $collection->save($document);
    }

    public function testSaveEmptyKeys(): void
    {
        $document = [];
        $this->getCollection()->save($document);

        self::assertSame(1, $this->getCollection()->count());
    }

    public function testSaveEmptyObject(): void
    {
        $document = (object) [];
        $this->getCollection()->save($document);

        self::assertSame(1, $this->getCollection()->count());
    }

    public function testGetDBRef(): void
    {
        $collection = $this->getCollection();

        $insertDocument = ['_id' => 1, 'foo' => 'bar'];
        $collection->insert($insertDocument);

        $document = $collection->getDBRef([
            '$ref' => 'test',
            '$id' => 1,
        ]);
        self::assertEquals($insertDocument, $document);
    }

    public function testCreateDBRef(): void
    {
        $collection = $this->getCollection();
        $reference = $collection->createDBRef(['_id' => 'foo']);
        self::assertSame(
            [
                '$ref' => 'test',
                '$id' => 'foo',
            ],
            $reference,
        );
    }

    public function testCreateIndex(): void
    {
        $expected = [
            'createdCollectionAutomatically' => true,
            'numIndexesBefore' => 1,
            'numIndexesAfter' => 2,
            'ok' => 1.0,
        ];

        $collection = $this->getCollection();
        self::assertSame($expected, $collection->createIndex(['foo' => 1]));

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        $iterator = $newCollection->listIndexes();
        $indexes = iterator_to_array($iterator);
        self::assertCount(2, $indexes);
        $index = $indexes[1];
        self::assertSame(['foo' => 1], $index->getKey());
        self::assertSame('mongo-php-adapter.test', $index->getNamespace());
    }

    public function testCreateIndexInvalid(): void
    {
        $this->expectException(\MongoException::class);
        $this->expectExceptionMessage('index specification has no elements');

        $this->getCollection()->createIndex([]);
    }

    public function testCreateIndexTwice(): void
    {
        $this->getCollection()->createIndex(['foo' => 1]);

        $expected = [
            'createdCollectionAutomatically' => false,
            'numIndexesBefore' => 2,
            'numIndexesAfter' => 2,
            'note' => 'all indexes already exist',
            'ok' => 1.0,
        ];
        self::assertSame($expected, $this->getCollection()->createIndex(['foo' => 1]));
    }

    public function testCreateIndexWithDeprecatedOptions(): void
    {
        $this->getCollection()->createIndex(['foo' => 1], ['w' => 1]);

        $expected = [
            'createdCollectionAutomatically' => false,
            'numIndexesBefore' => 2,
            'numIndexesAfter' => 2,
            'note' => 'all indexes already exist',
            'ok' => 1.0,
        ];
        self::assertSame($expected, $this->getCollection()->createIndex(['foo' => 1]));
    }

    public function testCreateIndexTwiceWithSameName(): void
    {
        $this->getCollection()->createIndex(['foo' => 1], ['name' => 'test_index']);

        $expected = [
            'createdCollectionAutomatically' => false,
            'numIndexesBefore' => 2,
            'numIndexesAfter' => 2,
            'note' => 'all indexes already exist',
            'ok' => 1.0,
        ];
        self::assertSame($expected, $this->getCollection()->createIndex(['foo' => 1], ['name' => 'test_index']));
    }

    public function testCreateIndexTwiceWithDifferentName(): void
    {
        $this->getCollection()->createIndex(['foo' => 1], ['name' => 'test_index']);

        $expected = [
            'createdCollectionAutomatically' => false,
            'numIndexesBefore' => 2,
            'numIndexesAfter' => 2,
            'note' => 'all indexes already exist',
            'ok' => 1.0,
        ];
        self::assertSame($expected, $this->getCollection()->createIndex(['foo' => 1], ['name' => 'index_test']));
    }

    public function testCreateIndexTwiceWithDifferentOrder(): void
    {
        $this->getCollection()->createIndex(['foo' => 1, 'bar' => 1]);

        $expected = [
            'createdCollectionAutomatically' => false,
            'numIndexesBefore' => 2,
            'numIndexesAfter' => 3,
            'ok' => 1.0,
        ];
        self::assertSame($expected, $this->getCollection()->createIndex(['bar' => 1, 'foo' => 1]));
    }

    public function testCreateIndexesWithDifferentOptions(): void
    {
        $this->expectException(\MongoResultException::class);

        $this->getCollection()->createIndex(['foo' => 1]);

        $this->getCollection()->createIndex(['foo' => 1], ['unique' => true]);
    }

    /**
     * @dataProvider provideCreateIndexesWithIgnoredOptionsCases
     */
    public function testCreateIndexesWithIgnoredOptions($option): void
    {
        $this->getCollection()->createIndex(['foo' => 1]);

        $expected = [
            'createdCollectionAutomatically' => false,
            'numIndexesBefore' => 2,
            'numIndexesAfter' => 2,
            'note' => 'all indexes already exist',
            'ok' => 1.0,
        ];
        self::assertSame($expected, $this->getCollection()->createIndex(['foo' => 1], [$option => true]));
    }

    public static function provideCreateIndexesWithIgnoredOptionsCases(): iterable
    {
        return [
            'background' => ['background'],
            'dropDups' => ['dropDups'],
        ];
    }

    public function testCreateIndexWithSameNameAndDifferentOptions(): void
    {
        $this->expectException(\MongoResultException::class);

        $this->getCollection()->createIndex(['foo' => 1], ['name' => 'foo']);

        $this->getCollection()->createIndex(['bar' => 1], ['name' => 'foo']);
    }

    public function testEnsureIndex(): void
    {
        $expected = [
            'createdCollectionAutomatically' => true,
            'numIndexesBefore' => 1,
            'numIndexesAfter' => 2,
            'ok' => 1.0,
        ];

        $collection = $this->getCollection();
        self::assertEquals($expected, $collection->ensureIndex(['bar' => 1], ['unique' => true]));

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        $indexes = iterator_to_array($newCollection->listIndexes());
        self::assertCount(2, $indexes);
        $index = $indexes[1];
        self::assertSame(['bar' => 1], $index->getKey());
        self::assertTrue($index->isUnique());
        self::assertSame('mongo-php-adapter.test', $index->getNamespace());
    }

    public function testEnsureIndexAlreadyExists(): void
    {
        $collection = $this->getCollection();
        $collection->ensureIndex(['bar' => 1], ['unique' => true]);

        $expected = [
            'createdCollectionAutomatically' => false,
            'numIndexesBefore' => 2,
            'numIndexesAfter' => 2,
            'ok' => 1.0,
            'note' => 'all indexes already exist',
        ];
        self::assertEquals($expected, $collection->ensureIndex(['bar' => 1], ['unique' => true]));
    }

    public function testEnsureIndexAlreadyExistsWithDifferentOptions(): void
    {
        $collection = $this->getCollection();
        $collection->ensureIndex(['bar' => 1], ['unique' => true]);

        $this->expectException(\MongoResultException::class);
        $this->expectExceptionMessage('Index with name: bar_1 already exists with different options');
        $collection->ensureIndex(['bar' => 1]);
    }

    public function testDeleteIndexUsingIndexName(): void
    {
        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        $newCollection->createIndex(['bar' => 1], ['name' => 'bar']);

        $expected = [
            'nIndexesWas' => 2,
            'errmsg' => 'index not found with name [bar_1]',
            'ok' => 0.0,
        ];

        if (version_compare($this->getServerVersion(), '3.4.0', '>=')) {
            $expected['code'] = 27;
        }

        // Using assertMatches because newer versions (3.4.7?) also return `codeName`
        $this->assertMatches($expected, $this->getCollection()->deleteIndex('bar'));

        self::assertCount(2, iterator_to_array($newCollection->listIndexes()));
    }

    public function testDeleteIndexUsingField(): void
    {
        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        $newCollection->createIndex(['bar' => 1]);

        $expected = [
            'nIndexesWas' => 2,
            'ok' => 1.0,
        ];
        self::assertSame($expected, $this->getCollection()->deleteIndex('bar'));

        self::assertCount(1, iterator_to_array($newCollection->listIndexes()));
    }

    public function testDeleteIndexUsingKeys(): void
    {
        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        $newCollection->createIndex(['bar' => 1]);

        $expected = [
            'nIndexesWas' => 2,
            'ok' => 1.0,
        ];
        self::assertSame($expected, $this->getcollection()->deleteIndex(['bar' => 1]));

        self::assertCount(1, iterator_to_array($newCollection->listIndexes()));
    }

    public function testDeleteIndexes(): void
    {
        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        $newCollection->createIndex(['bar' => 1]);

        $expected = [
            'nIndexesWas' => 2,
            'msg' => 'non-_id indexes dropped for collection',
            'ok' => 1.0,
        ];
        self::assertSame($expected, $this->getcollection()->deleteIndexes());

        self::assertCount(1, iterator_to_array($newCollection->listIndexes())); // ID index is present by default
    }

    public function testDeleteIndexesForNonExistingCollection(): void
    {
        $result = $this->getCollection('nonExisting')->deleteIndexes();

        self::assertSame(0.0, $result['ok']);
        self::assertMatchesRegularExpression('#ns not found#', $result['errmsg']);
        if (version_compare($this->getServerVersion(), '3.4.0', '>=')) {
            self::assertSame(26, $result['code']);
            $expected['code'] = 26;
        }
    }

    /**
     * @dataProvider provideGetIndexInfoCases
     */
    public function testGetIndexInfo($expectedIndex, $fields, $options): void
    {
        $idIndex = [
            'v' => $this->getDefaultIndexVersion(),
            'key' => ['_id' => 1],
            'name' => '_id_',
            'ns' => 'mongo-php-adapter.test',
        ];

        $expectedIndexInfo = [$idIndex, $expectedIndex];

        $collection = $this->getCollection();
        $collection->createIndex($fields, $options);

        self::assertEquals(
            $expectedIndexInfo,
            $collection->getIndexInfo(),
        );
    }

    public function provideGetIndexInfoCases(): iterable
    {
        $indexVersion = $this->getDefaultIndexVersion();

        return [
            'plainIndex' => [
                'expectedIndex' => [
                    'v' => $indexVersion,
                    'key' => ['foo' => 1],
                    'name' => 'foo_1',
                    'ns' => 'mongo-php-adapter.test',
                ],
                'fields' => ['foo' => 1],
                'options' => [],
            ],
            'uniqueIndex' => [
                'expectedIndex' => [
                    'v' => $indexVersion,
                    'key' => ['foo' => 1],
                    'name' => 'foo_1',
                    'ns' => 'mongo-php-adapter.test',
                    'unique' => true,
                ],
                'fields' => ['foo' => 1],
                'options' => ['unique' => true],
            ],
            'sparseIndex' => [
                'expectedIndex' => [
                    'v' => $indexVersion,
                    'key' => ['foo' => 1],
                    'name' => 'foo_1',
                    'ns' => 'mongo-php-adapter.test',
                    'sparse' => true,
                ],
                'fields' => ['foo' => 1],
                'options' => ['sparse' => true],
            ],
            'ttlIndex' => [
                'expectedIndex' => [
                    'v' => $indexVersion,
                    'key' => ['foo' => 1],
                    'name' => 'foo_1',
                    'ns' => 'mongo-php-adapter.test',
                    'expireAfterSeconds' => 86400,
                ],
                'fields' => ['foo' => 1],
                'options' => ['expireAfterSeconds' => 86400],
            ],
            'textIndex' => [
                'expectedIndex' => [
                    'v' => $indexVersion,
                    'key' => [
                        '_fts' => 'text',
                        '_ftsx' => 1,
                    ],
                    'name' => 'foo_text',
                    'ns' => 'mongo-php-adapter.test',
                    'weights' => [
                        'foo' => 1,
                    ],
                    'default_language' => 'english',
                    'language_override' => 'language',
                    'textIndexVersion' => version_compare($this->getServerVersion(), '3.2.0', '>=') ? 3 : 2,
                ],
                'fields' => ['foo' => 'text'],
                'options' => [],
            ],
            'partialFilterExpression' => [
                'expectedIndex' => [
                    'v' => $indexVersion,
                    'key' => ['foo' => 1],
                    'name' => 'foo_1',
                    'ns' => 'mongo-php-adapter.test',
                    'partialFilterExpression' => [
                        'bar' => ['$gt' => 1],
                    ],
                ],
                'fields' => ['foo' => 1],
                'options' => [
                    'partialFilterExpression' => ['bar' => ['$gt' => 1]],
                ],
            ],
            'geoSpatial' => [
                'expectedIndex' => [
                    'v' => $indexVersion,
                    'key' => ['foo' => '2dsphere'],
                    'name' => 'foo_2dsphere',
                    'ns' => 'mongo-php-adapter.test',
                    '2dsphereIndexVersion' => version_compare($this->getServerVersion(), '3.2.0', '>=') ? 3 : 2,
                ],
                'fields' => ['foo' => '2dsphere'],
                'options' => [],
            ],
            'geoHaystack' => [
                'expectedIndex' => [
                    'v' => $indexVersion,
                    'key' => ['foo' => 'geoHaystack', 'bar' => 1],
                    'name' => 'foo_geoHaystack_bar_1',
                    'ns' => 'mongo-php-adapter.test',
                    'bucketSize' => 10,
                ],
                'fields' => ['foo' => 'geoHaystack', 'bar' => 1],
                'options' => ['bucketSize' => 10],
            ],
        ];
    }

    public function testFindAndModifyUpdate(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $document = ['_id' => new \MongoId($id), 'foo' => 'bar'];
        $collection->insert($document);
        $document = $collection->findAndModify(
            ['_id' => new \MongoId($id)],
            ['$set' => ['foo' => 'foo']],
        );
        self::assertSame('bar', $document['foo']);

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
        $object = $newCollection->findOne();

        self::assertNotNull($object);
        self::assertSame('foo', $object->foo);
    }

    public function testFindAndModifyUpdateWithUpdateOptions(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $document = ['_id' => new \MongoId($id), 'foo' => 'bar'];
        $collection->insert($document);
        $document = $collection->findAndModify(
            ['_id' => new \MongoId($id)],
            [],
            [],
            [
                'update' => ['bar' => 'foo'],
            ],
        );
        self::assertSame('bar', $document['foo']);

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
        $object = $newCollection->findOne();

        self::assertNotNull($object);
        self::assertSame('foo', $object->bar);
        self::assertArrayNotHasKey('foo', $object);
    }

    public function testFindAndModifyWithUpdateParamAndOption(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $document = ['_id' => new \MongoId($id), 'foo' => 'bar'];
        $collection->insert($document);

        $data = ['foo' => 'foo', 'bar' => 'bar'];

        $this->getCollection()->findAndModify(
            ['_id' => new \MongoId($id)],
            [$data],
            [],
            [
                'update' => ['$set' => ['foo' => 'foobar']],
                'upsert' => true,
            ],
        );

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
        $object = $newCollection->findOne();

        self::assertNotNull($object);
        self::assertSame('foobar', $object->foo);
        self::assertArrayNotHasKey('bar', $object);
    }

    public function testFindAndModifyUpdateReplace(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $document = ['_id' => new \MongoId($id), 'foo' => 'bar'];
        $collection->insert($document);
        $document = $collection->findAndModify(
            ['_id' => new \MongoId($id)],
            ['_id' => new \MongoId($id), 'foo' => 'boo'],
        );
        self::assertSame('bar', $document['foo']);

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
        $object = $newCollection->findOne();

        self::assertNotNull($object);
        self::assertSame('boo', $object->foo);
        self::assertArrayNotHasKey('bar', $object);
    }

    public function testFindAndModifyUpdateReturnNew(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $document = ['_id' => new \MongoId($id), 'foo' => 'bar'];
        $collection->insert($document);
        $document = $collection->findAndModify(
            ['_id' => new \MongoId($id)],
            ['$set' => ['foo' => 'foo']],
            null,
            ['new' => true],
        );
        self::assertSame('foo', $document['foo']);
    }

    public function testFindAndModifyWithFields(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $document = [
            '_id' => new \MongoId($id),
            'foo' => 'bar',
            'bar' => 'foo',
        ];
        $collection->insert($document);
        $document = $collection->findAndModify(
            ['_id' => new \MongoId($id)],
            ['$set' => ['foo' => 'foo']],
            ['foo' => true],
        );
        self::assertArrayNotHasKey('bar', $document);
        self::assertArrayHasKey('foo', $document);
    }

    public function testGroup(): void
    {
        $this->skipTestIf(version_compare($this->getServerVersion(), '4.2.0', '>='), 'Test does not apply to MongoDB >= 4.2.');

        $collection = $this->getCollection();

        $document1 = ['a' => 2];
        $collection->insert($document1);
        $document2 = ['b' => 5];
        $collection->insert($document2);
        $document3 = ['a' => 1];
        $collection->insert($document3);
        $keys = [];
        $initial = ['count' => 0];
        $reduce = 'function (obj, prev) { prev.count++; }';
        $condition = ['condition' => ['a' => ['$gt' => 1]]];

        $result = $collection->group($keys, $initial, $reduce, $condition);

        $this->assertMatches(
            [
                'retval' => [['count' => 1.0]],
                'count' => 1.0,
                'keys' => 1,
                'ok' => 1.0,
            ],
            $result,
        );
    }

    public function testMapReduce(): void
    {
        $data = [
            [
                'username' => 'jones',
                'likes' => 20.0,
                'text' => 'Hello world!',
            ],
            [
                'username' => 'bob',
                'likes' => 100.0,
                'text' => 'Hello world!',
            ],
            [
                'username' => 'bob',
                'likes' => 100.0,
                'text' => 'Hello world!',
            ],
        ];

        $collection = $this->getCollection();
        $collection->batchInsert($data);

        $map = 'function() {
            emit(this.username, { count: 1, likes: this.likes });
        }';

        $reduce = 'function(key, values) {
            var result = {count: 0, likes: 0};

            values.forEach(function(value) {
              result.count += value.count;
              result.likes += value.likes;
            });

            return result;
        }';

        $finalize = 'function (key, value) { value.test = "test"; return value; }';

        $command = [
            'mapreduce' => $this->getCollection()->getName(),
            'map' => new \MongoCode($map),
            'reduce' => new \MongoCode($reduce),
            'query' => (object) [],
            'out' => ['inline' => 1],
            'finalize' => new \MongoCode($finalize),
        ];

        $result = $this->getDatabase()->command($command);

        $expected = [
            [
                '_id' => 'bob',
                'value' => [
                    'count' => 2.0,
                    'likes' => 200.0,
                    'test' => 'test',
                ],
            ],
            [
                '_id' => 'jones',
                'value' => [
                    'count' => 1.0,
                    'likes' => 20.0,
                    'test' => 'test',
                ],
            ],
        ];

        usort($result['results'], static fn($a, $b) => strcasecmp($a['_id'], $b['_id']));

        self::assertSame(1.0, $result['ok']);
        self::assertEquals($expected, $result['results']);
    }

    public function testFindAndModifyResultException(): void
    {
        self::markTestSkipped('Test fails on travis-ci - skipped while investigating this');
        $collection = $this->getCollection();

        $this->expectException(\MongoResultException::class);

        $collection->findAndModify(
            ['inprogress' => false, 'name' => 'Next promo'],
            ['$unsupportedOperator' => ['tasks' => -1]],
            ['tasks' => true],
            ['new' => true],
        );
    }

    public function testFindAndModifyExceptionTimeout(): void
    {
        $this->failMaxTimeMS();

        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $this->expectException(\MongoExecutionTimeoutException::class);

        $document = $collection->findAndModify(
            ['_id' => new \MongoId($id)],
            null,
            null,
            ['maxTimeMS' => 1, 'remove' => true],
        );
    }

    public function testFindAndModifyRemove(): void
    {
        $id = '54203e08d51d4a1f868b456e';
        $collection = $this->getCollection();

        $document = ['_id' => new \MongoId($id), 'foo' => 'bar'];
        $collection->insert($document);
        $document = $collection->findAndModify(
            ['_id' => new \MongoId($id)],
            null,
            null,
            ['remove' => true],
        );

        self::assertEquals('bar', $document['foo']);

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(0, $newCollection->count());
    }

    public function testValidate(): void
    {
        $collection = $this->getCollection();
        $document = ['foo' => 'bar'];
        $collection->insert($document);
        $result = $collection->validate();

        $this->assertMatches(
            [
                'ns' => 'mongo-php-adapter.test',
                'nrecords' => 1,
                'nIndexes' => 1,
                'valid' => true,
                'errors' => [],
            ],
            $result,
        );
    }

    public function testDrop(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);
        $expected = [
            'ns' => (string) $this->getCollection(),
            'nIndexesWas' => 1,
            'ok' => 1.0,
        ];
        self::assertEquals($expected, $this->getCollection()->drop());
    }

    public function testEmptyCollectionName(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Collection name cannot be empty');

        new \MongoCollection($this->getDatabase(), '');
    }

    public function testSelectCollectionWithNullBytes(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Collection name cannot contain null bytes');

        new \MongoCollection($this->getDatabase(), 'foo' . \chr(0));
    }

    public function testSubCollectionWithNullBytes(): void
    {
        $collection = $this->getCollection();

        self::assertInstanceOf('MongoCollection', $collection->{'foo' . \chr(0)});
        self::assertSame('test', $collection->getName());
    }

    public function testSelectCollectionWithDatabaseObject(): void
    {
        $client = $this->getClient();
        $database = $this->getDatabase($client);

        $collection = $client->selectCollection($database, 'test');
        self::assertSame('mongo-php-adapter.test', (string) $collection);
    }

    public function testHasNextLoop(): void
    {
        $collection = $this->getCollection();
        for ($i = 0; $i < 5; ++$i) {
            $document = ['i' => $i];
            $collection->insert($document);
        }

        $cursor = $collection->find()->sort(['i' => 1]);
        $data = [];
        $i = 0;

        while ($cursor->hasNext()) {
            self::assertSame($i < 5, $cursor->hasNext());
            $row = $cursor->getNext();
            self::assertSame($i, $row['i']);
            $data[] = $row;
            ++$i;
        }

        self::assertCount(5, $data);
    }

    public function testProjectionWithBSONTypes(): void
    {
        $collection = $this->getCollection();

        $id = new \MongoId();
        $referencedId = new \MongoId();

        $data = [
            '_id' => $id,
            'loveItems' => [
                [
                    'sellable' => [
                        '$ref' => 'sellables',
                        '$id' => $referencedId,
                    ],
                ],
                [
                    'sellable' => [
                        '$ref' => 'sellables',
                        '$id' => new \MongoId(),
                    ],
                ],
            ],
        ];
        $collection->insert($data);

        $item = $collection->findOne(
            ['_id' => $id],
            ['loveItems' => ['$elemMatch' => ['sellable.$id' => $referencedId]]],
        );
        self::assertArrayHasKey('loveItems', $item);
        self::assertCount(1, $item['loveItems']);

        $cursor = $collection->find(
            ['_id' => $id],
            ['loveItems' => ['$elemMatch' => ['sellable.$id' => $referencedId]]],
        );
        $items = iterator_to_array($cursor, false);
        self::assertCount(1, $items);
        self::assertCount(1, $items[0]['loveItems']);
    }

    /**
     * @dataProvider provideFindWithRegexCases
     */
    public function testFindWithRegex($regex): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));
        $document = ['name' => 'FOO 123'];
        $this->getCollection()->insert($document);

        $cursor = $this->getCollection()->find(['name' => $regex]);
        self::assertSame(1, $cursor->count());
    }

    public static function provideFindWithRegexCases(): iterable
    {
        return [
            'MongoRegex' => [new \MongoRegex('/^foo.*/i')],
            'BSONRegex' => [new Regex('^foo.*', 'i')],
        ];
    }
}

class PrivatePropertiesStub
{
    private $foo = 'bar';
}

class ArrayObjectWithProtectedProperties extends \ArrayObject
{
    protected $something = 'baz';
}
