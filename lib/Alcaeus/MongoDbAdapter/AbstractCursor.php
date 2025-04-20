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

namespace Alcaeus\MongoDbAdapter;

use Alcaeus\MongoDbAdapter\Helper\ReadPreference;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Server;

/**
 * @internal
 */
abstract class AbstractCursor
{
    use ReadPreference;

    /**
     * @var int|null
     */
    protected $batchSize;

    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var \MongoClient
     */
    protected $connection;

    /**
     * @var Cursor
     */
    protected $cursor;

    /**
     * @var Database
     */
    protected $db;

    /**
     * @var CursorIterator
     */
    protected $iterator;

    /**
     * @var string
     */
    protected $ns;

    /**
     * @var bool
     */
    protected $startedIterating = false;

    /**
     * @var bool
     */
    protected $cursorNeedsAdvancing = true;

    /**
     * @var int
     */
    protected $position = 0;

    /** @var list<string> */
    protected array $optionNames = [
        'batchSize',
        'readPreference',
    ];

    private $current;

    private $key;

    private $valid = false;

    /**
     * Create a new cursor.
     * @see http://www.php.net/manual/en/mongocursor.construct.php
     * @param \MongoClient $connection database connection
     * @param string $ns full name of database and collection
     */
    public function __construct(\MongoClient $connection, $ns)
    {
        $this->connection = $connection;
        $this->ns = $ns;

        $nsParts = explode('.', $ns);
        $dbName = array_shift($nsParts);
        $collectionName = implode('.', $nsParts);

        $this->db = $connection->selectDB($dbName)->getDb();

        if ($collectionName) {
            $this->collection = $connection->selectCollection($dbName, $collectionName)->getCollection();
        }
    }

    /**
     * Returns the current element.
     * @see http://www.php.net/manual/en/mongocursor.current.php
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->current;
    }

    /**
     * Returns the current result's _id.
     * @see http://www.php.net/manual/en/mongocursor.key.php
     * @return string the current result's _id as a string
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->key;
    }

    /**
     * Advances the cursor to the next result, and returns that result.
     * @see http://www.php.net/manual/en/mongocursor.next.php
     * @return array Returns the next object
     * @throws \MongoConnectionException
     * @throws \MongoCursorTimeoutException
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        if (!$this->startedIterating) {
            $this->ensureIterator();
            $this->startedIterating = true;
        } else {
            if ($this->cursorNeedsAdvancing) {
                $this->ensureIterator()->next();
            }

            $this->cursorNeedsAdvancing = true;
            ++$this->position;
        }

        return $this->storeIteratorState();
    }

    /**
     * Returns the cursor to the beginning of the result set.
     * @throws \MongoConnectionException
     * @throws \MongoCursorTimeoutException
     */
    #[\ReturnTypeWillChange]
    public function rewind(): void
    {
        // We can recreate the cursor to allow it to be rewound
        $this->reset();
        $this->startedIterating = true;
        $this->position = 0;
        $this->ensureIterator()->rewind();
        $this->storeIteratorState();
    }

    /**
     * Checks if the cursor is reading a valid result.
     * @see http://www.php.net/manual/en/mongocursor.valid.php
     * @return bool if the current result is not null
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->valid;
    }

    /**
     * Limits the number of elements returned in one batch.
     *
     * @see http://docs.php.net/manual/en/mongocursor.batchsize.php
     * @param int|null $batchSize The number of results to return per batch
     * @return $this returns this cursor
     */
    public function batchSize($batchSize)
    {
        $this->batchSize = $batchSize;

        return $this;
    }

    /**
     * Checks if there are documents that have not been sent yet from the database for this cursor.
     * @see http://www.php.net/manual/en/mongocursor.dead.php
     * @return bool returns if there are more results that have not been sent to the client, yet
     */
    public function dead()
    {
        return $this->ensureCursor()->isDead();
    }

    /**
     * @return array
     */
    public function info()
    {
        return $this->getCursorInfo() + $this->getIterationInfo();
    }

    /**
     * @see http://www.php.net/manual/en/mongocursor.setreadpreference.php
     * @param string $readPreference
     * @param array $tags
     * @return $this returns this cursor
     */
    public function setReadPreference($readPreference, $tags = null)
    {
        $this->setReadPreferenceFromParameters($readPreference, $tags);

        return $this;
    }

