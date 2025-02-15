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

if (class_exists('MongoCursor', false)) {
    return;
}

use Alcaeus\MongoDbAdapter\AbstractCursor;
use Alcaeus\MongoDbAdapter\CursorIterator;
use Alcaeus\MongoDbAdapter\ExceptionConverter;
use Alcaeus\MongoDbAdapter\TypeConverter;
use MongoDB\Driver\Cursor;
use MongoDB\Operation\Find;

/**
 * Result object for database query.
 * @see http://www.php.net/manual/en/class.mongocursor.php
 */
class MongoCursor extends AbstractCursor implements Iterator, Countable, MongoCursorInterface
{
    /**
     * @var bool
     */
    public static $slaveOkay = false;

    /**
     * @var int
     */
    public static $timeout = 30000;

    /**
     * @var array
     */
    protected $optionNames = [
        'allowPartialResults',
        'batchSize',
        'cursorType',
        'limit',
        'maxTimeMS',
        'modifiers',
        'noCursorTimeout',
        'projection',
        'readPreference',
        'skip',
        'sort',
    ];

    /**
     * @var array
     */
    protected $projection;

    /**
     * @var array
     */
    protected $query;

    protected $allowPartialResults;

    protected $awaitData;

    protected $flags = 0;

    protected $hint;

    protected $limit;

    protected $maxTimeMS;

    protected $noCursorTimeout;

    protected $options = [];

    protected $skip;

    protected $snapshot;

    protected $sort;

    protected $tailable;

    /**
     * Create a new cursor.
     * @see http://www.php.net/manual/en/mongocursor.construct.php
     * @param MongoClient $connection database connection
     * @param string $ns full name of database and collection
     * @param array $query database query
     * @param array $fields fields to return
     */
    public function __construct(MongoClient $connection, $ns, array $query = [], array $fields = [])
    {
        parent::__construct($connection, $ns);

        $this->query = $query;
        $this->projection = $fields;
    }

