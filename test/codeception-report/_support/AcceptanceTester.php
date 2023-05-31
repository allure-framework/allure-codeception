<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Report;

use Behat\Gherkin\Node\TableNode;
use Codeception\Actor;
use Codeception\Test\Unit;

use function abs;
use function array_map;
use function iterator_to_array;

/**
 * Inherited Methods
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
*/
class AcceptanceTester extends Actor
{
    use _generated\AcceptanceTesterActions;

    /**
     * @var list<int>
     */
    private array $inputs = [];

    /**
     * @var list<int>
     */
    private array $outputs = [];

    /**
     * @Given I have input as :num
     */
    public function iHaveInputAs($num)
    {
        $this->inputs = [$num];
        $this->calculate();
    }

    private function calculate(): void
    {
        $this->outputs = array_map(
            fn (int $num): int => abs($num),
            $this->inputs,
        );
    }

    /**
     * @Then I should get output as :num
     */
    public function iShouldGetOutputAs($num)
    {
        Unit::assertSame([(int) $num], $this->outputs);
    }

    /**
     * @Given I have no input
     */
    public function iHaveNoInput()
    {
        $this->inputs = [];
        $this->calculate();
    }

    /**
     * @Given I have inputs
     */
    public function iHaveInputs(TableNode $table)
    {
        $this->inputs = array_map(
            fn (array $row): int => (int) $row['num'],
            iterator_to_array($table),
        );
        $this->calculate();
    }

    /**
     * @Then I should get non-negative outputs
     */
    public function iShouldGetNonNegativeOutputs()
    {
        foreach ($this->outputs as $num) {
            Unit::assertGreaterThanOrEqual(0, $num);
        }
    }
}
