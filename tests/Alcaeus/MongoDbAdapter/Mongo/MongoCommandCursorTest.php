<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use MongoDB\Database;
use MongoDB\Driver\ReadPreference;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoCommandCursorTest extends TestCase
{
    public function testSerialize(): void
    {
        $this->prepareData();
        $cursor = $this->getCollection()->aggregateCursor([['$match' => ['foo' => 'bar']]]);
        self::assertIsString(serialize($cursor));
    }

    public function testInfo(): void
    {
        $this->prepareData();
        $host = $this->getCurrentHost();
        $cursor = $this->getCollection()->aggregateCursor([['$match' => ['foo' => 'bar']]]);

        $expected = [
            'ns' => 'mongo-php-adapter.test',
            'limit' => 0,
            'batchSize' => 0,
            'skip' => 0,
            'flags' => 0,
            'query' => [
                'aggregate' => 'test',
                'pipeline' => [
                    [
                        '$match' => ['foo' => 'bar'],
                    ],
                ],
                'cursor' => new \stdClass(),
            ],
            'fields' => null,
            'started_iterating' => false,
        ];
        $info = $cursor->info();
        self::assertEquals($expected, $info);

        // Ensure cursor started iterating
        $array = iterator_to_array($cursor);

        $expected['started_iterating'] = true;
        $expected += [
            'id' => 0,
            'at' => 0,
            'numReturned' => 0,
            'server' => "{$host}:27017;-;.;" . getmypid(),
            'host' => $host,
            'port' => 27017,
            'connection_type_desc' => 'STANDALONE',
        ];

        $this->assertMatches($expected, $cursor->info());

        $i = 0;
        foreach ($array as $key => $value) {
            self::assertEquals($i, $key);
            ++$i;
        }
    }

    /**
     * @dataProvider provideCommandAppliesCorrectReadPreferenceCases
     */
    public function testCommandAppliesCorrectReadPreference($command, $expectedReadPreference): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $checkReadPreference = static function ($other) use ($expectedReadPreference) {
            if (!\is_array($other)) {
                return false;
            }

            if (!\array_key_exists('readPreference', $other)) {
                return false;
            }

            if (!$other['readPreference'] instanceof ReadPreference) {
                return false;
            }

            return $other['readPreference']->getModeString() === $expectedReadPreference;
        };

        $databaseMock = $this->createMock(Database::class);
        $databaseMock
            ->expects(self::once())
            ->method('command')
            ->with(self::anything(), self::callback($checkReadPreference))
            ->willReturn(new \ArrayIterator());

        $cursor = new \MongoCommandCursor($this->getClient(), (string) $this->getDatabase(), $command);
        $reflection = new \ReflectionProperty($cursor, 'db');
        $reflection->setAccessible(true);
        $reflection->setValue($cursor, $databaseMock);
        $cursor->setReadPreference(\MongoClient::RP_SECONDARY);

        iterator_to_array($cursor);

        self::assertSame(\MongoClient::RP_SECONDARY, $cursor->getReadPreference()['type']);
    }

    public function provideCommandAppliesCorrectReadPreferenceCases(): iterable
    {
        return [
            'findAndUpdate' => [
                [
                    'findandmodify' => (string) $this->getCollection(),
                    'query' => [],
                    'update' => ['$inc' => ['field' => 1]],
                ],
                ReadPreference::PRIMARY,
            ],
            'findAndRemove' => [
                [
                    'findandremove' => (string) $this->getCollection(),
                    'query' => [],
                ],
                ReadPreference::PRIMARY,
            ],
            'mapReduceWithOut' => [
                [
                    'mapReduce' => (string) $this->getCollection(),
                    'out' => 'sample',
                ],
                ReadPreference::PRIMARY,
            ],
            'mapReduceWithOutInline' => [
                [
                    'mapReduce' => (string) $this->getCollection(),
                    'out' => ['inline' => 1],
                ],
                ReadPreference::SECONDARY,
            ],
            'count' => [
                [
                    'count' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
            'group' => [
                [
                    'group' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
            'dbStats' => [
                [
                    'dbStats' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
            'geoNear' => [
                [
                    'geoNear' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
            'geoWalk' => [
                [
                    'geoWalk' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
            'distinct' => [
                [
                    'distinct' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
            'aggregate' => [
                [
                    'aggregate' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
            'collStats' => [
                [
                    'collStats' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
            'geoSearch' => [
                [
                    'geoSearch' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
            'parallelCollectionScan' => [
                [
                    'parallelCollectionScan' => (string) $this->getCollection(),
                ],
                ReadPreference::SECONDARY,
            ],
        ];
    }

    public function testInterfaces(): void
    {
        $this->prepareData();
        $cursor = $this->getCollection()->aggregateCursor([['$match' => ['foo' => 'bar']]]);

        self::assertInstanceOf(\MongoCursorInterface::class, $cursor);
    }
}
