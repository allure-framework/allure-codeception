<?php
namespace Yandex\Allure\Codeception;

use Codeception\Codecept;
use Codeception\Configuration;
use Codeception\Extension;
use Codeception\Event\FailEvent;
use Codeception\Event\StepEvent;
use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Exception\ConfigurationException;
use Codeception\Test\Cept;
use Codeception\Test\Cest;
use Codeception\Test\Gherkin;
use Codeception\Util\Debug;
use Codeception\Util\Locator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Yandex\Allure\Adapter\Allure;
use Yandex\Allure\Adapter\Annotation;
use Yandex\Allure\Adapter\Annotation\Description;
use Yandex\Allure\Adapter\Annotation\Features;
use Yandex\Allure\Adapter\Annotation\Issues;
use Yandex\Allure\Adapter\Annotation\Stories;
use Yandex\Allure\Adapter\Annotation\Title;
use Yandex\Allure\Adapter\Event\AddAttachmentEvent;
use Yandex\Allure\Adapter\Event\AddParameterEvent;
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
use Yandex\Allure\Adapter\Model\Attachment;
use Yandex\Allure\Adapter\Model\Label;
use Yandex\Allure\Adapter\Model\LabelType;
use Yandex\Allure\Adapter\Model\ParameterKind;
use function GuzzleHttp\json_encode;

const ARGUMENTS_LENGTH = 'arguments_length';
const OUTPUT_DIRECTORY_PARAMETER = 'outputDirectory';
const DELETE_PREVIOUS_RESULTS_PARAMETER = 'deletePreviousResults';
const IGNORED_ANNOTATION_PARAMETER = 'ignoredAnnotations';
const DEFAULT_RESULTS_DIRECTORY = 'allure-results';
const DEFAULT_REPORT_DIRECTORY = 'allure-report';
const INITIALIZED_PARAMETER = '_initialized';

