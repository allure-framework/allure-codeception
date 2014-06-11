# Allure Codeception Adapter

This an official [Codeception](http://codeception.com) adapter for Allure Framework - a flexible, lightweight and multi-language framework for writing self-documenting tests.

## What is this for?
The main purpose of this adapter is to accumulate information about your tests and write it out to a set of XML files: one for each test class. Then you can use a standalone command line tool or a plugin for popular continuous integration systems to generate an HTML page showing your tests in a good form.

## Example project
Example project is located at: https://github.com/allure-framework/allure-codeception-example

## Usage
In order to use this adapter you need to add a new dependency to your **composer.json** file:
```
{
    "require": {
	    "php": ">=5.4.0",
	    "allure-framework/allure-codeception": "~1.0.0"
    }
}
```
To enable this adapter in Codeception tests simply enabled it in extensions section of **codeception.yml**:
```yaml
extensions:
    enabled: [Yandex\Allure\Adapter\AllureAdapter]
```

## Advanced features
See respective [PHPUnit](https://github.com/allure-framework/allure-phpunit#advanced-features) section.
