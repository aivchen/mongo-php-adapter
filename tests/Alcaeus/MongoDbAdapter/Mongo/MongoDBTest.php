<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use MongoDB\Driver\ReadPreference;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoDBTest extends TestCase
{
    public function testSerialize(): void
    {
        self::assertIsString(serialize($this->getDatabase()));
    }

    public function testEmptyDatabaseName(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database name cannot be empty');

        new \MongoDB($this->getClient(), '');
    }

    public function testInvalidDatabaseName(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database name contains invalid characters');

        new \MongoDB($this->getClient(), '/');
    }

    public function testGetCollection(): void
    {
        $db = $this->getDatabase();
        $collection = $db->selectCollection('test');
        self::assertInstanceOf('MongoCollection', $collection);
        self::assertSame('mongo-php-adapter.test', (string) $collection);
    }

    public function testSelectCollectionEmptyName(): void
    {
        $database = $this->getDatabase();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Collection name cannot be empty');

        $database->selectCollection('');
    }

    public function testSelectCollectionWithNullBytes(): void
    {
        $database = $this->getDatabase();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Collection name cannot contain null bytes');

        $database->selectCollection('foo' . \chr(0));
    }

    public function testCreateCollectionWithoutOptions(): void
    {
        $database = $this->getDatabase();

        $collection = $database->createCollection('test');
        self::assertInstanceOf('MongoCollection', $collection);

        $checkDatabase = $this->getCheckDatabase();
        foreach ($checkDatabase->listCollections() as $collectionInfo) {
            if ($collectionInfo->getName() === 'test') {
                self::assertFalse($collectionInfo->isCapped());

                return;
            }
        }

        self::fail('Did not find expected collection');
    }

    public function testCreateCollection(): void
    {
        $database = $this->getDatabase();

        $collection = $database->createCollection('test', ['capped' => true, 'size' => 100]);
        self::assertInstanceOf('MongoCollection', $collection);

        $document = ['foo' => 'bar'];
        $collection->insert($document);

        $checkDatabase = $this->getCheckDatabase();
        foreach ($checkDatabase->listCollections() as $collectionInfo) {
            if ($collectionInfo->getName() === 'test') {
                self::assertTrue($collectionInfo->isCapped());

                return;
            }
        }
    }

    public function testCreateCollectionInvalidParameters(): void
    {
        $database = $this->getDatabase();

        self::assertInstanceOf('MongoCollection', $database->createCollection('test', ['capped' => 2, 'size' => 100]));
    }

    public function testGetCollectionProperty(): void
    {
        $db = $this->getDatabase();
        $collection = $db->test;
        self::assertInstanceOf('MongoCollection', $collection);
        self::assertSame('mongo-php-adapter.test', (string) $collection);
    }

    public function testCommand(): void
    {
        $db = $this->getDatabase();
        self::assertEquals(['ok' => 1], $db->command(['ping' => 1]));
    }

    public function testCommandError(): void
    {
        $db = $this->getDatabase();
        $expected = [
            'ok' => 0,
            'errmsg' => 'listDatabases may only be run against the admin database.',
            'code' => 13,
        ];

        // Using assertMatches because newer versions (3.4.7?) also return `codeName`
        $this->assertMatches($expected, $db->command(['listDatabases' => 1]));
    }

    public function testCommandCursorTimeout(): void
    {
        $database = $this->getDatabase();

        $this->failMaxTimeMS();

        $result = $database->command([
            'count' => 'test',
            'query' => ['a' => 1],
            'maxTimeMS' => 100,
        ]);

        self::assertSame([
            'ok' => 0.0,
            'errmsg' => 'operation exceeded time limit',
            'code' => 50,
        ], $result);
    }

    public function testReadPreference(): void
    {
        $database = $this->getDatabase();
        self::assertSame(['type' => \MongoClient::RP_PRIMARY], $database->getReadPreference());
        self::assertFalse($database->getSlaveOkay());

        self::assertTrue($database->setReadPreference(\MongoClient::RP_SECONDARY, [['a' => 'b']]));
        self::assertSame(['type' => \MongoClient::RP_SECONDARY, 'tagsets' => [['a' => 'b']]], $database->getReadPreference());
        self::assertTrue($database->getSlaveOkay());

        self::assertTrue($database->setSlaveOkay(true));
        self::assertSame(['type' => \MongoClient::RP_SECONDARY_PREFERRED, 'tagsets' => [['a' => 'b']]], $database->getReadPreference());

        self::assertTrue($database->setSlaveOkay(false));
        // Only test a subset since we don't keep tagsets around for RP_PRIMARY
        $this->assertMatches(['type' => \MongoClient::RP_PRIMARY], $database->getReadPreference());
    }

    public function testReadPreferenceIsSetInDriver(): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $database = $this->getDatabase();

        self::assertTrue($database->setReadPreference(\MongoClient::RP_SECONDARY, [['a' => 'b']]));

        // Only way to check whether options are passed down is through debugInfo
        $readPreference = $database->getDb()->__debugInfo()['readPreference'];

        self::assertSame(ReadPreference::SECONDARY, $readPreference->getModeString());
        self::assertSame([['a' => 'b']], $readPreference->getTagSets());
    }

    public function testReadPreferenceIsInherited(): void
    {
        $client = $this->getClient();
        $client->setReadPreference(\MongoClient::RP_SECONDARY, [['a' => 'b']]);

        $database = $client->selectDB('test');
        self::assertSame(['type' => \MongoClient::RP_SECONDARY, 'tagsets' => [['a' => 'b']]], $database->getReadPreference());
    }

    public function testWriteConcern(): void
    {
        $database = $this->getDatabase();

        self::assertTrue($database->setWriteConcern('majority', 100));
        self::assertSame(['w' => 'majority', 'wtimeout' => 100], $database->getWriteConcern());
    }

    public function testWriteConcernIsSetInDriver(): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $database = $this->getDatabase();
        self::assertTrue($database->setWriteConcern(2, 100));

        // Only way to check whether options are passed down is through debugInfo
        $writeConcern = $database->getDb()->__debugInfo()['writeConcern'];

        self::assertSame(2, $writeConcern->getW());
        self::assertSame(100, $writeConcern->getWtimeout());
    }

    public function testWriteConcernIsInherited(): void
    {
        $client = $this->getClient();
        $client->setWriteConcern(2, 100);

        $database = $client->selectDB('test');
        self::assertSame(['w' => 2, 'wtimeout' => 100], $database->getWriteConcern());
    }

    public function testProfilingLevel(): void
    {
        self::assertSame(\MongoDB::PROFILING_OFF, $this->getDatabase()->getProfilingLevel());
        self::assertSame(\MongoDB::PROFILING_OFF, $this->getDatabase()->setProfilingLevel(\MongoDB::PROFILING_SLOW));

        self::assertSame(\MongoDB::PROFILING_SLOW, $this->getDatabase()->getProfilingLevel());
        self::assertSame(\MongoDB::PROFILING_SLOW, $this->getDatabase()->setProfilingLevel(\MongoDB::PROFILING_ON));
        self::assertSame(\MongoDB::PROFILING_ON, $this->getDatabase()->getProfilingLevel());
    }

    public function testForceError(): void
    {
        $result = $this->getDatabase()->forceError();
        self::assertSame(0.0, $result['ok']);
    }

    public function testExecute(): void
    {
        $this->skipTestIf(version_compare($this->getServerVersion(), '4.2.0', '>='), 'Eval no longer works on MongoDB 4.2.0 and newer');

        $db = $this->getDatabase();
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);
        self::assertEquals(['ok' => 1, 'retval' => 1], $db->execute('return db.test.count();'));
    }

    public function testGetCollectionNames(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);
        self::assertContains('test', $this->getDatabase()->getCollectionNames());
    }

    public function testGetCollectionNamesExecutionTimeoutException(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);
        $database = $this->getDatabase();

        $this->failMaxTimeMS();

        $this->expectException(\MongoExecutionTimeoutException::class);

        $database->getCollectionNames(['maxTimeMS' => 1]);
    }

    public function testGetCollectionInfo(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);

        foreach ($this->getDatabase()->getCollectionInfo() as $collectionInfo) {
            if ($collectionInfo['name'] === 'test') {
                $expected = [
                    'name' => 'test',
                    'options' => [],
                ];

                if (version_compare($this->getServerVersion(), '3.4.0', '>=')) {
                    $expected += [
                        'type' => 'collection',
                        'info' => ['readOnly' => false],
                        'idIndex' => [
                            'v' => $this->getDefaultIndexVersion(),
                            'key' => ['_id' => 1],
                            'name' => '_id_',
                            'ns' => (string) $this->getCollection(),
                        ],
                    ];
                }
                $this->assertMatches($expected, $collectionInfo);

                return;
            }
        }

        self::fail('The test collection was not found');
    }

    public function testGetCollectionInfoExecutionTimeoutException(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);

        $database = $this->getDatabase();

        $this->failMaxTimeMS();

        $this->expectException(\MongoExecutionTimeoutException::class);

        $database->getCollectionInfo(['maxTimeMS' => 1]);
    }

    public function testListCollections(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);
        foreach ($this->getDatabase()->listCollections() as $collection) {
            self::assertInstanceOf('MongoCollection', $collection);

            if ($collection->getName() === 'test') {
                return;
            }
        }

        self::fail('The test collection was not found');
    }

    public function testGetCollectionNamesDoesNotListSystemCollections(): void
    {
        // Enable profiling to ensure we have a system.profile collection
        $this->getDatabase()->setProfilingLevel(\MongoDB::PROFILING_ON);

        try {
            $document = ['foo' => 'bar'];
            $this->getCollection()->insert($document);

            $collectionNames = $this->getDatabase()->getCollectionNames();
            self::assertNotContains('system.profile', $collectionNames);
        } finally {
            $this->getDatabase()->setProfilingLevel(\MongoDB::PROFILING_OFF);
        }
    }

    public function testGetCollectionNamesWithSystemCollections(): void
    {
        // Enable profiling to ensure we have a system.profile collection
        $this->getDatabase()->setProfilingLevel(\MongoDB::PROFILING_ON);

        try {
            $document = ['foo' => 'bar'];
            $this->getCollection()->insert($document);

            $collectionNames = $this->getDatabase()->getCollectionNames(['includeSystemCollections' => true]);
            self::assertContains('system.profile', $collectionNames);
        } finally {
            $this->getDatabase()->setProfilingLevel(\MongoDB::PROFILING_OFF);
        }
    }

    public function testListCollectionsExecutionTimeoutException(): void
    {
        $this->failMaxTimeMS();

        $this->expectException(\MongoExecutionTimeoutException::class);

        $this->getDatabase()->listCollections(['maxTimeMS' => 1]);
    }

    public function testDrop(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);
        self::assertSame(['dropped' => 'mongo-php-adapter', 'ok' => 1.0], $this->getDatabase()->drop());
    }

    public function testDropCollection(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);
        $expected = [
            'ns' => (string) $this->getCollection(),
            'nIndexesWas' => 1,
            'ok' => 1.0,
        ];
        self::assertEquals($expected, $this->getDatabase()->dropCollection('test'));
    }

    public function testRepair(): void
    {
        $this->skipTestIf(version_compare($this->getServerVersion(), '4.2.0', '>='), 'The "repairDatabase" has been removed in MongoDB 4.2.0');

        self::assertSame(['ok' => 1.0], $this->getDatabase()->repair());
    }
}