    /**
     * Adds a top-level key/value pair to a query.
     * @see http://www.php.net/manual/en/mongocursor.addoption.php
     * @param string $key fieldname to add
     * @param mixed $value value to add
     * @return MongoCursor Returns this cursor
     * @throws MongoCursorException
     */
    public function addOption($key, $value)
    {
        $this->errorIfOpened();
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * (PECL mongo &gt;= 1.2.11)<br/>
     * Sets whether this cursor will wait for a while for a tailable cursor to return more data.
     * @param bool $wait [optional] <p>If the cursor should wait for more data to become available.</p>
     * @return MongoCursor returns this cursor
     */
    public function awaitData($wait = true)
    {
        $this->errorIfOpened();
        $this->awaitData = $wait;

        return $this;
    }

    /**
     * Counts the number of results for this query.
     * @see http://www.php.net/manual/en/mongocursor.count.php
     * @param bool $foundOnly send cursor limit and skip information to the count function, if applicable
     * @return int the number of documents returned by this cursor's query
     */
    #[\ReturnTypeWillChange]
    public function count($foundOnly = false)
    {
        $optionNames = ['hint', 'maxTimeMS'];
        if ($foundOnly) {
            $optionNames = array_merge($optionNames, ['limit', 'skip']);
        }

        $options = $this->getOptions($optionNames) + $this->options;

        try {
            $count = $this->collection->count(TypeConverter::fromLegacy($this->query), $options);
        } catch (\MongoDB\Driver\Exception\ExecutionTimeoutException $e) {
            throw new MongoCursorTimeoutException($e->getMessage(), $e->getCode(), $e);
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            throw ExceptionConverter::toLegacy($e);
        }

        return $count;
    }

    /**
     * Return an explanation of the query, often useful for optimization and debugging.
     * @see http://www.php.net/manual/en/mongocursor.explain.php
     * @return array returns an explanation of the query
     */
    public function explain()
    {
        $optionNames = [
            'allowPartialResults',
            'batchSize',
            'cursorType',
            'limit',
            'maxTimeMS',
            'noCursorTimeout',
            'projection',
            'skip',
            'sort',
        ];

        $options = $this->getOptions($optionNames);

        $command = [
            'explain' => [
                'find' => $this->collection->getCollectionName(),
                'filter' => TypeConverter::fromLegacy($this->query),
            ] + $options,
        ];

        $explained = TypeConverter::toLegacy(iterator_to_array($this->db->command($command))[0]);
        unset($explained['ok']);

        return $explained;
    }

    /**
     * Sets the fields for a query.
     * @see http://www.php.net/manual/en/mongocursor.fields.php
     * @param array $f fields to return (or not return)
     * @return MongoCursor
     * @throws MongoCursorException
     */
    public function fields(array $f)
    {
        $this->errorIfOpened();
        $this->projection = $f;

        return $this;
    }

    /**
     * Advances the cursor to the next result, and returns that result.
     * @see http://www.php.net/manual/en/mongocursor.getnext.php
     * @return array Returns the next object
     * @throws MongoConnectionException
     * @throws MongoCursorTimeoutException
     */
    public function getNext()
    {
        return $this->next();
    }

    /**
     * Checks if there are any more elements in this cursor.
     * @see http://www.php.net/manual/en/mongocursor.hasnext.php
     * @return bool Returns true if there is another element
     * @throws MongoConnectionException
     * @throws MongoCursorTimeoutException
     */
    public function hasNext()
    {
        if (!$this->startedIterating) {
            $this->ensureIterator();
            $this->startedIterating = true;
            $this->storeIteratorState();
            $this->cursorNeedsAdvancing = false;
        } elseif ($this->cursorNeedsAdvancing) {
            $this->ensureIterator()->next();
            $this->cursorNeedsAdvancing = false;
        }

        return $this->ensureIterator()->valid();
    }

    /**
     * Gives the database a hint about the query.
     * @see http://www.php.net/manual/en/mongocursor.hint.php
     * @param array|string $keyPattern indexes to use for the query
     * @return MongoCursor Returns this cursor
     * @throws MongoCursorException
     */
    public function hint($keyPattern)
    {
        $this->errorIfOpened();
        $this->hint = $keyPattern;

        return $this;
    }

    /**
     * Sets whether this cursor will timeout.
     * @see http://www.php.net/manual/en/mongocursor.immortal.php
     * @param bool $liveForever if the cursor should be immortal
     * @return MongoCursor Returns this cursor
     * @throws MongoCursorException
     */
    public function immortal($liveForever = true)
    {
        $this->errorIfOpened();
        $this->noCursorTimeout = $liveForever;

        return $this;
    }

    /**
     * Limits the number of results returned.
     * @see http://www.php.net/manual/en/mongocursor.limit.php
     * @param int $num the number of results to return
     * @return MongoCursor Returns this cursor
     * @throws MongoCursorException
     */
    public function limit($num)
    {
        $this->errorIfOpened();
        $this->limit = $num;

        return $this;
    }

    /**
     * @param int $ms
     * @return $this
     * @throws MongoCursorException
     */
    public function maxTimeMS($ms)
    {
        $this->errorIfOpened();
        $this->maxTimeMS = $ms;

        return $this;
    }

    /**
     * @see http://www.php.net/manual/en/mongocursor.partial.php
     * @param bool $okay [optional] <p>If receiving partial results is okay.</p>
     * @return MongoCursor returns this cursor
     */
    public function partial($okay = true)
    {
        $this->allowPartialResults = $okay;

        return $this;
    }

    /**
     * Clears the cursor.
     * @see http://www.php.net/manual/en/mongocursor.reset.php
     */
    public function reset(): void
    {
        parent::reset();
    }

    /**
     * @see http://www.php.net/manual/en/mongocursor.setflag.php
     * @param int $flag
     * @param bool $set
     * @return MongoCursor
     */
    public function setFlag($flag, $set = true)
    {
        $this->notImplemented();
    }

    /**
     * Skips a number of results.
     * @see http://www.php.net/manual/en/mongocursor.skip.php
     * @param int $num the number of results to skip
     * @return MongoCursor Returns this cursor
     * @throws MongoCursorException
     */
    public function skip($num)
    {
        $this->errorIfOpened();
        $this->skip = $num;

        return $this;
    }

    /**
     * Sets whether this query can be done on a slave
     * This method will override the static class variable slaveOkay.
     * @see http://www.php.net/manual/en/mongocursor.slaveOkay.php
     * @param bool $okay if it is okay to query the slave
     * @return MongoCursor Returns this cursor
     * @throws MongoCursorException
     */
    public function slaveOkay($okay = true)
    {
        $this->errorIfOpened();

        $this->setReadPreferenceFromSlaveOkay($okay);

        return $this;
    }

    /**
     * Use snapshot mode for the query.
     * @see http://www.php.net/manual/en/mongocursor.snapshot.php
     * @return MongoCursor Returns this cursor
     * @throws MongoCursorException
     */
    public function snapshot()
    {
        $this->errorIfOpened();
        $this->snapshot = true;

        return $this;
    }

    /**
     * Sorts the results by given fields.
     * @see http://www.php.net/manual/en/mongocursor.sort.php
     * @param array $fields An array of fields by which to sort. Each element in the array has as key the field name, and as value either 1 for ascending sort, or -1 for descending sort
     * @return MongoCursor Returns the same cursor that this method was called on
     * @throws MongoCursorException
     */
    public function sort(array $fields)
    {
        $this->errorIfOpened();
        $this->sort = $fields;

        return $this;
    }

    /**
     * Sets whether this cursor will be left open after fetching the last results.
     * @see http://www.php.net/manual/en/mongocursor.tailable.php
     * @param bool $tail if the cursor should be tailable
     * @return MongoCursor Returns this cursor
     */
    public function tailable($tail = true)
    {
        $this->errorIfOpened();
        $this->tailable = $tail;

        return $this;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return [
            'allowPartialResults',
            'awaitData',
            'flags',
            'hint',
            'limit',
            'maxTimeMS',
            'noCursorTimeout',
            'optionNames',
            'options',
            'projection',
            'query',
            'skip',
            'snapshot',
            'sort',
            'tailable',
        ] + parent::__sleep();
    }

    /**
     * Execute the query.
     * @see http://www.php.net/manual/en/mongocursor.doquery.php
     * @throws MongoConnectionException if it cannot reach the database
     */
    protected function doQuery(): void
    {
        $options = $this->getOptions() + $this->options;

        try {
            $this->cursor = $this->collection->find(TypeConverter::fromLegacy($this->query), $options);
        } catch (\MongoDB\Driver\Exception\ExecutionTimeoutException $e) {
            throw new MongoCursorTimeoutException($e->getMessage(), $e->getCode(), $e);
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            throw ExceptionConverter::toLegacy($e);
        }
    }

    /**
     * @return int|null
     */
    protected function convertCursorType()
    {
        if (!$this->tailable) {
            return null;
        }

        return $this->awaitData ? Find::TAILABLE_AWAIT : Find::TAILABLE;
    }

    /**
     * @return array
     */
    protected function convertModifiers()
    {
        $modifiers = array_key_exists('modifiers', $this->options) ? $this->options['modifiers'] : [];

        foreach (['hint', 'snapshot'] as $modifier) {
            if ($this->{$modifier} === null) {
                continue;
            }

            $modifiers['$' . $modifier] = $this->{$modifier};
        }

        return $modifiers;
    }

    /**
     * @return array
     */
    protected function convertProjection()
    {
        return TypeConverter::convertProjection($this->projection);
    }

    /**
     * @return Cursor
     */
    protected function ensureCursor()
    {
        if ($this->cursor === null) {
            $this->doQuery();
        }

        return $this->cursor;
    }

    /**
     * @return CursorIterator
     */
    protected function wrapTraversable(Traversable $traversable)
    {
        return new CursorIterator($traversable, true);
    }

    /**
     * @return array
     */
    protected function getCursorInfo()
    {
        return [
            'ns' => $this->ns,
            'limit' => $this->limit,
            'batchSize' => (int) $this->batchSize,
            'skip' => $this->skip,
            'flags' => $this->flags,
            'query' => $this->query,
            'fields' => $this->projection,
        ];
    }
}
