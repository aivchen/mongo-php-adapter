<?php

declare(strict_types=1);

namespace Alcaeus\MongoDbAdapter\Tests;

use Alcaeus\MongoDbAdapter\GlobalInc;

final class GlobalIncTest extends TestCase
{
    protected function tearDown(): void
    {
        GlobalInc::reset();
    }

    public function testItIncrementsAndResets(): void
    {
        self::assertSame(0, GlobalInc::next());
        self::assertSame(1, GlobalInc::next());
        GlobalInc::reset();
        self::assertSame(0, GlobalInc::next());
    }
}
