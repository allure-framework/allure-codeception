# Allure Codeception Adapter

[![Latest Stable Version](http://poser.pugx.org/allure-framework/allure-codeception/v)](https://packagist.org/packages/allure-framework/allure-codeception)
[![Build](https://github.com/allure-framework/allure-codeception/actions/workflows/build.yml/badge.svg)](https://github.com/allure-framework/allure-codeception/actions/workflows/build.yml)
[![Type Coverage](https://shepherd.dev/github/allure-framework/allure-codeception/coverage.svg)](https://shepherd.dev/github/allure-framework/allure-codeception)
[![Psalm Level](https://shepherd.dev/github/allure-framework/allure-codeception/level.svg)](https://shepherd.dev/github/allure-framework/allure-codeception)
[![Total Downloads](http://poser.pugx.org/allure-framework/allure-codeception/downloads)](https://packagist.org/packages/allure-framework/allure-codeception)
[![License](http://poser.pugx.org/allure-framework/allure-codeception/license)](https://packagist.org/packages/allure-framework/allure-codeception)

This is an official [Codeception](http://codeception.com) adapter for Allure Framework.

## What is this for?
The main purpose of this adapter is to accumulate information about your tests and write it out to a set of XML files: one for each test class. This adapter only generates XML files containing information about tests. See [wiki section](https://github.com/allure-framework/allure-core/wiki#generating-report) on how to generate report.

## Example project
Example project is located at: https://github.com/allure-examples/allure-codeception-example

## Installation and Usage
In order to use this adapter you need to add a new dependency to your **composer.json** file:
```
{
    "require": {
	    "php": "^8",
	    "allure-framework/allure-codeception": "^2"
    }
}
```
To enable this adapter in Codeception tests simply put it in "enabled" extensions section of **codeception.yml**:
```yaml
extensions:
    enabled:
        - Qameta\Allure\Codeception\AllureCodeception
    config:
        Qameta\Allure\Codeception\AllureCodeception:
            outputDirectory: allure-results
            linkTemplates:
                issue: https://example.org/issues/%s
            setipHook: My\SetupHook
```

`outputDirectory` is used to store Allure results and will be calculated
relatively to Codeception output directory (also known as `paths: log` in
codeception.yml) unless you specify an absolute path. You can traverse up using
`..` as usual. `outputDirectory` defaults to `allure-results`.

`linkTemplates` is used to process links and generate URLs for them. You can put
here an `sprintf()`-like template or a name of class to be constructed; such class
must implement `Qameta\Allure\Setup\LinkTemplateInterface`.

`setupHook` allows to execute some bootstrapping code during initialization. You can
put here a name of the class that implements magic `__invoke()` method - and that method
will be called. For example, it can be used to ignore unnecessary docblock annotations:

```php
<?php

namespace My;

use Doctrine\Common\Annotations\AnnotationReader;

class SetupHook
{
    public function __invoke(): void
    {
        AnnotationReader::addGlobalIgnoredName('annotationToIgnore');
    }
}
```

To generate report from your favourite terminal,
[install](https://github.com/allure-framework/allure-cli#installation)
allure-cli and run following command (assuming you're in project root and using
default configuration):

```bash
allure generate -o ./build/allure-report ./build/allure-results
```

Report will be generated in `build/allure-report`.

## Main features
See respective [PHPUnit](https://github.com/allure-framework/allure-phpunit#advanced-features) section.
