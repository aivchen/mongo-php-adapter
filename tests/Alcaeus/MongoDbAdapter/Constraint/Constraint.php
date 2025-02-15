<?php

namespace Alcaeus\MongoDbAdapter\Tests\Constraint;

use PHPUnit\Framework\Constraint\Constraint as BaseConstraint;

if (\class_exists('PHPUnit_Framework_Constraint')) {
    abstract class Constraint extends \PHPUnit\Framework\Constraint\Constraint {}
} else {
    abstract class Constraint extends BaseConstraint {}
}
