<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;

class MongoGridFSFileTest extends TestCase
{
    public function testSerialize(): void
    {
        $this->prepareFile('abcd', ['filename' => 'foo']);
        $file = $this->getGridFS()->findOne(['filename' => 'foo']);
        self::assertInstanceOf(\MongoGridFSFile::class, $file);

        self::assertIsString(serialize($file));
    }

    public function testFileProperty(): void
    {
        $file = $this->getFile();
        self::assertArrayHasKey('_id', $file->file);
        $this->assertMatches(
            [
                'length' => 666,
                'filename' => 'file',
                'md5' => 'md5',
            ],
            $file->file,
        );
    }

    public function testGetFilename(): void
    {
        $file = $this->getFile();
        self::assertSame('file', $file->getFilename());
    }

    public function testGetSize(): void
    {
        $file = $this->getFile();
        self::assertSame(666, $file->getSize());
    }

    public function testWrite(): void
    {
        $filename = '/tmp/test-mongo-grid-fs-file';
        $id = $this->prepareFile('abcd', ['filename' => $filename]);
        @unlink($filename);
        $file = $this->getGridFS()->findOne(['_id' => $id]);
        self::assertInstanceOf(\MongoGridFSFile::class, $file);

        $file->write();

        self::assertFileExists($filename);
        self::assertSame('e2fc714c4727ee9395f324cd2e7f331f', md5_file($filename));
        unlink($filename);
    }

    public function testWriteSpecifyFilename(): void
    {
        $id = $this->prepareFile();
        $filename = '/tmp/test-mongo-grid-fs-file';
        @unlink($filename);
        $file = $this->getGridFS()->findOne(['_id' => $id]);
        self::assertInstanceOf(\MongoGridFSFile::class, $file);

        $file->write($filename);

        self::assertFileExists($filename);
        self::assertSame('e2fc714c4727ee9395f324cd2e7f331f', md5_file($filename));
        unlink($filename);
    }

    public function testGetBytes(): void
    {
        $id = $this->prepareFile();
        $file = $this->getFile(['_id' => $id, 'length' => 4]);

        $result = $file->getBytes();

        self::assertSame('abcd', $result);
    }

    public function testGetResource(): void
    {
        $data = str_repeat('a', 500 * 1024);
        $id = $this->prepareFile($data);
        $file = $this->getGridFS()->findOne(['_id' => $id]);
        self::assertInstanceOf(\MongoGridFSFile::class, $file);

        $result = $file->getResource();

        self::assertTrue(\is_resource($result));
        self::assertSame($data, stream_get_contents($result));
    }

    /**
     * @var \MongoGridFSFile
     */
    protected function getFile($extra = [])
    {
        $file = [
            '_id' => new \MongoId(),
            'length' => 666,
            'filename' => 'file',
            'md5' => 'md5',
        ];
        $file = array_merge($file, $extra);

        return new \MongoGridFSFile($this->getGridFS(), $file);
    }

    /**
     * @var \MongoID
     */
    protected function prepareFile($data = 'abcd', $extra = [])
    {
        $collection = $this->getGridFS();

        return $collection->storeBytes($data, $extra);
    }

    /**
     * @param string $name
     * @return \MongoGridFS
     */
    protected function getGridFS($name = 'testfs', ?\MongoDB $database = null)
    {
        if ($database === null) {
            $database = $this->getDatabase();
        }

        return new \MongoGridFS($database, $name);
    }
}
