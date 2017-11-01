<?php

use PHPUnit\Framework\Assert;
use Yandex\Allure\Adapter\Annotation\Description;

class ExampleCest
{
    public function _before(AcceptanceTester $I)
    {
    }

    public function _after(AcceptanceTester $I)
    {
    }

    /**
     *
     * @Description("Free text description in annotation")
     * @param AcceptanceTester $I
     * @example {"example_name" : "example 1", "data": "String data for example 1"}
     * @example {"example_name" : "example 2", "data": "String data for example 2"}
     */
    public function tryToTestExamples(AcceptanceTester $I, Codeception\Example $example)
    {
        Assert::assertStringEndsWith($example['example_name'], $example['data'], "Assertion comment");
    }

    public function tryToTestNoExamples(AcceptanceTester $I)
    {
        Assert::assertTrue(TRUE, "True is always true");
    }

}
