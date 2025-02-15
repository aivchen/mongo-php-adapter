<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;

class MongoGridFSCursorTest extends TestCase
{
    public function testSerialize(): void
    {
        $gridfs = $this->getGridFS();
        $gridfs->storeBytes('foo', ['filename' => 'foo.txt']);
        $gridfs->storeBytes('bar', ['filename' => 'bar.txt']);
        $cursor = $gridfs->find(['filename' => 'foo.txt']);

        self::assertIsString(serialize($cursor));
    }

    public function testCursorItems(): void
    {
        $gridfs = $this->getGridFS();
        $id = $gridfs->storeBytes('foo', ['filename' => 'foo.txt']);
        $gridfs->storeBytes('bar', ['filename' => 'bar.txt']);

        $cursor = $gridfs->find(['filename' => 'foo.txt']);
        self::assertCount(1, $cursor);
        foreach ($cursor as $key => $value) {
            self::assertSame((string) $id, $key);
            self::assertInstanceOf('MongoGridFSFile', $value);
            self::assertSame('foo', $value->getBytes());

            $this->assertMatches([
                'filename' => 'foo.txt',
                'chunkSize' => 261120,
                'length' => 3,
                'md5' => 'acbd18db4cc2f85cedef654fccc4a4d8',
            ], $value->file);
        }
    }

    public function testInterfaces(): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $gridfs = $this->getGridFS();
        $id = $gridfs->storeBytes('foo', ['filename' => 'foo.txt']);
        $gridfs->storeBytes('bar', ['filename' => 'bar.txt']);

        $cursor = $gridfs->find(['filename' => 'foo.txt']);
        self::assertInstanceOf(\Countable::class, $cursor);
    }
}
