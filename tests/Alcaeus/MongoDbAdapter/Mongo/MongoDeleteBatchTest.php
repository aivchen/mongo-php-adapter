<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;

class MongoDeleteBatchTest extends TestCase
{
    public function testSerialize(): void
    {
        $batch = new \MongoDeleteBatch($this->getCollection());
        self::assertIsString(serialize($batch));
    }

    public function testDeleteOne(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoDeleteBatch($collection);

        $document = ['foo' => 'bar'];
        $collection->insert($document);
        unset($document['_id']);
        $collection->insert($document);

        self::assertTrue($batch->add(['q' => ['foo' => 'bar'], 'limit' => 1]));

        $expected = [
            'nRemoved' => 1,
            'ok' => true,
        ];

        self::assertSame($expected, $batch->execute());

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(1, $newCollection->count());
    }

    public function testDeleteMany(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoDeleteBatch($collection);

        $document = ['foo' => 'bar'];
        $collection->insert($document);
        unset($document['_id']);
        $collection->insert($document);

        self::assertTrue($batch->add(['q' => ['foo' => 'bar'], 'limit' => 0]));

        $expected = [
            'nRemoved' => 2,
            'ok' => true,
        ];

        self::assertSame($expected, $batch->execute());

        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(0, $newCollection->count());
    }

    public function testDeleteManyWithoutAck(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoDeleteBatch($collection);

        $document = ['foo' => 'bar'];
        $collection->insert($document);
        unset($document['_id']);
        $collection->insert($document);

        self::assertTrue($batch->add(['q' => ['foo' => 'bar'], 'limit' => 0]));

        $expected = [
            'nRemoved' => 0,
            'ok' => true,
        ];

        self::assertSame($expected, $batch->execute(['w' => 0]));
        sleep(1);
        $newCollection = $this->getCheckDatabase()->selectCollection('test');
        self::assertSame(0, $newCollection->count());
    }

    public function testValidateItem(): void
    {
        $collection = $this->getCollection();
        $batch = new \MongoDeleteBatch($collection);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Expected \$item to contain 'q' key");

        $batch->add([]);
    }
}