class AllureCodeception extends Extension
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
        Events::STEP_AFTER => 'stepAfter'
    ];

    /**
     * Annotations that should be ignored by the annotaions parser (especially PHPUnit annotations).
     * 
     * @var array
     */
    private $ignoredAnnotations = [
        'after', 'afterClass', 'backupGlobals', 'backupStaticAttributes', 'before', 'beforeClass',
        'codeCoverageIgnore', 'codeCoverageIgnoreStart', 'codeCoverageIgnoreEnd', 'covers',
        'coversDefaultClass', 'coversNothing', 'dataProvider', 'depends', 'expectedException',
        'expectedExceptionCode', 'expectedExceptionMessage', 'group', 'large', 'medium',
        'preserveGlobalState', 'requires', 'runTestsInSeparateProcesses', 'runInSeparateProcess',
        'small', 'test', 'testdox', 'ticket', 'uses',
    ];

    /**
     * Extra annotations to ignore in addition to standard PHPUnit annotations.
     * 
     * @param array $ignoredAnnotations
     */
    public function _initialize(array $ignoredAnnotations = [])
    {
        parent::_initialize();
        Annotation\AnnotationProvider::registerAnnotationNamespaces();
        // Add standard PHPUnit annotations
        Annotation\AnnotationProvider::addIgnoredAnnotations($this->ignoredAnnotations);
        // Add custom ignored annotations
        $ignoredAnnotations = $this->tryGetOption(IGNORED_ANNOTATION_PARAMETER, []);
        Annotation\AnnotationProvider::addIgnoredAnnotations($ignoredAnnotations);
        $outputDirectory = $this->getOutputDirectory();
        $deletePreviousResults =
            $this->tryGetOption(DELETE_PREVIOUS_RESULTS_PARAMETER, false);
        $this->prepareOutputDirectory($outputDirectory, $deletePreviousResults);
        if (is_null(Model\Provider::getOutputDirectory())) {
            Model\Provider::setOutputDirectory($outputDirectory);
        }
        $this->setOption(INITIALIZED_PARAMETER, true);
    }

    /**
     * Sets runtime option which will be live
     *
     * @param string $key
     * @param mixed $value
     */
    private function setOption($key, $value)
    {
        $config = [];
        $cursor = &$config;
        $path = ['extensions', 'config', get_class()];
        foreach ($path as $segment) {
            $cursor[$segment] = [];
            $cursor = &$cursor[$segment];
        }
        $cursor[$key] = $this->config[$key] = $value;
        Configuration::append($config);
    }

    /**
     * Retrieves option or returns default value.
     *
     * @param string $optionKey    Configuration option key.
     * @param mixed  $defaultValue Value to return in case option isn't set.
     *
     * @return mixed Option value.
     * @since 0.1.0
     */
    private function tryGetOption($optionKey, $defaultValue = null)
    {
        if (array_key_exists($optionKey, $this->config)) {
            return $this->config[$optionKey];
        } 
        return $defaultValue;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    /**
     * Retrieves option or dies.
     *
     * @param string $optionKey Configuration option key.
     *
     * @throws ConfigurationException Thrown if option can't be retrieved.
     *
     * @return mixed Option value.
     * @since 0.1.0
     */
    private function getOption($optionKey)
    {
        if (!array_key_exists($optionKey, $this->config)) {
            $template = '%s: Couldn\'t find required configuration option `%s`';
            $message = sprintf($template, __CLASS__, $optionKey);
            throw new ConfigurationException($message);
        }
        return $this->config[$optionKey];
    }

    /**
     * Returns output directory.
     *
     * @throws ConfigurationException Thrown if there is Codeception-wide
     *                                problem with output directory
     *                                configuration.
     *
     * @return string Absolute path to output directory.
     * @since 0.1.0
     */
    private function getOutputDirectory()
    {
        $outputDirectory = $this->tryGetOption(
            OUTPUT_DIRECTORY_PARAMETER,
            DEFAULT_RESULTS_DIRECTORY
        );
        $filesystem = new Filesystem;
        if (!$filesystem->isAbsolutePath($outputDirectory)) {
            $outputDirectory = Configuration::outputDir() . $outputDirectory;
        }
        return $outputDirectory;
    }

    /**
     * Creates output directory (if it hasn't been created yet) and cleans it
     * up (if corresponding argument has been set to true).
     *
     * @param string $outputDirectory
     * @param bool   $deletePreviousResults Whether to delete previous results
     *                                      or keep 'em.
     *
     * @since 0.1.0
     */
    private function prepareOutputDirectory(
        $outputDirectory,
        $deletePreviousResults = false
    ) {
        $filesystem = new Filesystem;
        $filesystem->mkdir($outputDirectory, 0775);
        $initialized = $this->tryGetOption(INITIALIZED_PARAMETER, false);
        if ($deletePreviousResults && !$initialized) {
            $finder = new Finder;
            $files = $finder->files()->in($outputDirectory)->name('*.xml');
            $filesystem->remove($files);
        }
    }

    public function suiteBefore(SuiteEvent $suiteEvent)
    {
        $suite = $suiteEvent->getSuite();
        $suiteName = $suite->getName();
        $event = new TestSuiteStartedEvent($suiteName);
        if (class_exists($suiteName, false)) {
            $annotationManager = new Annotation\AnnotationManager(
                Annotation\AnnotationProvider::getClassAnnotations($suiteName)
            );
            $annotationManager->updateTestSuiteEvent($event);
        }
        $this->uuid = $event->getUuid();
        $this->getLifecycle()->fire($event);
    }

    public function suiteAfter()
    {
        $this->getLifecycle()->fire(new TestSuiteFinishedEvent($this->uuid));
    }

    private $testInvocations = array();
    private function buildTestName($test) {
        $testName = $test->getName();
        if ($test instanceof Cest) {
            $testFullName = get_class($test->getTestClass()) . '::' . $testName;
            if(isset($this->testInvocations[$testFullName])) {
                $this->testInvocations[$testFullName]++;
            } else {
                $this->testInvocations[$testFullName] = 0;
            }
            $currentExample = $test->getMetadata()->getCurrent();
            if ($currentExample && isset($currentExample['example']) ) {
                $testName .= ' with data set #' . $this->testInvocations[$testFullName];
            }
        } else if($test instanceof Gherkin) {
            $testName = $test->getScenarioNode()->getTitle();
        }
        return $testName;
    }

    public function testStart(TestEvent $testEvent)
    {
        $test = $testEvent->getTest();
        $testName = $this->buildTestName($test);
        $event = new TestCaseStartedEvent($this->uuid, $testName);        
        if ($test instanceof Cest) {
            $methodName = $test->getName();
            $className = get_class($test->getTestClass());
            $event->setLabels(array_merge($event->getLabels(), [
                new Label("testMethod", $methodName),
                new Label("testClass", $className)
            ]));
            $annotations = [];
            if (class_exists($className, false)) {
                $annotations = array_merge($annotations, Annotation\AnnotationProvider::getClassAnnotations($className));
            }
            if (method_exists($className, $test->getName())){
                $annotations = array_merge($annotations, Annotation\AnnotationProvider::getMethodAnnotations($className, $test->getName()));
            }
            $annotationManager = new Annotation\AnnotationManager($annotations);
            $annotationManager->updateTestCaseEvent($event);
        } else if ($test instanceof Gherkin) {
            $featureTags = $test->getFeatureNode()->getTags();
            $scenarioTags = $test->getScenarioNode()->getTags();
            $event->setLabels(
                    array_map(
                            function ($a) {
                                return new Label($a, LabelType::FEATURE);
                            },
                            array_merge($featureTags, $scenarioTags)
                        )
                );
        } else if ($test instanceof Cept) {
            $annotations = $this->getCeptAnnotations($test);
            if (count($annotations) > 0) {
                $annotationManager = new Annotation\AnnotationManager($annotations);
                $annotationManager->updateTestCaseEvent($event);
            }
        } else if ($test instanceof \PHPUnit\Framework\TestCase) {
            $methodName = $this->methodName = $test->getName(false);
            $className = get_class($test);
            if (class_exists($className, false)) {
                $annotationManager = new Annotation\AnnotationManager(
                    Annotation\AnnotationProvider::getClassAnnotations($className)
                );
                $annotationManager->updateTestCaseEvent($event);
            }
            if (method_exists($test, $methodName)) {
                $annotationManager = new Annotation\AnnotationManager(
                    Annotation\AnnotationProvider::getMethodAnnotations(get_class($test), $methodName)
                );
                $annotationManager->updateTestCaseEvent($event);
            }
        }
        $this->getLifecycle()->fire($event);

        if ($test instanceof Cest) {
            $currentExample = $test->getMetadata()->getCurrent();
            if ($currentExample && isset($currentExample['example']) ) {
                foreach ($currentExample['example'] as $name => $param) {
                    $paramEvent = new AddParameterEvent(
                            $name, $this->stringifyArgument($param), ParameterKind::ARGUMENT);
                    $this->getLifecycle()->fire($paramEvent);
                }
            }
        } else if ($test instanceof \PHPUnit_Framework_TestCase) {
            if ($test->usesDataProvider()) {
                $method = new \ReflectionMethod(get_class($test), 'getProvidedData');
                $method->setAccessible(true);
                $testMethod = new \ReflectionMethod(get_class($test), $test->getName(false));
                $paramNames = $testMethod->getParameters();
                foreach ($method->invoke($test) as $key => $param) {
                    $paramName = array_shift($paramNames);
                    $paramEvent = new AddParameterEvent(
                            is_null($paramName)
                                ? $key
                                : $paramName->getName(),
                            $this->stringifyArgument($param),
                            ParameterKind::ARGUMENT);
                    $this->getLifecycle()->fire($paramEvent);
                }
            }
        }
    }

    /**
     * @param FailEvent $failEvent
     */
    public function testError(FailEvent $failEvent)
    {
        $event = new TestCaseBrokenEvent();
        $e = $failEvent->getFail();
        $message = $e->getMessage();
        $this->getLifecycle()->fire($event->withException($e)->withMessage($message));
    }

    /**
     * @param FailEvent $failEvent
     */
    public function testFail(FailEvent $failEvent)
    {
        $event = new TestCaseFailedEvent();
        $e = $failEvent->getFail();
        $message = $e->getMessage();
        $this->getLifecycle()->fire($event->withException($e)->withMessage($message));
    }

    /**
     * @param FailEvent $failEvent
     */
    public function testIncomplete(FailEvent $failEvent)
    {
        $event = new TestCasePendingEvent();
        $e = $failEvent->getFail();
        $message = $e->getMessage();
        $this->getLifecycle()->fire($event->withException($e)->withMessage($message));
    }

    /**
     * @param FailEvent $failEvent
     */
    public function testSkipped(FailEvent $failEvent)
    {
        $event = new TestCaseCanceledEvent();
        $e = $failEvent->getFail();
        $message = $e->getMessage();
        $this->getLifecycle()->fire($event->withException($e)->withMessage($message));
    }

    public function testEnd(TestEvent $testEvent)
    {
        // attachments supported since Codeception 3.0
        if (version_compare(Codecept::VERSION, '3.0.0') > -1 && $testEvent->getTest() instanceof Cest) {
            $artifacts = $testEvent->getTest()->getMetadata()->getReports();
            foreach ($artifacts as $name => $artifact) {
                Allure::lifecycle()->fire(new AddAttachmentEvent($artifact, $name, null));
            }
        } elseif (version_compare(Codecept::VERSION, '3.0.0') > -1 && $testEvent->getTest() instanceof Gherkin) {
            $artifacts = $testEvent->getTest()->getMetadata()->getReports();
            foreach ($artifacts as $name => $artifact) {
                Allure::lifecycle()->fire(new AddAttachmentEvent($artifact, $name, null));
            }
        }
        $this->getLifecycle()->fire(new TestCaseFinishedEvent());
    }

    public function stepBefore(StepEvent $stepEvent)
    {
        $argumentsLength = $this->tryGetOption(ARGUMENTS_LENGTH, 200);

        $stepAction = $stepEvent->getStep()->getHumanizedActionWithoutArguments();
        $stepArgs = $stepEvent->getStep()->getArgumentsAsString($argumentsLength);

        if (!trim($stepAction)) {
            $stepAction = $stepEvent->getStep()->getMetaStep()->getHumanizedActionWithoutArguments();
            $stepArgs = $stepEvent->getStep()->getMetaStep()->getArgumentsAsString($argumentsLength);
        }

        $stepName = $stepAction . ' ' . $stepArgs;

        $this->emptyStep = false;
        $this->getLifecycle()->fire(new StepStartedEvent($stepName));
}

    public function stepAfter(StepEvent $stepEvent)
    {
        if ($stepEvent->getStep()->hasFailed()) {
            $this->getLifecycle()->fire(new StepFailedEvent());
        }
        $this->getLifecycle()->fire(new StepFinishedEvent());
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

    /**
     *
     * @param \Codeception\TestInterface $test
     * @return array
     */
    private function getCeptAnnotations($test)
    {
        $tokens = token_get_all($test->getSourceCode());
        $comments = array();
        $annotations = [];
        foreach($tokens as $token) {
            if($token[0] == T_DOC_COMMENT || $token[0] == T_COMMENT) {
                $comments[] = $token[1];
            }
        }
        foreach($comments as $comment) {
            $lines = preg_split ('/$\R?^/m', $comment);
            foreach($lines as $line) {
                $output = [];
                if (preg_match('/\*\s\@(.*)\((.*)\)/', $line, $output) > 0) {
                    if ($output[1] == "Features") {
                        $feature = new Features();
                        $features = $this->splitAnnotationContent($output[2]);
                        foreach($features as $featureName) {
                            $feature->featureNames[] = $featureName;
                        }
                        $annotations[get_class($feature)] = $feature;
                    } else if ($output[1] == 'Title') {
                        $title = new Title();
                        $title_content = str_replace('"', '', $output[2]);
                        $title->value = $title_content;
                        $annotations[get_class($title)] = $title;
                    } else if ($output[1] == 'Description') {
                        $description = new Description();
                        $description_content = str_replace('"', '', $output[2]);
                        $description->value = $description_content;
                        $annotations[get_class($description)] = $description;
                    } else if ($output[1] == 'Stories') {
                        $stories = $this->splitAnnotationContent($output[2]);
                        $story = new Stories();
                        foreach($stories as $storyName) {
                            $story->stories[] = $storyName;
                        }
                        $annotations[get_class($story)] = $story;
                    } else if ($output[1] == 'Issues') {
                        $issues = $this->splitAnnotationContent($output[2]);
                        $issue = new Issues();
                        foreach($issues as $issueName) {
                            $issue->issueKeys[] = $issueName;
                        }
                        $annotations[get_class($issue)] = $issue;
                    } else {
                        Debug::debug("Tag not detected: ".$output[1]);
                    }
                }
            }
        }
        return $annotations;
    }

    /**
     *
     * @param string $string
     * @return array
     */
    private function splitAnnotationContent($string)
    {
        $parts = [];
        $detected = str_replace('{', '', $string);
        $detected = str_replace('}', '', $detected);
        $detected = str_replace('"', '', $detected);
        $parts = explode(',', $detected);
        if (count($parts) == 0 && count($detected) > 0) {
            $parts[] = $detected;
        }
        return $parts;
    }

    protected function stringifyArgument($argument)
    {
        if (is_string($argument)) {
            return '"' . strtr($argument, ["\n" => '\n', "\r" => '\r', "\t" => ' ']) . '"';
        } elseif (is_resource($argument)) {
            $argument = (string)$argument;
        } elseif (is_array($argument)) {
            foreach ($argument as $key => $value) {
                if (is_object($value)) {
                    $argument[$key] = $this->getClassName($value);
}
            }
        } elseif (is_object($argument)) {
            if (method_exists($argument, '__toString')) {
                $argument = (string)$argument;
            } elseif (get_class($argument) == 'Facebook\WebDriver\WebDriverBy') {
                $argument = Locator::humanReadableString($argument);
            } else {
                $argument = $this->getClassName($argument);
            }
        }

        return json_encode($argument, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function getClassName($argument)
    {
        if ($argument instanceof \Closure) {
            return 'Closure';
        } elseif ((isset($argument->__mocked))) {
            return $this->formatClassName($argument->__mocked);
        } else {
            return $this->formatClassName(get_class($argument));
        }
    }

    protected function formatClassName($classname)
    {
        return trim($classname, "\\");
    }
}
