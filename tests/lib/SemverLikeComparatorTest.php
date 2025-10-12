<?php


namespace lib;

use MavenRV\SemverLikeComparator;
use PHPUnit\Framework\TestCase;

class SemverLikeComparatorTest extends TestCase
{
    public function testCompare()
    {
        $array = ["1.1", "1.3.0", "1", "1-test.12", "1.1.0", "1.12", "1-test.1", "1-test", "1-test+2"];
        usort($array, fn ($a, $b) => SemverLikeComparator::compare($a, $b));

        $this->assertEquals(
            ["1-test+2", "1-test", "1-test.1", "1-test.12", "1", "1.1", "1.1.0", "1.3.0", "1.12"],
            $array
        );
    }
}
