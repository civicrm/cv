cv
==

Examples
========

```bash
me@localhost$ cv find --json
me@localhost$ cv find --json --buildkit
me@localhost$ cv find --php
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

If you have previously installed [phpunit](http://phpunit.de/), then you can run the test suite. Something like:

```
$ composer create-project totten/civil
$ cd civil
$ phpunit
PHPUnit 3.7.10 by Sebastian Bergmann.

Configuration read from /home/me/src/civil/phpunit.xml.dist

.................................................

Time: 2 seconds, Memory: 6.50Mb

OK (49 tests, 121 assertions)
```
