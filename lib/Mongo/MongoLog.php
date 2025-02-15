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

if (class_exists('MongoLog', false)) {
    return;
}

class MongoLog
{
    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.none
     */
    public const NONE = 0;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.all
     */
    public const ALL = 31;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.warning
     */
    public const WARNING = 1;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.info
     */
    public const INFO = 2;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.fine
     */
    public const FINE = 4;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.rs
     */
    public const RS = 1;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.pool
     */
    public const POOL = 1;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.con
     */
    public const CON = 2;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.io
     */
    public const IO = 4;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.server
     */
    public const SERVER = 8;

    /**
     * @see http://php.net/manual/en/class.mongolog.php#mongolog.constants.parse
     */
    public const PARSE = 16;

    private static $callback;

    private static $level;

    private static $module;

    /**
     * (PECL mongo &gt;= 1.3.0)
     * Gets the previously set callback function.
     *
     * @return callable|null
     */
    public static function getCallback()
    {
        return self::$callback;
    }

    /**
     * (PECL mongo &gt;= 1.3.0)<br/>
     * <p>
     * This function will set a callback function to be called for {@link http://www.php.net/manual/en/class.mongolog.php MongoLog} events
     * instead of triggering warnings.
     * </p>.
     * @see http://www.php.net/manual/en/mongolog.setcallback.php
     * @param callable $log_function   <p>
     * The function to be called on events.
     * </p>
     * <p>
     * The function should have the following prototype
     * </p>
     *
     * <em>log_function</em> ( <em>int</em> <em>$module</em> , <em>int</em> <em>$level</em>, <em>string</em> <em>$message</em>)
     * <ul>
     * <li>
     * <b><i>module</i></b>
     *
     * <p>One of the {@link http://www.php.net/manual/en/class.mongolog.php#mongolog.constants.module MongoLog module constants}.</p>
     * </li>
     * <li>
     * <b><i>level</i></b>
     *
     * <p>One of the {@link http://www.php.net/manual/en/class.mongolog.php#mongolog.constants.level MongoLog level constants}.</p>
     * </li
     * <li>
     * <b><i>message</i></b>
     *
     * <p>The log message itself.</p></li>
     * <ul>
     * @return bool returns <b>TRUE</b> on success or <b>FALSE</b> on failure
     */
    public static function setCallback(callable $log_function)
    {
        self::$callback = $log_function;

        return true;
    }

    /**
     * This function can be used to set how verbose logging should be and the types of
     * activities that should be logged. Use the constants described in the MongoLog
     * section with bitwise operators to specify levels.
     *
     * @see http://php.net/manual/en/mongolog.setlevel.php
     * @static
     * @param int $level The levels you would like to log
     */
    public static function setLevel($level): void
    {
        self::$level = $level;
    }

    /**
     * This can be used to see the log level. Use the constants described in the
     * MongoLog section with bitwise operators to check the level.
     *
     * @see http://php.net/manual/en/mongolog.getlevel.php
     * @static
     * @return int Returns the current level
     */
    public static function getLevel()
    {
        return self::$level;
    }

    /**
     * This function can be used to set which parts of the driver's functionality
     * should be logged. Use the constants described in the MongoLog section with
     * bitwise operators to specify modules.
     *
     * @see http://php.net/manual/en/mongolog.setmodule.php
     * @static
     * @param int $module The module(s) you would like to log
     */
    public static function setModule($module): void
    {
        self::$module = $module;
    }

    /**
     * This function can be used to see which parts of the driver's functionality are
     * being logged. Use the constants described in the MongoLog section with bitwise
     * operators to check if specific modules are being logged.
     *
     * @see http://php.net/manual/en/mongolog.getmodule.php
     * @static
     * @return int Returns the modules currently being logged
     */
    public static function getModule()
    {
        return self::$module;
    }
}
