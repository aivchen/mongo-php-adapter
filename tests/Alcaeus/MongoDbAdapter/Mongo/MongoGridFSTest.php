<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;

class MongoGridFSTest extends TestCase
{
    public function testSerialize(): void
    {
        self::assertIsString(serialize($this->getGridFS()));
    }

    public function testChunkProperty(): void
    {
        $collection = $this->getGridFS();
        self::assertInstanceOf('MongoCollection', $collection->chunks);
        self::assertSame('mongo-php-adapter.fs.chunks', (string) $collection->chunks);
    }

    public function testCustomCollectionName(): void
    {
        $collection = $this->getGridFS('foofs');
        self::assertSame('mongo-php-adapter.foofs.files', (string) $collection);
        self::assertInstanceOf('MongoCollection', $collection->chunks);
        self::assertSame('mongo-php-adapter.foofs.chunks', (string) $collection->chunks);
    }

    public function testDrop(): void
    {
        $collection = $this->getGridFS();

        $document = ['foo' => 'bar'];
        $collection->insert($document);
        unset($document['_id']);
        $collection->chunks->insert($document);

        $collection->drop();

        $newCollection = $this->getCheckDatabase()->selectCollection('fs.files');
        $newChunksCollection = $this->getCheckDatabase()->selectCollection('fs.chunks');
        self::assertSame(0, $newCollection->count());
        self::assertSame(0, $newChunksCollection->count());
    }

    public function testFindReturnsGridFSCursor(): void
    {
        $this->prepareData();
        $collection = $this->getGridFS();

        self::assertInstanceOf('MongoGridFSCursor', $collection->find());
    }

    public function testStoringData(): void
    {
        $collection = $this->getGridFS();

        $id = $collection->storeBytes(
            'abcd',
            [
                'foo' => 'bar',
                'chunkSize' => 2,
            ],
        );

        $newCollection = $this->getCheckDatabase()->selectCollection('fs.files');
        $newChunksCollection = $this->getCheckDatabase()->selectCollection('fs.chunks');
        self::assertSame(1, $newCollection->count());
        self::assertSame(2, $newChunksCollection->count());

        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $record->_id);
        self::assertSame((string) $id, (string) $record->_id);
        self::assertSame('bar', $record->foo);
        self::assertSame(4, $record->length);
        self::assertSame(2, $record->chunkSize);
        self::assertSame('e2fc714c4727ee9395f324cd2e7f331f', $record->md5);

