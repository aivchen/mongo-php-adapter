<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;

class MongoInsertBatchTest extends TestCase
{
    public function testSerialize(): void
    {
        $batch = new \MongoInsertBatch($this->getCollection());
        self::assertIsString(serialize($batch));
    }

    public function testInsertBatch(): void
    {
        $batch = new \MongoInsertBatch($this->getCollection());

        self::assertTrue($batch->add(['foo' => 'bar']));
        self::assertTrue($batch->add(['bar' => 'foo']));

        $expected = [
            'nInserted' => 2,
            'ok' => true,
        ];

        self::assertSame($expected, $batch->execute());

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(2, $newCollection->count());
        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertSame('bar', $record->foo);
    }

    public function testInsertBatchWithoutAck(): void
    {
        $batch = new \MongoInsertBatch($this->getCollection());

        self::assertTrue($batch->add(['foo' => 'bar']));
        self::assertTrue($batch->add(['bar' => 'foo']));

        $expected = [
            'nInserted' => 0,
            'ok' => true,
        ];

        self::assertSame($expected, $batch->execute(['w' => 0]));
        sleep(1);

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(2, $newCollection->count());
        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertSame('bar', $record->foo);
    }

    public function testInsertBatchError(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoInsertBatch($collection);
        $collection->createIndex(['foo' => 1], ['unique' => true]);

        self::assertTrue($batch->add(['foo' => 'bar']));
        self::assertTrue($batch->add(['foo' => 'bar']));

        $expected = [
            'writeErrors' => [
                [
                    'index' => 1,
                    'code' => 11000,
                ],
            ],
            'nInserted' => 1,
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
}
