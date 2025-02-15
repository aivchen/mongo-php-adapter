<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use Alcaeus\MongoDbAdapter\TypeConverter;
use Countable;
use MongoDB\Driver\ReadPreference;
use MongoDB\Model\BSONDocument;
use MongoDB\Operation\Find;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoCursorTest extends TestCase
{
    public static function provideCursorAppliesOptionsCases(): iterable
    {
        function getMissingOptionCallback($optionName)
        {
            return static function ($value) use ($optionName) {
                return
                    \is_array($value)
                    && !\array_key_exists($optionName, $value);
            };
        }

        function getBasicCheckCallback($expected, $optionName)
        {
            return static function ($value) use ($expected, $optionName) {
                return
                    \is_array($value)
                    && \array_key_exists($optionName, $value)
                    && $value[$optionName] == $expected;
            };
        }

        function getModifierCheckCallback($expected, $modifierName)
        {
            return static function ($value) use ($expected, $modifierName) {
                return
                    \is_array($value)
                    && \is_array($value['modifiers'])
                    && \array_key_exists($modifierName, $value['modifiers'])
                    && $value['modifiers'][$modifierName] == $expected;
            };
        }

        $tests = [
            'allowPartialResults' => [
                getBasicCheckCallback(true, 'allowPartialResults'),
                static function (\MongoCursor $cursor): void {
                    $cursor->partial(true);
                },
            ],
            'batchSize' => [
                getBasicCheckCallback(10, 'batchSize'),
                static function (\MongoCursor $cursor): void {
                    $cursor->batchSize(10);
                },
            ],
            'cursorTypeNonTailable' => [
                getMissingOptionCallback('cursorType'),
                static function (\MongoCursor $cursor): void {
                    $cursor
                        ->tailable(false)
                        ->awaitData(true);
                },
            ],
            'cursorTypeTailable' => [
                getBasicCheckCallback(Find::TAILABLE, 'cursorType'),
                static function (\MongoCursor $cursor): void {
                    $cursor->tailable(true);
                },
            ],
            'cursorTypeTailableAwait' => [
                getBasicCheckCallback(Find::TAILABLE_AWAIT, 'cursorType'),
                static function (\MongoCursor $cursor): void {
                    $cursor->tailable(true)->awaitData(true);
                },
            ],
            'hint' => [
                getModifierCheckCallback('index_name', '$hint'),
                static function (\MongoCursor $cursor): void {
                    $cursor->hint('index_name');
                },
            ],
            'limit' => [
                getBasicCheckCallback(5, 'limit'),
                static function (\MongoCursor $cursor): void {
                    $cursor->limit(5);
                },
            ],
            'maxTimeMS' => [
                getBasicCheckCallback(100, 'maxTimeMS'),
                static function (\MongoCursor $cursor): void {
                    $cursor->maxTimeMS(100);
                },
            ],
            'noCursorTimeout' => [
                getBasicCheckCallback(true, 'noCursorTimeout'),
                static function (\MongoCursor $cursor): void {
                    $cursor->immortal(true);
                },
            ],
            'slaveOkay' => [
                getBasicCheckCallback(new ReadPreference(ReadPreference::SECONDARY_PREFERRED), 'readPreference'),
                static function (\MongoCursor $cursor): void {
                    $cursor->slaveOkay(true);
                },
            ],
            'slaveOkayWithReadPreferenceSet' => [
                getBasicCheckCallback(new ReadPreference(ReadPreference::SECONDARY), 'readPreference'),
                static function (\MongoCursor $cursor): void {
                    $cursor
                        ->setReadPreference(\MongoClient::RP_SECONDARY)
                        ->slaveOkay(true);
                },
            ],
            'projectionDefaultFields' => [
                getBasicCheckCallback(new BSONDocument(['_id' => false, 'foo' => true]), 'projection'),
            ],
            'projectionDifferentFields' => [
                getBasicCheckCallback(new BSONDocument(['_id' => false, 'foo' => true, 'bar' => true]), 'projection'),
                static function (\MongoCursor $cursor): void {
                    $cursor->fields(['_id' => false, 'foo' => true, 'bar' => true]);
                },
            ],
            'readPreferencePrimary' => [
                getBasicCheckCallback(new ReadPreference(ReadPreference::PRIMARY), 'readPreference'),
                static function (\MongoCursor $cursor): void {
                    $cursor->setReadPreference(\MongoClient::RP_PRIMARY);
                },
            ],
            'skip' => [
                getBasicCheckCallback(5, 'skip'),
                static function (\MongoCursor $cursor): void {
                    $cursor->skip(5);
                },
            ],
            'sort' => [
                getBasicCheckCallback(['foo' => -1], 'sort'),
                static function (\MongoCursor $cursor): void {
                    $cursor->sort(['foo' => -1]);
                },
            ],
        ];

        return $tests;
    }

    public function testSerialize(): void
    {
        $this->prepareData();
        $cursor = $this->getCollection()->find(['foo' => 'bar']);
        self::assertIsString(serialize($cursor));
    }

    public function testCursorConvertsTypes(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar']);
        self::assertCount(2, $cursor);

        $this->assertCursorIteration($cursor);
    }

    public function testCursorHandlesHasNextBeforeIteration(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar']);
        self::assertTrue($cursor->hasNext());

        $this->assertCursorIteration($cursor);
    }

    public function testCount(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar'])->limit(1);

        self::assertSame(2, $cursor->count());
        self::assertSame(1, $cursor->count(true));
    }

    public function testCountCannotConnect(): void
    {
        $client = $this->getClient(['connect' => false], 'mongodb://localhost:28888');
        $cursor = $client->selectCollection('mongo-php-adapter', 'test')->find();

        $this->expectException(\MongoConnectionException::class);

        $cursor->count();
    }

    public function testCountAfterIteration(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar']);

        // Ensure the generator is consumed and thus closed
        iterator_to_array($cursor);
        self::assertSame(2, $cursor->count(true));
    }

    public function testNextStartsWithFirstItem(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar']);

        self::assertTrue($cursor->hasNext());
        $item = $cursor->getNext();
        self::assertNotNull($item);
        self::assertInstanceOf('MongoId', $item['_id']);
        self::assertSame('bar', $item['foo']);

        self::assertTrue($cursor->hasNext());
        $item = $cursor->getNext();
        self::assertNotNull($item);
        self::assertInstanceOf('MongoId', $item['_id']);
        self::assertSame('bar', $item['foo']);

        self::assertFalse($cursor->hasNext());
        $item = $cursor->getNext();
        self::assertNull($item);

        $cursor->reset();

        self::assertTrue($cursor->hasNext());
        $item = $cursor->getNext();
        self::assertNotNull($item);
        self::assertInstanceOf('MongoId', $item['_id']);
        self::assertSame('bar', $item['foo']);

        $item = $cursor->getNext();
        self::assertNotNull($item);
        self::assertInstanceOf('MongoId', $item['_id']);
        self::assertSame('bar', $item['foo']);
    }

    public function testIteratorInterface(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar']);

        self::assertFalse($cursor->valid(), 'Cursor should be invalid to start with');
        self::assertNull($cursor->current(), 'Cursor should be invalid to start with');
        self::assertNull($cursor->key(), 'Cursor should be invalid to start with');

        $cursor->next();
        self::assertTrue($cursor->valid(), 'Cursor should be valid');

        $item = $cursor->current();
        self::assertNotNull($item);
        self::assertInstanceOf('MongoId', $item['_id']);
        self::assertSame('bar', $item['foo']);

        $cursor->next();

        $item = $cursor->current();
        self::assertNotNull($item);
        self::assertInstanceOf('MongoId', $item['_id']);
        self::assertSame('bar', $item['foo']);

        $cursor->next();

        self::assertNull($cursor->current(), 'Cursor should return null at the end');
        self::assertFalse($cursor->valid(), 'Cursor should be invalid');

        $cursor->rewind();

        $item = $cursor->current();
        self::assertNotNull($item);
        self::assertInstanceOf('MongoId', $item['_id']);
        self::assertSame('bar', $item['foo']);
    }

    /**
     * @dataProvider provideCursorAppliesOptionsCases
     */
    public function testCursorAppliesOptions($checkOptionCallback, ?\Closure $applyOptionCallback = null): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));

        $query = ['foo' => 'bar'];
        $projection = ['_id' => false, 'foo' => true];

        $collectionMock = $this->getCollectionMock();
        $collectionMock
            ->expects(self::once())
            ->method('find')
            ->with(self::equalTo(TypeConverter::fromLegacy($query)), self::callback($checkOptionCallback))
            ->willReturn(new \ArrayIterator([]));

        $collection = $this->getCollection('test');
        $cursor = $collection->find($query, $projection);

        // Replace the original MongoDB collection with our mock
        $reflectionProperty = new \ReflectionProperty($cursor, 'collection');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($cursor, $collectionMock);

        if ($applyOptionCallback !== null) {
            $applyOptionCallback($cursor);
        }

        // Force query by converting to array
        iterator_to_array($cursor);
    }

    public function testCursorInfo(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar'], ['_id' => false])->skip(1)->limit(3);

        $expected = [
            'ns' => 'mongo-php-adapter.test',
            'limit' => 3,
            'batchSize' => 0,
            'skip' => 1,
            'flags' => 0,
            'query' => ['foo' => 'bar'],
            'fields' => ['_id' => false],
            'started_iterating' => false,
        ];

        self::assertSame($expected, $cursor->info());

        // Ensure cursor started iterating
        iterator_to_array($cursor);

        $expected['started_iterating'] = true;
        $expected += [
            'id' => 0,
            'at' => 1,
            'numReturned' => 1,
            'server' => $this->getCurrentHost() . ':27017;-;.;' . getmypid(),
            'host' => $this->getCurrentHost(),
            'port' => 27017,
            'connection_type_desc' => 'STANDALONE',
        ];

        self::assertSame($expected, $cursor->info());
    }

    public function testCursorInfoWithBatchSize(): void
    {
        $this->prepareData();
        $host = $this->getCurrentHost();
        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar'], ['_id' => false])->skip(1)->limit(3);
        $cursor->batchSize(1);

        $expected = [
            'ns' => 'mongo-php-adapter.test',
            'limit' => 3,
            'batchSize' => 1,
            'skip' => 1,
            'flags' => 0,
            'query' => ['foo' => 'bar'],
            'fields' => ['_id' => false],
            'started_iterating' => false,
        ];

        self::assertSame($expected, $cursor->info());

        // Ensure cursor started iterating
        iterator_to_array($cursor);

        $expected['started_iterating'] = true;
        $expected += [
            'id' => 0,
            'at' => 1,
            'numReturned' => 1,
            'server' => "{$host}:27017;-;.;" . getmypid(),
            'host' => $host,
            'port' => 27017,
            'connection_type_desc' => 'STANDALONE',
        ];

        self::assertSame($expected, $cursor->info());
    }

    public function testReadPreferenceIsInherited(): void
    {
        $collection = $this->getCollection();
        $collection->setReadPreference(\MongoClient::RP_SECONDARY, [['a' => 'b']]);

        $cursor = $collection->find(['foo' => 'bar']);
        self::assertSame(['type' => \MongoClient::RP_SECONDARY, 'tagsets' => [['a' => 'b']]], $cursor->getReadPreference());
    }

    public function testExplain(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar'], ['_id' => false])->skip(1)->limit(3);

        $expected = [
            'queryPlanner' => [
                'plannerVersion' => 1,
                'namespace' => 'mongo-php-adapter.test',
                'indexFilterSet' => false,
                'parsedQuery' => [
                    'foo' => ['$eq' => 'bar'],
                ],
                'winningPlan' => ['$$exists' => true],
                'rejectedPlans' => ['$$exists' => true],
            ],
            'executionStats' => [
                'executionSuccess' => true,
                'nReturned' => 1,
                'totalKeysExamined' => 0,
                'totalDocsExamined' => 3,
                'executionStages' => ['$$exists' => true],
                'allPlansExecution' => ['$$exists' => true],
            ],
            'serverInfo' => [
                'port' => 27017,
            ],
        ];

        $this->assertMatches($expected, $cursor->explain());
    }

    public function testExplainWithEmptyProjection(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => 'bar']);

        $expected = [
            'queryPlanner' => [
                'plannerVersion' => 1,
                'namespace' => 'mongo-php-adapter.test',
                'indexFilterSet' => false,
                'parsedQuery' => [
                    'foo' => ['$eq' => 'bar'],
                ],
                'winningPlan' => ['$$exists' => true],
                'rejectedPlans' => ['$$exists' => true],
            ],
            'executionStats' => [
                'executionSuccess' => true,
                'nReturned' => 2,
                'totalKeysExamined' => 0,
                'totalDocsExamined' => 3,
                'executionStages' => ['$$exists' => true],
                'allPlansExecution' => ['$$exists' => true],
            ],
            'serverInfo' => [
                'port' => 27017,
            ],
        ];

        $this->assertMatches($expected, $cursor->explain());
    }

    public function testExplainConvertsQuery(): void
    {
        $this->prepareData();

        $collection = $this->getCollection();
        $cursor = $collection->find(['foo' => new \MongoRegex('/^b/')]);

        $expected = [
            'queryPlanner' => [
                'plannerVersion' => 1,
                'namespace' => 'mongo-php-adapter.test',
                'indexFilterSet' => false,
                'winningPlan' => ['$$exists' => true],
                'rejectedPlans' => ['$$exists' => true],
            ],
            'executionStats' => [
                'executionSuccess' => true,
                'nReturned' => 2,
                'totalKeysExamined' => 0,
                'totalDocsExamined' => 3,
                'executionStages' => ['$$exists' => true],
                'allPlansExecution' => ['$$exists' => true],
            ],
            'serverInfo' => [
                'port' => 27017,
            ],
        ];

        $this->assertMatches($expected, $cursor->explain());
    }

    public function testInterfaces(): void
    {
        $collection = $this->getCollection();
        $cursor = $collection->find();

        self::assertInstanceOf(\MongoCursorInterface::class, $cursor);

        // The countable interface is necessary for compatibility with PHP 7.3+, but not implemented by MongoCursor
        if (!\extension_loaded('mongo')) {
            self::assertInstanceOf(\Countable::class, $cursor);
        }
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getCollectionMock()
    {
        return $this->createMock('MongoDB\Collection', [], [], '', false);
    }

    private function assertCursorIteration($cursor): void
    {
        $iterated = 0;
        foreach ($cursor as $key => $item) {
            self::assertSame($iterated, $cursor->info()['at']);
            self::assertInstanceOf('MongoId', $item['_id']);
            self::assertEquals($key, (string) $item['_id']);
            self::assertSame('bar', $item['foo']);
            ++$iterated;
        }

        self::assertSame(2, $iterated);
    }
}
