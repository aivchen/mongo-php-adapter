<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;

class MongoUpdateBatchTest extends TestCase
{
    public function testSerialize(): void
    {
        $batch = new \MongoUpdateBatch($this->getCollection());
        self::assertIsString(serialize($batch));
    }

    public function testUpdateOne(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoUpdateBatch($collection);

        $document = ['foo' => 'bar'];
        $collection->insert($document);

        self::assertTrue($batch->add(['q' => ['foo' => 'bar'], 'u' => ['$set' => ['foo' => 'foo']]]));

        $expected = [
            'nMatched' => 1,
            'nModified' => 1,
            'nUpserted' => 0,
            'ok' => true,
        ];

        self::assertSame($expected, $batch->execute());

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertSame('foo', $record->foo);
    }

    public function testUpdateOneException(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoUpdateBatch($collection);

        $document = ['foo' => 'bar'];
        $collection->insert($document);
        $document = ['foo' => 'foo'];
        $collection->insert($document);
        $collection->createIndex(['foo' => 1], ['unique' => true]);

        self::assertTrue($batch->add(['q' => ['foo' => 'bar'], 'u' => ['$set' => ['foo' => 'foo']]]));

        $expected = [
            'writeErrors' => [
                [
                    'index' => 0,
                    'code' => 11000,
                ],
            ],
            'nMatched' => 0,
            'nModified' => 0,
            'nUpserted' => 0,
            'ok' => true,
        ];

        try {
            $batch->execute();
            self::fail('Expected MongoWriteConcernException');
        } catch (\MongoWriteConcernException $e) {
            self::assertSame('Failed write', $e->getMessage());
            self::assertSame(911, $e->getCode());
            $this->assertMatches($expected, $e->getDocument());
        }
    }

    public function testUpdateMany(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoUpdateBatch($collection);

        $document = ['foo' => 'bar'];
        $collection->insert($document);
        unset($document['_id']);
        $collection->insert($document);

        self::assertTrue($batch->add(['q' => ['foo' => 'bar'], 'u' => ['$set' => ['foo' => 'foo']], 'multi' => true]));

        $expected = [
            'nMatched' => 2,
            'nModified' => 2,
            'nUpserted' => 0,
            'ok' => true,
        ];

        self::assertSame($expected, $batch->execute());

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(2, $newCollection->count());
        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertSame('foo', $record->foo);
    }

    public function testUpdateManyWithoutAck(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoUpdateBatch($collection);

        $document = ['foo' => 'bar'];
        $collection->insert($document);
        unset($document['_id']);
        $collection->insert($document);

        self::assertTrue($batch->add(['q' => ['foo' => 'bar'], 'u' => ['$set' => ['foo' => 'foo']], 'multi' => true]));

        $expected = [
            'nMatched' => 0,
            'nModified' => 0,
            'nUpserted' => 0,
            'ok' => true,
        ];

        self::assertSame($expected, $batch->execute(['w' => 0]));

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(2, $newCollection->count());
        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertSame('foo', $record->foo);
    }

    public function testUpdateManyException(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoUpdateBatch($collection);

        $document = ['foo' => 'bar', 'bar' => 'bar'];
        $collection->insert($document);
        $document = ['foo' => 'foobar', 'bar' => 'bar'];
        $collection->insert($document);
        $collection->createIndex(['foo' => 1], ['unique' => true]);

        $batch->add(['q' => ['bar' => 'bar'], 'u' => ['$set' => ['foo' => 'foo']], 'multi' => true]);

        $expected = [
            'writeErrors' => [
                [
                    'index' => 0,
                    'code' => 11000,
                ],
            ],
            'nMatched' => 0,
            'nModified' => 0,
            'nUpserted' => 0,
            'ok' => true,
        ];

        try {
            $batch->execute();
            self::fail('Expected MongoWriteConcernException');
        } catch (\MongoWriteConcernException $e) {
            self::assertSame('Failed write', $e->getMessage());
            self::assertSame(911, $e->getCode());
            $this->assertMatches($expected, $e->getDocument());
        }
    }

    public function testUpsert(): void
    {
        $document = ['foo' => 'foo'];
        $this->getCollection()->insert($document);
        $batch = new \MongoUpdateBatch($this->getCollection());

        self::assertTrue($batch->add(['q' => ['foo' => 'foo'], 'u' => ['$set' => ['foo' => 'bar']], 'upsert' => true]));
        self::assertTrue($batch->add(['q' => ['bar' => 'foo'], 'u' => ['$set' => ['foo' => 'bar']], 'upsert' => true]));

        $expected = [
            'upserted' => [
                [
                    'index' => 1,
                ],
            ],
            'nMatched' => 1,
            'nModified' => 1,
            'nUpserted' => 1,
            'ok' => true,
        ];

        $result = $batch->execute();
        $this->assertMatches($expected, $result);

        self::assertInstanceOf('MongoId', $result['upserted'][0]['_id']);

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(0, $newCollection->count(['foo' => 'foo']));
        self::assertSame(2, $newCollection->count());
        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertSame('bar', $record->foo);
    }

    public function testValidateItem(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoUpdateBatch($collection);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Expected \$item to contain 'q' key");

        $batch->add([]);
    }
}
