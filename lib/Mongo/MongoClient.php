<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if (class_exists('MongoClient', false)) {
    return;
}

use Alcaeus\MongoDbAdapter\ExceptionConverter;
use Alcaeus\MongoDbAdapter\Helper;
use MongoDB\Client;

/**
 * A connection between PHP and MongoDB. This class is used to create and manage connections
 * See MongoClient::__construct() and the section on connecting for more information about creating connections.
 * @see http://www.php.net/manual/en/class.mongoclient.php
 */
class MongoClient
{
    use Helper\ReadPreference;
    use Helper\WriteConcern;

    public const VERSION = '1.6.12';
    public const DEFAULT_HOST = 'localhost';
    public const DEFAULT_PORT = 27017;
    public const RP_PRIMARY = 'primary';
    public const RP_PRIMARY_PREFERRED = 'primaryPreferred';
    public const RP_SECONDARY = 'secondary';
    public const RP_SECONDARY_PREFERRED = 'secondaryPreferred';
    public const RP_NEAREST = 'nearest';

    /**
     * @var bool
     * @deprecated This will not properly work as the underlying driver connects lazily
     */
    public $connected = false;

    public $status;

    /**
     * @var string
     */
    protected $server;

    protected $persistent;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var \MongoDB\Driver\Manager
     */
    private $manager;

    /**
     * Creates a new database connection object.
     *
     * @see http://php.net/manual/en/mongo.construct.php
     * @param string $server the server name
     * @param array $options an array of options for the connection
     * @param array $driverOptions an array of options for the MongoDB driver
     * @throws MongoConnectionException
     */
    public function __construct($server = 'default', array $options = ['connect' => true], array $driverOptions = [])
    {
        if ($server === 'default') {
            $server = 'mongodb://' . self::DEFAULT_HOST . ':' . self::DEFAULT_PORT;
        }

        if (isset($options['readPreferenceTags'])) {
            $options['readPreferenceTags'] = [$this->getReadPreferenceTags($options['readPreferenceTags'])];
        }

        $this->applyConnectionOptions($server, $options);

        $this->server = $server;
        if (strpos($this->server, '://') === false) {
            $this->server = 'mongodb://' . $this->server;
        }
        $client = new Client($this->server, $options, $driverOptions + ['driver' => ['name' => 'mongo-php-adapter']]);
        $this->setClient($client);

        if (isset($options['connect']) && $options['connect']) {
            $this->connect();
        }
    }

    /**
     * @return self
     * @throws MongoConnectionException
     */
    public static function fromClient(Client $client)
    {
        $self = new self((string) $client);
        $self->setClient($client);

        return $self;
    }

    /**
     * Get connections.
     *
     * Returns an array of all open connections, and information about each of the servers
     *
     * @return array
     */
    public static function getConnections()
    {
        return [];
    }

    /**
     * Closes this database connection.
     *
     * @see http://www.php.net/manual/en/mongoclient.close.php
     * @param  bool|string $connection
     * @return bool if the connection was successfully closed
     */
    public function close($connection = null)
    {
        $this->connected = false;

        return false;
    }

    /**
     * Connects to a database server.
     *
     * @see http://www.php.net/manual/en/mongoclient.connect.php
     *
     * @return bool if the connection was successful
     * @throws MongoConnectionException
     */
    public function connect()
    {
        $this->connected = true;

        return true;
    }

    /**
     * Drops a database.
     *
     * @see http://www.php.net/manual/en/mongoclient.dropdb.php
     * @param mixed $db The database to drop. Can be a MongoDB object or the name of the database.
     * @return array the database response
     * @deprecated use MongoDB::drop() instead
     */
    public function dropDB($db)
    {
        return $this->selectDB($db)->drop();
    }

    /**
     * Gets a database.
     *
     * @see http://php.net/manual/en/mongoclient.get.php
     * @param string $dbname the database name
     * @return MongoDB the database name
     */
    public function __get($dbname)
    {
        return $this->selectDB($dbname);
    }

    /**
     * Gets the client for this object.
     *
     * @internal This part is not of the ext-mongo API and should not be used
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get hosts.
     *
     * This method is only useful with a connection to a replica set. It returns the status of all of the hosts in the
     * set. Without a replica set, it will just return an array with one element containing the host that you are
     * connected to.
     *
     * @return array
     */
    public function getHosts()
    {
        $this->forceConnect();

        $results = [];

        try {
            $servers = $this->manager->getServers();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            throw ExceptionConverter::toLegacy($e);
        }

        foreach ($servers as $server) {
            $key = sprintf('%s:%d;-;.;%d', $server->getHost(), $server->getPort(), getmypid());
            $info = $server->getInfo();

            switch ($server->getType()) {
                case \MongoDB\Driver\Server::TYPE_RS_PRIMARY:
                    $state = 1;
                    break;
                case \MongoDB\Driver\Server::TYPE_RS_SECONDARY:
                    $state = 2;
                    break;

                default:
                    $state = 0;
            }

            $results[$key] = [
                'host' => $server->getHost(),
                'port' => $server->getPort(),
                'health' => 1,
                'state' => $state,
                'ping' => $server->getLatency(),
                'lastPing' => null,
            ];
        }

        return $results;
    }

    /**
     * Kills a specific cursor on the server.
     *
     * @see http://www.php.net/manual/en/mongoclient.killcursor.php
     * @param string $server_hash the server hash that has the cursor
     * @param int|MongoInt64 $id the ID of the cursor to kill
     * @return bool
     */
    public function killCursor($server_hash, $id)
    {
        $this->notImplemented();
    }