    /**
     * Sets a client-side timeout for this query.
     * @see http://www.php.net/manual/en/mongocursor.timeout.php
     * @param int $ms The number of milliseconds for the cursor to wait for a response. By default, the cursor will wait forever.
     * @return $this Returns this cursor
     */
    public function timeout($ms)
    {
        trigger_error('The ' . __METHOD__ . ' method is not implemented in mongo-php-adapter', E_USER_WARNING);

        return $this;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return ['batchSize', 'connection', 'iterator', 'ns', 'optionNames', 'position', 'startedIterating'];
    }

    /**
     * Applies all options set on the cursor, overwriting any options that have already been set.
     *
     * @param array $optionNames Array of option names to be applied (will be read from properties)
     * @return array
     */
    protected function getOptions($optionNames = null)
    {
        $options = [];

        if ($optionNames === null) {
            $optionNames = $this->optionNames;
        }

        foreach ($optionNames as $option) {
            $converter = 'convert' . ucfirst($option);
            $value = method_exists($this, $converter) ? $this->{$converter}() : $this->{$option};

            if ($value === null) {
                continue;
            }

            $options[$option] = $value;
        }

        return $options;
    }

    /**
     * @return \Iterator
     */
    protected function ensureIterator()
    {
        if ($this->iterator === null) {
            $this->iterator = $this->wrapTraversable($this->ensureCursor());
            $this->iterator->rewind();
        }

        return $this->iterator;
    }

    /**
     * @return CursorIterator
     */
    protected function wrapTraversable(\Traversable $traversable)
    {
        return new CursorIterator($traversable);
    }

    /**
     * @throws \MongoCursorException
     */
    protected function errorIfOpened(): void
    {
        if ($this->cursor === null) {
            return;
        }

        throw new \MongoCursorException('cannot modify cursor after beginning iteration.');
    }

    /**
     * @return array
     */
    protected function getIterationInfo()
    {
        $iterationInfo = [
            'started_iterating' => $this->cursor !== null,
        ];

        if ($this->cursor !== null) {
            switch ($this->cursor->getServer()->getType()) {
                case Server::TYPE_RS_ARBITER:
                    $typeString = 'ARBITER';
                    break;
                case Server::TYPE_MONGOS:
                    $typeString = 'MONGOS';
                    break;
                case Server::TYPE_RS_PRIMARY:
                    $typeString = 'PRIMARY';
                    break;
                case Server::TYPE_RS_SECONDARY:
                    $typeString = 'SECONDARY';
                    break;

                default:
                    $typeString = 'STANDALONE';
            }

            $cursorId = (string) $this->cursor->getId(true);
            $iterationInfo += [
                'id' => (int) $cursorId,
                'at' => $this->position,
                'numReturned' => $this->position, // This can't be obtained from the new cursor
                'server' => \sprintf('%s:%d;-;.;%d', $this->cursor->getServer()->getHost(), $this->cursor->getServer()->getPort(), getmypid()),
                'host' => $this->cursor->getServer()->getHost(),
                'port' => $this->cursor->getServer()->getPort(),
                'connection_type_desc' => $typeString,
            ];
        }

        return $iterationInfo;
    }

    /**
     * @return never-return
     */
    protected function notImplemented(): void
    {
        throw new \Exception('Not implemented');
    }

    /**
     * Clears the cursor.
     *
     * This is generic but implemented as protected since it's only exposed in MongoCursor
     */
    protected function reset(): void
    {
        $this->startedIterating = false;
        $this->cursorNeedsAdvancing = true;
        $this->cursor = null;
        $this->iterator = null;
        $this->storeIteratorState();
    }

    /**
     * Stores the current cursor element.
     *
     * This is necessary because hasNext() might advance the iterator but we still
     * need to be able to return the current object.
     */
    protected function storeIteratorState()
    {
        if (!$this->startedIterating) {
            $this->current = null;
            $this->key = null;
            $this->valid = false;

            return null;
        }

        $this->current = $this->ensureIterator()->current();
        $this->key = $this->ensureIterator()->key();
        $this->valid = $this->ensureIterator()->valid();

        if ($this->current !== null) {
            $this->current = TypeConverter::toLegacy($this->current);
        }

        return $this->current;
    }

    /**
     * @return Cursor
     */
    abstract protected function ensureCursor();

    /**
     * @return array
     */
    abstract protected function getCursorInfo();
}
