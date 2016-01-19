cv
==

Examples
========

```bash
me@localhost$ cv find
me@localhost$ cv find --buildkit
me@localhost$ cv scr /path/to/throwaway.php
me@localhost$ cv ev 'echo Civi::paths()->get("[civicrm.root]/.");'
```

Build
=====

```
php -dphar.readonly=0 `which box` build
```

Unit-Tests
==========

To run the test suite, you will need an existing CiviCRM installation,
preferrably based on buildkit. (Example: `/home/me/buildkit/build/dmaster/`)

```
$ composer create-project totten/cv
$ cd cv
$ export CV_TEST_BUILD=/home/me/buildkit/build/dmaster/
$ ./bin/phpunit
PHPUnit 3.7.10 by Sebastian Bergmann.

Configuration read from /home/me/src/cv/phpunit.xml.dist

.................................................

Time: 2 seconds, Memory: 6.50Mb

OK (49 tests, 121 assertions)
```
