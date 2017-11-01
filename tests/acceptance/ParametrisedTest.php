<?php

use Yandex\Allure\Adapter\Annotation\Title;
use Yandex\Allure\Adapter\Annotation\Description;

/**
 * @Description("A parametrised test class description")
 * https://www.startutorial.com/articles/view/phpunit-beginner-part-2-data-provider
 */
class ParametrisedTest extends PHPUnit_Framework_TestCase
{

    public function addDataProvider() {
        return array(
            array(1,2,3),
            array(0,0,10),
            array(-1,-1,-2),
        );
    }

    /**
     * @dataProvider addDataProvider
     * @Title("My Parametrised Test Method Title")
     */
    public function testAdd($a, $b, $expected)
    {
        $result = $a + $b;
        $this->assertEquals($expected, $result);
    }

    /**
     * Free text description in annotation
     * @Title("My Not Parametrised Title")
     */
    public function testSubstruct()
    {
        $this->assertEquals(2, 5-3);
    }

    /**
     * @Title("My Not Parametrised Title")
     */
    public function testFailMultiplication()
    {
        $this->assertEquals(5, 2*2);
    }

}