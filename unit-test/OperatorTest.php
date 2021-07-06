<?php

require_once '../Class/class.phpsqlgoose.php';

use phpgoose\Model;
use PHPUnit\Framework\TestCase;

class OperatorTest extends TestCase
{

    /**
     * @dataProvider providerIsOperator
     */
    public function testIsOperator($a, $b) {
        $model = new Model();
    }

    public function providerIsOperator() {
        return [
            ['$not', true]
        ];
    }
}
