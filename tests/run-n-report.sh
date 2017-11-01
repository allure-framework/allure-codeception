export XDEBUG_CONFIG="idekey=netbeans-xdebug"
vendor/bin/codecept run --config=tests/codeception.yml acceptance
allure generate -o tests/_output/allure-report -- tests/_output/allure-results/
allure report open --report-dir tests/_output/allure-report