    /**
     * Lists all of the databases available.
     *
     * @see http://php.net/manual/en/mongoclient.listdbs.php
     * @return array Returns an associative array containing three fields. The first field is databases, which in turn contains an array. Each element of the array is an associative array corresponding to a database, giving the database's name, size, and if it's empty. The other two fields are totalSize (in bytes) and ok, which is 1 if this method ran successfully.
     */
    public function listDBs()
    {
        try {
            $databaseInfoIterator = $this->client->listDatabases();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            throw ExceptionConverter::toLegacy($e);
        }

        $databases = [
            'databases' => [],
            'totalSize' => 0,
            'ok' => 1.0,
        ];

        foreach ($databaseInfoIterator as $databaseInfo) {
            $databases['databases'][] = [
                'name' => $databaseInfo->getName(),
                'empty' => $databaseInfo->isEmpty(),
                'sizeOnDisk' => $databaseInfo->getSizeOnDisk(),
            ];
            $databases['totalSize'] += $databaseInfo->getSizeOnDisk();
        }

        return $databases;
    }

    /**
     * Gets a database collection.
     *
     * @see http://www.php.net/manual/en/mongoclient.selectcollection.php
     * @param string $db the database name
     * @param string $collection the collection name
     * @return MongoCollection returns a new collection object
     * @throws Exception throws Exception if the database or collection name is invalid
     */
    public function selectCollection($db, $collection)
    {
        return new MongoCollection($this->selectDB($db), $collection);
    }

    /**
     * Gets a database.
     *
     * @see http://www.php.net/manual/en/mongo.selectdb.php
     * @param string $name the database name
     * @return MongoDB returns a new db object
     * @throws InvalidArgumentException
     */
    public function selectDB($name)
    {
        return new MongoDB($this, $name);
    }

    public function setReadPreference($readPreference, $tags = null)
    {
        return $this->setReadPreferenceFromParameters($readPreference, $tags);
    }

    public function setWriteConcern($wstring, $wtimeout = 0)
    {
        return $this->setWriteConcernFromParameters($wstring, $wtimeout);
    }

    /**
     * String representation of this connection.
     *
     * @see http://www.php.net/manual/en/mongoclient.tostring.php
     * @return string returns hostname and port for this connection
     */
    public function __toString()
    {
        return (string) $this->client;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return [
            'connected', 'status', 'server', 'persistent',
        ];
    }

    private function setClient(Client $client): void
    {
        $this->client = $client;
        $info = $client->__debugInfo();
        $this->manager = $info['manager'];
    }

    /**
     * Forces a connection by executing the ping command.
     */
    private function forceConnect(): void
    {
        try {
            $command = new \MongoDB\Driver\Command(['ping' => 1]);
            $this->manager->executeCommand('db', $command);
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            throw ExceptionConverter::toLegacy($e);
        }
    }

    private function notImplemented(): void
    {
        throw new \Exception('Not implemented');
    }

    /**
     * @return array
     */
    private function extractUrlOptions($server)
    {
        $queryOptions = parse_url($server, PHP_URL_QUERY);
        if (!$queryOptions) {
            return [];
        }

        $queryOptions = explode('&', $queryOptions);

        $options = [];
        foreach ($queryOptions as $option) {
            if (strpos($option, '=') === false) {
                continue;
            }

            $keyValue = explode('=', $option);
            if ($keyValue[0] === 'readPreferenceTags') {
                $options[$keyValue[0]][] = $this->getReadPreferenceTags($keyValue[1]);
            } elseif (ctype_digit($keyValue[1])) {
                $options[$keyValue[0]] = (int) $keyValue[1];
            } else {
                $options[$keyValue[0]] = $keyValue[1];
            }
        }

        return $options;
    }

    /**
     * @return array
     */
    private function getReadPreferenceTags($readPreferenceTagString)
    {
        $tagSets = [];
        foreach (explode(',', $readPreferenceTagString) as $index => $tagSet) {
            $tags = explode(':', $tagSet);
            $tagSets[$tags[0]] = $tags[1];
        }

        return $tagSets;
    }

    /**
     * @param string $server
     */
    private function applyConnectionOptions($server, array $options): void
    {
        $urlOptions = $this->extractUrlOptions($server);

        if (isset($urlOptions['wTimeout'])) {
            $urlOptions['wTimeoutMS'] = $urlOptions['wTimeout'];
            unset($urlOptions['wTimeout']);
        }

        if (isset($options['wTimeout'])) {
            $options['wTimeoutMS'] = $options['wTimeout'];
            unset($options['wTimeout']);
        }

        // Special handling for readPreferenceTags which are merged
        if (isset($options['readPreferenceTags'], $urlOptions['readPreferenceTags'])) {
            $options['readPreferenceTags'] = array_merge($urlOptions['readPreferenceTags'], $options['readPreferenceTags']);
            unset($urlOptions['readPreferenceTags']);
        }

        $urlOptions = array_merge($urlOptions, $options);

        if (isset($urlOptions['slaveOkay'])) {
            $this->setReadPreferenceFromSlaveOkay($urlOptions['slaveOkay']);
        } elseif (isset($urlOptions['readPreference']) || isset($urlOptions['readPreferenceTags'])) {
            $readPreference = $urlOptions['readPreference'] ?? null;
            $tags = $urlOptions['readPreferenceTags'] ?? null;
            $this->setReadPreferenceFromParameters($readPreference, $tags);
        }

        if (isset($urlOptions['w']) || isset($urlOptions['wTimeoutMs'])) {
            $writeConcern = (isset($urlOptions['w'])) ? $urlOptions['w'] : 1;
            $wTimeout = (isset($urlOptions['wTimeoutMs'])) ? $urlOptions['wTimeoutMs'] : null;
            $this->setWriteConcern($writeConcern, $wTimeout);
        }
    }
}
