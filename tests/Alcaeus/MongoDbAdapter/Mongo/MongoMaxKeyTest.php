<?php

namespace Alcaeus\MongoDbAdapter\Tests\Mongo;

use Alcaeus\MongoDbAdapter\Tests\TestCase;
use Alcaeus\MongoDbAdapter\TypeInterface;

/**
 * @author alcaeus <alcaeus@alcaeus.org>
 */
class MongoMaxKeyTest extends TestCase
{
    public function testConvert(): void
    {
        $maxKey = new \MongoMaxKey();
        $this->skipTestUnless($maxKey instanceof TypeInterface);
        self::assertInstanceOf('MongoDB\BSON\MaxKey', $maxKey->toBSONType());
    }
}
