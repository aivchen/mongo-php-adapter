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

if (class_exists('Mongo', false)) {
    return;
}

/**
 * The connection point between MongoDB and PHP.
 * This class is used to initiate a connection and for database server commands.
 * @see http://www.php.net/manual/en/class.mongo.php
 * @deprecated This class has been DEPRECATED as of version 1.3.0.
 * Relying on this feature is highly discouraged. Please use MongoClient instead.
 * @see MongoClient
 */
class Mongo extends MongoClient
{
    /**
     * Dummy constructor to throw an exception.
     */
    public function __construct()
    {
        $this->notImplemented();
    }

    /**
     * Get pool size for connection pools.
     *
     * @see http://php.net/manual/en/mongo.getpoolsize.php
     * @return int returns the current pool size
     *
     * @deprecated This feature has been DEPRECATED as of version 1.2.3. Relying on this feature is highly discouraged. Please use MongoPool::getSize() instead.
     */
    public function getPoolSize()
    {
        $this->notImplemented();
    }

    /**
     * Returns the address being used by this for slaveOkay reads.
     *
     * @see http://php.net/manual/en/mongo.getslave.php
     * @return bool The address of the secondary this connection is using for
     * reads. This returns NULL if this is not connected to a replica set or not yet
     * initialized.
     */
    public function getSlave()
    {
        $this->notImplemented();
    }

    /**
     * Get slaveOkay setting for this connection.
     *
     * @see http://php.net/manual/en/mongo.getslaveokay.php
     * @return bool returns the value of slaveOkay for this instance
     */
    public function getSlaveOkay()
    {
        $this->notImplemented();
    }

    /**
     * Connects to paired database server.
     *
     * @see http://www.php.net/manual/en/mongo.pairconnect.php
     * @return bool
     * @throws MongoConnectionException
     *
     * @deprecated pass a string of the form "mongodb://server1,server2" to the constructor instead of using this method
     */
    public function pairConnect()
    {
        $this->notImplemented();
    }

    /**
     * Returns information about all connection pools.
     *
     * @see http://php.net/manual/en/mongo.pooldebug.php
     * @return array
     * @deprecated This feature has been DEPRECATED as of version 1.2.3. Relying on this feature is highly discouraged. Please use MongoPool::info() instead.
     */
    public function poolDebug()
    {
        $this->notImplemented();
    }

    /**
     * Change slaveOkay setting for this connection.
     *
     * @see http://php.net/manual/en/mongo.setslaveokay.php
     * @param bool $ok
     * @return bool returns the former value of slaveOkay for this instance
     */
    public function setSlaveOkay($ok)
    {
        $this->notImplemented();
    }

    /**
     * Set the size for future connection pools.
     *
     * @see http://php.net/manual/en/mongo.setpoolsize.php
     * @param $size <p>The max number of connections future pools will be able to create. Negative numbers mean that the pool will spawn an infinite number of connections.</p>
     * @return bool returns the former value of pool size
     * @deprecated Relying on this feature is highly discouraged. Please use MongoPool::setSize() instead.
     */
    public function setPoolSize($size)
    {
        $this->notImplemented();
    }

    /**
     * Creates a persistent connection with a database server.
     *
     * @see http://www.php.net/manual/en/mongo.persistconnect.php
     * @param string $username a username used to identify the connection
     * @param string $password a password used to identify the connection
     * @return bool if the connection was successful
     * @throws MongoConnectionException
     * @deprecated pass array("persist" => $id) to the constructor instead of using this method
     */
    public function persistConnect($username = '', $password = '')
    {
        $this->notImplemented();
    }

    /**
     * Creates a persistent connection with paired database servers.
     *
     * @see http://www.php.net/manual/en/mongo.pairpersistconnect.php
     * @param string $username a username used to identify the connection
     * @param string $password a password used to identify the connection
     * @return bool if the connection was successful
     * @throws MongoConnectionException
     * @deprecated pass "mongodb://server1,server2" and array("persist" => $id) to the constructor instead of using this method
     */
    public function pairPersistConnect($username = '', $password = '')
    {
        $this->notImplemented();
    }

    /**
     * Check if there was an error on the most recent db operation performed.
     *
     * @see http://www.php.net/manual/en/mongo.lasterror.php
     * @return array|null returns the error, if there was one, or NULL
     * @deprecated use MongoDB::lastError() instead
     */
    public function lastError()
    {
        $this->notImplemented();
    }

    /**
     * Checks for the last error thrown during a database operation.
     *
     * @see http://www.php.net/manual/en/mongo.preverror.php
     * @return array returns the error and the number of operations ago it occurred
     * @deprecated use MongoDB::prevError() instead
     */
    public function prevError()
    {
        $this->notImplemented();
    }

    /**
     * Clears any flagged errors on the connection.
     *
     * @see http://www.php.net/manual/en/mongo.reseterror.php
     * @return array returns the database response
     * @deprecated use MongoDB::resetError() instead
     */
    public function resetError()
    {
        $this->notImplemented();
    }

    /**
     * Choose a new secondary for slaveOkay reads.
     *
     * @see www.php.net/manual/en/mongo.switchslave.php
     * @return string The address of the secondary this connection is using for reads. This may be the same as the previous address as addresses are randomly chosen. It may return only one address if only one secondary (or only the primary) is available.
     * @throws MongoException (error code 15) if it is called on a non-replica-set connection. It will also throw MongoExceptions if it cannot find anyone (primary or secondary) to read from (error code 16).
     */
    public function switchSlave()
    {
        $this->notImplemented();
    }

    /**
     * Creates a database error on the database.
     *
     * @see http://www.php.net/manual/en/mongo.forceerror.php
     * @return bool the database response
     * @deprecated use MongoDB::forceError() instead
     */
    public function forceError()
    {
        $this->notImplemented();
    }

    /**
     * Connects with a database server.
     *
     * @see http://www.php.net/manual/en/mongo.connectutil.php
     * @return bool if the connection was successful
     * @throws MongoConnectionException
     */
    protected function connectUtil()
    {
        $this->notImplemented();
    }

    /**
     * @return never-return
     */
    protected function notImplemented(): void
    {
        throw new Exception('The Mongo class is deprecated and not supported through mongo-php-adapter');
    }
}
