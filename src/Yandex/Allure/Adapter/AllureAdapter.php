<?php

namespace Yandex\Allure\Adapter;

use Codeception\Configuration;
use Codeception\Event\StepEvent;
use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Platform\Extension;
use Yandex\Allure\Adapter\Annotation;
use Yandex\Allure\Adapter\Event\StepFailedEvent;
use Yandex\Allure\Adapter\Event\StepFinishedEvent;
use Yandex\Allure\Adapter\Event\StepStartedEvent;
use Yandex\Allure\Adapter\Event\TestCaseBrokenEvent;
use Yandex\Allure\Adapter\Event\TestCaseCanceledEvent;
use Yandex\Allure\Adapter\Event\TestCaseFailedEvent;
use Yandex\Allure\Adapter\Event\TestCaseFinishedEvent;
use Yandex\Allure\Adapter\Event\TestCasePendingEvent;
use Yandex\Allure\Adapter\Event\TestCaseStartedEvent;
use Yandex\Allure\Adapter\Event\TestSuiteFinishedEvent;
use Yandex\Allure\Adapter\Event\TestSuiteStartedEvent;
use Yandex\Allure\Adapter\Model;

const OUTPUT_DIRECTORY_PARAMETER = 'outputDirectory';
const DELETE_PREVIOUS_RESULTS_PARAMETER = 'deletePreviousResults';
const DEFAULT_RESULTS_DIRECTORY = 'allure-results';

class AllureAdapter extends Extension
{

    //NOTE: here we implicitly assume that PHP runs in single-threaded mode
    private $uuid;

    /**
     * @var Allure
     */
    private $lifecycle;

    static $events = [
        Events::SUITE_BEFORE => 'suiteBefore',
        Events::SUITE_AFTER => 'suiteAfter',
        Events::TEST_START => 'testStart',
        Events::TEST_FAIL => 'testFail',
        Events::TEST_ERROR => 'testError',
        Events::TEST_INCOMPLETE => 'testIncomplete',
        Events::TEST_SKIPPED => 'testSkipped',
        Events::TEST_END => 'testEnd',
        Events::STEP_BEFORE => 'stepBefore',
        Events::STEP_FAIL => 'stepFail',
        Events::STEP_AFTER => 'stepAfter'
    ];

    public function _initialize()
    {
        parent::_initialize();
        Annotation\AnnotationProvider::registerAnnotationNamespaces();
        $outputDirectory = (isset($this->config[OUTPUT_DIRECTORY_PARAMETER])) ?
            $this->config[OUTPUT_DIRECTORY_PARAMETER] : self::getDefaultOutputDirectory();
        $deletePreviousResults = (isset($this->config[DELETE_PREVIOUS_RESULTS_PARAMETER])) ?
            $this->config[DELETE_PREVIOUS_RESULTS_PARAMETER] : false;
        if (!file_exists($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }
        if ($deletePreviousResults) {
            $files = glob($outputDirectory . DIRECTORY_SEPARATOR . '{,.}*', GLOB_BRACE);
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_null(Model\Provider::getOutputDirectory())) {
            Model\Provider::setOutputDirectory($outputDirectory);
        }
    }

    private static function getDefaultOutputDirectory()
    {
        // outputDir return value already contains directory separator
        return Configuration::outputDir() . DEFAULT_RESULTS_DIRECTORY;
    }
    
    public function suiteBefore(SuiteEvent $suiteEvent)
    {
        $suite = $suiteEvent->getSuite();
        $suiteName = $suite->getName();
        $event = new TestSuiteStartedEvent($suiteName);
        if (class_exists($suiteName)){
            $annotationManager = new Annotation\AnnotationManager(Annotation\AnnotationProvider::getClassAnnotations(get_class($suite)));
            $annotationManager->updateTestSuiteEvent($event);
        }
        $this->uuid = $event->getUuid();
        $this->getLifecycle()->fire($event);
    }

    public function suiteAfter()
    {
        $this->getLifecycle()->fire(new TestSuiteFinishedEvent($this->uuid));
    }

    public function testStart(TestEvent $testEvent)
    {
        $test = $testEvent->getTest();
        $testName = $test->getName();
        $className = get_class($test);
        $event = new TestCaseStartedEvent($this->uuid, $testName);
        if (method_exists($className, $testName)){
            $annotationManager = new Annotation\AnnotationManager(Annotation\AnnotationProvider::getMethodAnnotations($className, $testName));
            $annotationManager->updateTestCaseEvent($event);
        }
        $this->getLifecycle()->fire($event);
    }

    public function testError()
    {
        $this->getLifecycle()->fire(new TestCaseBrokenEvent());
    }

    public function testFail()
    {
        $this->getLifecycle()->fire(new TestCaseFailedEvent());
    }

    public function testIncomplete()
    {
        $this->getLifecycle()->fire(new TestCasePendingEvent());
    }

    public function testSkipped()
    {
        $this->getLifecycle()->fire(new TestCaseCanceledEvent());
    }

    public function testEnd()
    {
        $this->getLifecycle()->fire(new TestCaseFinishedEvent());
    }

    public function stepBefore(StepEvent $stepEvent)
    {
        $stepName = $stepEvent->getStep()->getName();
        $this->getLifecycle()->fire(new StepStartedEvent($stepName));
    }

    public function stepAfter()
    {
        $this->getLifecycle()->fire(new StepFinishedEvent());
    }

    public function stepFail()
    {
        $this->getLifecycle()->fire(new StepFailedEvent());
    }

    /**
     * @return Allure
     */
    public function getLifecycle()
    {
        if (!isset($this->lifecycle)){
            $this->lifecycle = Allure::lifecycle();
        }
        return $this->lifecycle;
    }

    public function setLifecycle(Allure $lifecycle)
    {
        $this->lifecycle = $lifecycle;
    }

}