        $chunksCursor = $newChunksCollection->find([], ['sort' => ['n' => 1]]);
        $chunks = iterator_to_array($chunksCursor);
        $firstChunk = $chunks[0];
        self::assertNotNull($firstChunk);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $firstChunk->files_id);
        self::assertSame((string) $id, (string) $firstChunk->files_id);
        self::assertSame(0, $firstChunk->n);
        self::assertInstanceOf('MongoDB\BSON\Binary', $firstChunk->data);
        self::assertSame('ab', (string) $firstChunk->data->getData());

        $secondChunck = $chunks[1];
        self::assertNotNull($secondChunck);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $secondChunck->files_id);
        self::assertSame((string) $id, (string) $secondChunck->files_id);
        self::assertSame(1, $secondChunck->n);
        self::assertInstanceOf('MongoDB\BSON\Binary', $secondChunck->data);
        self::assertSame('cd', (string) $secondChunck->data->getData());
    }

    public function testIndexesCreation(): void
    {
        $collection = $this->getGridFS();

        $id = $collection->storeBytes(
            'abcd',
            [
                'foo' => 'bar',
                'chunkSize' => 2,
            ],
        );

        $newChunksCollection = $this->getCheckDatabase()->selectCollection('fs.chunks');
        $indexes = iterator_to_array($newChunksCollection->listIndexes());
        self::assertCount(2, $indexes);
        $index = $indexes[1];
        self::assertSame(['files_id' => 1, 'n' => 1], $index->getKey());
        self::assertTrue($index->isUnique());
    }

    public function testDelete(): void
    {
        $collection = $this->getGridFS();
        $id = $this->prepareFile();

        $collection->delete($id);

        $newCollection = $this->getCheckDatabase()->selectCollection('fs.files');
        $newChunksCollection = $this->getCheckDatabase()->selectCollection('fs.chunks');
        self::assertSame(0, $newCollection->count());
        self::assertSame(0, $newChunksCollection->count());
    }

    public function testRemove(): void
    {
        $collection = $this->getGridFS();
        $this->prepareFile('data', ['foo' => 'bar']);
        $this->prepareFile('data', ['foo' => 'bar']);

        $collection->remove(['foo' => 'bar']);

        $newCollection = $this->getCheckDatabase()->selectCollection('fs.files');
        $newChunksCollection = $this->getCheckDatabase()->selectCollection('fs.chunks');
        self::assertSame(0, $newCollection->count());
        self::assertSame(0, $newChunksCollection->count());
    }

    public function testStoreFile(): void
    {
        $collection = $this->getGridFS();

        $id = $collection->storeFile(__FILE__, ['chunkSize' => 100, 'foo' => 'bar']);

        $newCollection = $this->getCheckDatabase()->selectCollection('fs.files');
        $newChunksCollection = $this->getCheckDatabase()->selectCollection('fs.chunks');
        self::assertSame(1, $newCollection->count());

        $filename = __FILE__;
        $md5 = md5_file($filename);
        $size = filesize($filename);
        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $record->_id);
        self::assertSame((string) $id, (string) $record->_id);
        self::assertSame('bar', $record->foo);
        self::assertSame($size, $record->length);
        self::assertSame(100, $record->chunkSize);
        self::assertSame($md5, $record->md5);
        self::assertSame($filename, $record->filename);

        $numberOfChunks = (int) ceil($size / 100);
        self::assertSame($numberOfChunks, $newChunksCollection->count());
        $expectedContent = substr(file_get_contents(__FILE__), 0, 100);

        $firstChunk = $newChunksCollection->findOne([], ['sort' => ['n' => 1]]);
        self::assertNotNull($firstChunk);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $firstChunk->files_id);
        self::assertSame((string) $id, (string) $firstChunk->files_id);
        self::assertSame(0, $firstChunk->n);
        self::assertInstanceOf('MongoDB\BSON\Binary', $firstChunk->data);
        self::assertSame($expectedContent, (string) $firstChunk->data->getData());
    }

    public function testStoreFileResource(): void
    {
        $collection = $this->getGridFS();

        $id = $collection->storeFile(
            fopen(__FILE__, 'r'),
            ['chunkSize' => 100, 'foo' => 'bar', 'filename' => 'test.php'],
        );

        $newCollection = $this->getCheckDatabase()->selectCollection('fs.files');
        $newChunksCollection = $this->getCheckDatabase()->selectCollection('fs.chunks');
        self::assertSame(1, $newCollection->count());

        $md5 = md5_file(__FILE__);
        $size = filesize(__FILE__);
        $filename = basename(__FILE__);
        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $record->_id);
        self::assertSame((string) $id, (string) $record->_id);
        self::assertSame('bar', $record->foo);
        self::assertSame($size, $record->length);
        self::assertSame(100, $record->chunkSize);
        self::assertSame($md5, $record->md5);
        self::assertSame('test.php', $record->filename);

        $numberOfChunks = (int) ceil($size / 100);
        self::assertSame($numberOfChunks, $newChunksCollection->count());
        $expectedContent = substr(file_get_contents(__FILE__), 0, 100);

        $firstChunk = $newChunksCollection->findOne([], ['sort' => ['n' => 1]]);
        self::assertNotNull($firstChunk);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $firstChunk->files_id);
        self::assertSame((string) $id, (string) $firstChunk->files_id);
        self::assertSame(0, $firstChunk->n);
        self::assertInstanceOf('MongoDB\BSON\Binary', $firstChunk->data);
        self::assertSame($expectedContent, (string) $firstChunk->data->getData());
    }

    public function testStoreUpload(): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));
        $collection = $this->getGridFS();

        $_FILES['foo'] = [
            'name' => 'test.php',
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => __FILE__,
        ];

        $id = $collection->storeUpload(
            'foo',
            ['chunkSize' => 100, 'foo' => 'bar'],
        );

        $newCollection = $this->getCheckDatabase()->selectCollection('fs.files');
        $newChunksCollection = $this->getCheckDatabase()->selectCollection('fs.chunks');
        self::assertSame(1, $newCollection->count());

        $md5 = md5_file(__FILE__);
        $size = filesize(__FILE__);
        $record = $newCollection->findOne();
        self::assertNotNull($record);
        self::assertInstanceOf('MongoDB\BSON\ObjectID', $record->_id);
        self::assertSame((string) $id, (string) $record->_id);
        self::assertSame('bar', $record->foo);
        self::assertSame($size, $record->length);
        self::assertSame(100, $record->chunkSize);
        self::assertSame($md5, $record->md5);
        self::assertSame('test.php', $record->filename);

        $numberOfChunks = (int) ceil($size / 100);
        self::assertSame($numberOfChunks, $newChunksCollection->count());
    }

    public function testFindOneReturnsFile(): void
    {
        $collection = $this->getGridFS();
        $this->prepareFile();

        $result = $collection->findOne();

        self::assertInstanceOf('MongoGridFSFile', $result);
    }

    public function testFindOneWithLegacyProjectionReturnsFile(): void
    {
        $collection = $this->getGridFS();
        $this->prepareFile('abcd', ['date' => new \MongoDate()]);

        $result = $collection->findOne([], ['date']);

        self::assertInstanceOf('MongoGridFSFile', $result);
        self::assertCount(2, $result->file);
        self::assertArrayHasKey('date', $result->file);
    }

    public function testFindOneWithFilenameReturnsFile(): void
    {
        $collection = $this->getGridFS();
        $this->prepareFile('abcd', ['filename' => 'abcd']);
        $this->prepareFile('test', ['filename' => 'test']);
        $this->prepareFile('zyxv', ['filename' => 'zyxv']);

        $result = $collection->findOne('test');

        self::assertInstanceOf('MongoGridFSFile', $result);
        self::assertSame('test', $result->getBytes());
    }

    public function testFindOneNotFoundReturnsNull(): void
    {
        $collection = $this->getGridFS();

        $result = $collection->findOne();

        self::assertNull($result);
    }

    public function testPut(): void
    {
        $collection = $this->getGridFS();

        $id = $collection->put(__FILE__, ['chunkSize' => 100, 'foo' => 'bar']);

        $newCollection = $this->getCheckDatabase()->selectCollection('fs.files');
        $newChunksCollection = $this->getCheckDatabase()->selectCollection('fs.chunks');
        self::assertSame(1, $newCollection->count());

        $size = filesize(__FILE__);
        $numberOfChunks = (int) ceil($size / 100);
        self::assertSame($numberOfChunks, $newChunksCollection->count());
    }

    public function testStoreByteExceptionWhileInsertingRecord(): void
    {
        $id = new \MongoId();

        $collection = $this->getGridFS();

        $document = ['_id' => $id];
        $collection->insert($document);

        $this->expectException(\MongoGridFSException::class);
        $this->expectErrorMessageMatches('/Could not store file:.* E11000 duplicate key error .* mongo-php-adapter\.fs\.files/');
        $this->expectExceptionCode(11000);

        $collection->storeBytes('foo', ['_id' => $id]);
    }

    public function testStoreByteExceptionWhileInsertingChunks(): void
    {
        $collection = $this->getGridFS();
        $collection->chunks->createIndex(['n' => 1], ['unique' => true]);

        $document = ['n' => 0];
        $collection->chunks->insert($document);

        $this->expectException(\MongoGridFSException::class);
        $this->expectErrorMessageMatches('/Could not store file:.* E11000 duplicate key error .* mongo-php-adapter\.fs\.chunks/');
        $this->expectExceptionCode(11000);

        $collection->storeBytes('foo');
    }

    public function testStoreFileExceptionWhileInsertingRecord(): void
    {
        $id = new \MongoId();

        $collection = $this->getGridFS();
        $document = ['_id' => $id];
        $collection->insert($document);

        $this->expectException(\MongoGridFSException::class);
        $this->expectErrorMessageMatches('/Could not store file:.* E11000 duplicate key error .* mongo-php-adapter\.fs\.files/');
        $this->expectExceptionCode(11000);

        $collection->storeFile(__FILE__, ['_id' => $id]);
    }

    public function testStoreFileExceptionWhileInsertingChunks(): void
    {
        $collection = $this->getGridFS();
        $collection->chunks->createIndex(['n' => 1], ['unique' => true]);

        $document = ['n' => 0];
        $collection->chunks->insert($document);

        $this->expectException(\MongoGridFSException::class);
        $this->expectErrorMessageMatches('/Could not store file:.* E11000 duplicate key error .* mongo-php-adapter\.fs\.chunks/');
        $this->expectExceptionCode(11000);

        $collection->storeFile(__FILE__);
    }

    public function testStoreFileExceptionWhileUpdatingFileRecord(): void
    {
        $collection = $this->getGridFS();
        $collection->createIndex(['length' => 1], ['unique' => true]);

        $document = ['length' => filesize(__FILE__)];
        $collection->insert($document);

        $this->expectException(\MongoGridFSException::class);
        $this->expectErrorMessageMatches('/Could not store file:.* E11000 duplicate key error .* mongo-php-adapter\.fs\.files/');
        $this->expectExceptionCode(11000);

        $collection->storeFile(fopen(__FILE__, 'r'));
    }

    /**
     * @return \MongoID
     */
    protected function prepareFile($data = 'abcd', $extra = [])
    {
        $collection = $this->getGridFS();

        // to make sure we have multiple chunks
        $extra += ['chunkSize' => 2];

        return $collection->storeBytes($data, $extra);
    }
}
