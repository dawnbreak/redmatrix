The folder tests/ contains resources for automated testing tools.

Here you will find PHPUnit, Behat, etc. files to test the the
functionaly of RedMatrix. Right now it only contains some basic
tests to see if this can help improve the quality of our project.

# Contents

* unit/           PHPUnit tests
These are unit test to test the smallest parts, like single functions.
It uses the tool PHPUnit https://phpunit.de/

* acceptance/     functional/acceptance testing
These are behavioral or so called functional/acceptance testing. They
are used to test business logic. They are written in Gherkin and use
the tool Behat http://behat.org/

# How to use?
You need the dev tools which are defined in the composer.json in the
require-dev configuration.
Run ```composer install --dev``` to install these.

