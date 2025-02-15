<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoClientTest extends TestCase
{
    public static function provideReadPreferenceOptionsAreInheritedCases(): iterable
    {
        $options = [
            'readPreference' => \MongoClient::RP_SECONDARY_PREFERRED,
            'readPreferenceTags' => 'a:b',
        ];

        $overriddenOptions = [
            'readPreference' => \MongoClient::RP_NEAREST,
            'readPreferenceTags' => 'c:d',
        ];

        $multipleTagsets = [
            'readPreference' => \MongoClient::RP_SECONDARY_PREFERRED,
            'readPreferenceTags' => 'a:b,c:d',
        ];

        return [
            'optionsArray' => [
                'options' => $options,
                'uri' => 'mongodb://localhost',
                'expectedTagsets' => [['a' => 'b']],
            ],
            'queryString' => [
                'options' => [],
                'uri' => 'mongodb://localhost/?' . self::makeOptionString($options),
                'expectedTagsets' => [['a' => 'b']],
            ],
            'multipleInQueryString' => [
                'options' => [],
                'uri' => 'mongodb://localhost/?' . self::makeOptionString($options) . '&readPreferenceTags=c:d',
                'expectedTagsets' => [['a' => 'b'], ['c' => 'd']],
            ],
            'overridden' => [
                'options' => $options,
                'uri' => 'mongodb://localhost/?' . self::makeOptionString($overriddenOptions),
                'expectedTagsets' => [['c' => 'd'], ['a' => 'b']],
            ],
            'multipleTagsetsOptions' => [
                'options' => $multipleTagsets,
                'uri' => 'mongodb://localhost',
                'expectedTagsets' => [['a' => 'b', 'c' => 'd']],
            ],
            'multipleTagsetsQueryString' => [
                'options' => null,
                'uri' => 'mongodb://localhost/?' . self::makeOptionString($multipleTagsets),
                'expectedTagsets' => [['a' => 'b', 'c' => 'd']],
            ],
        ];
    }

    public static function provideWriteConcernOptionsAreInheritedCases(): iterable
    {
        $options = [
            'w' => 'majority',
            'wTimeoutMs' => 666,
        ];

        $overriddenOptions = [
            'w' => '2',
            'wTimeoutMs' => 333,
        ];

        return [
            'optionsArray' => [
                'options' => $options,
                'uri' => 'mongodb://localhost',
            ],
            'queryString' => [
                'options' => [],
                'uri' => 'mongodb://localhost/?' . self::makeOptionString($options),
            ],
            'overridden' => [
                'options' => $options,
                'uri' => 'mongodb://localhost/?' . self::makeOptionString($overriddenOptions),
            ],
        ];
    }

    /**
     * @return string
     */
    private static function makeOptionString(array $options)
    {
        return implode('&', array_map(
            static fn($key, $value) => $key . '=' . $value,
            array_keys($options),
            array_values($options),
        ));
    }

    /**
     * @dataProvider provideConnectionUriCases
     */
    public function testConnectionUri($uri, $expected): void
    {
        $this->skipTestIf(\extension_loaded('mongo'));
        self::assertSame($expected, (string) (new \MongoClient($uri, ['connect' => false])));
    }

    public function provideConnectionUriCases(): iterable
    {
        yield ['default', \sprintf('mongodb://%s:%d', \MongoClient::DEFAULT_HOST, \MongoClient::DEFAULT_PORT)];
        yield ['localhost', 'mongodb://localhost'];
        yield ['mongodb://localhost', 'mongodb://localhost'];
    }

    public function testSerialize(): void
    {
        self::assertIsString(serialize($this->getClient()));
    }

    public function testGetDb(): void
    {
        $client = $this->getClient();
        $db = $client->selectDB('mongo-php-adapter');
        self::assertInstanceOf('\MongoDB', $db);
        self::assertSame('mongo-php-adapter', (string) $db);
    }

    public function testSelectDBWithEmptyName(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database name cannot be empty');

        $this->getClient()->selectDB('');
    }

    public function testSelectDBWithInvalidName(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database name contains invalid characters');

        $this->getClient()->selectDB('/');
    }

    public function testGetDbProperty(): void
    {
        $client = $this->getClient();
        $db = $client->{'mongo-php-adapter'};
        self::assertInstanceOf('\MongoDB', $db);
        self::assertSame('mongo-php-adapter', (string) $db);
    }

    public function testGetCollection(): void
    {
        $client = $this->getClient();
        $collection = $client->selectCollection('mongo-php-adapter', 'test');
        self::assertInstanceOf('MongoCollection', $collection);
        self::assertSame('mongo-php-adapter.test', (string) $collection);
    }

    public function testGetHosts(): void
    {
        $host = $this->getCurrentHost();
        $client = $this->getClient();
        $hosts = $client->getHosts();
        $this->assertMatches(
            [
                "{$host}:27017;-;.;" . getmypid() => [
                    'host' => $host,
                    'port' => 27017,
                    'health' => 1,
                    'state' => 0,
                ],
            ],
            $hosts,
        );
    }

    public function testGetHostsExceptionHandling(): void
    {
        $this->expectException(\MongoConnectionException::class);
        $this->expectErrorMessageMatches('/fake_host/');

        $client = $this->getClient(null, 'mongodb://fake_host');
        $client->getHosts();
    }

    public function testReadPreference(): void
    {
        $client = $this->getClient();
        self::assertSame(['type' => \MongoClient::RP_PRIMARY], $client->getReadPreference());

        self::assertTrue($client->setReadPreference(\MongoClient::RP_SECONDARY, [['a' => 'b']]));
        self::assertSame(['type' => \MongoClient::RP_SECONDARY, 'tagsets' => [['a' => 'b']]], $client->getReadPreference());
    }

    public function testWriteConcern(): void
    {
        $client = $this->getClient();

        self::assertTrue($client->setWriteConcern('majority', 100));
        self::assertSame(['w' => 'majority', 'wtimeout' => 100], $client->getWriteConcern());
    }

    public function testListDBs(): void
    {
        $document = ['foo' => 'bar'];
        $this->getCollection()->insert($document);
        $databases = $this->getClient()->listDBs();

        self::assertSame(1.0, $databases['ok']);
        self::assertArrayHasKey('totalSize', $databases);
        self::assertArrayHasKey('databases', $databases);

        foreach ($databases['databases'] as $database) {
            self::assertArrayHasKey('name', $database);
            self::assertArrayHasKey('empty', $database);
            self::assertArrayHasKey('sizeOnDisk', $database);

            if ($database['name'] == 'mongo-php-adapter') {
                self::assertFalse($database['empty']);

                return;
            }
        }

        self::fail('Could not find mongo-php-adapter database in list');
    }

    public function testNoPrefixUri(): void
    {
        $client = $this->getClient(null, 'localhost');
        self::assertNotNull($client);
    }

    /**
     * @dataProvider provideReadPreferenceOptionsAreInheritedCases
     */
    public function testReadPreferenceOptionsAreInherited($options, $uri, $expectedTagsets): void
    {
        $client = $this->getClient($options, $uri);
        $collection = $client->selectCollection('test', 'foo');

        self::assertSame(
            [
                'type' => \MongoClient::RP_SECONDARY_PREFERRED,
                'tagsets' => $expectedTagsets,
            ],
            $collection->getReadPreference(),
        );
    }

    /**
     * @dataProvider provideWriteConcernOptionsAreInheritedCases
     */
    public function testWriteConcernOptionsAreInherited($options, $uri): void
    {
        $client = $this->getClient($options, $uri);
        $collection = $client->selectCollection('test', 'foo');

        self::assertSame(['w' => 'majority', 'wtimeout' => 666], $collection->getWriteConcern());
    }

    public function testConnectWithUsernameAndPassword(): void
    {
        $this->expectException(\MongoConnectionException::class);
        $this->expectExceptionMessage('Authentication failed');

        $client = $this->getClient(['username' => 'alcaeus', 'password' => 'mySuperSecurePassword']);
        $collection = $client->selectCollection('test', 'foo');

        $document = ['foo' => 'bar'];

        $collection->insert($document);
    }

    public function testConnectWithUsernameAndPasswordInConnectionUrl(): void
    {
        $host = $this->getCurrentHost();
        $this->expectException(\MongoConnectionException::class);
        $this->expectExceptionMessage('Authentication failed');

        $client = $this->getClient([], "mongodb://alcaeus:mySuperSecurePassword@{$host}");
        $collection = $client->selectCollection('test', 'foo');

        $document = ['foo' => 'bar'];

        $collection->insert($document);
    }

    public function testConnectionUriOptionIntegerTypeCasting(): void
    {
        $client = new \MongoClient('mongodb://localhost/db?w=0&wtimeout=0', ['connect' => false]);

        self::assertSame(['w' => 0, 'wtimeout' => 0], $client->getWriteConcern());
    }
}
