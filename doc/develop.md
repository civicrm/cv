# Development

Build PHAR
=====

> __TIP__: For day-to-day development, you don't usually need to compile a PHAR. These
> steps are mostly useful if (a) you are specifically addressing an issue in the PHAR
> or (b) you are testing on a UF/CMS which is prone to dependency conflicts (such as D9/D10).

`cv.phar` is usually compiled inside a [nix](https://nixos.org/download.html) shell, i.e.

```
nix-shell --run ./scripts/build.sh
```

You may also compile it in another environment using [`git`](https://git-scm.com), [`composer`](https://getcomposer.org/), and [`box`](http://box-project.github.io/box2/).

```
$ git clone https://github.com/civicrm/cv
$ cd cv
$ composer install
$ box compile
```

The output will be `./bin/cv.phar`.

__Tips__

* To match exact versions of the toolchain, consult [shell.nix](../shell.nix) and the corresponding release of [buildkit `pkgs`](https://github.com/civicrm/civicrm-buildkit/blob/master/nix/pkgs/default.nix).
* `box` may require updating `php.ini`.

Unit-Tests (Standard)
=====================

To run the standard test suite, you will need to pick an existing CiviCRM
installation and put it in `CV_TEST_BUILD`, as in:

```
$ git clone https://github.com/civicrm/cv
$ cd cv
$ composer install
$ export CV_TEST_BUILD=/home/me/buildkit/build/dmaster/web/
$ phpunit7 --group std
PHPUnit 7.5.15 by Sebastian Bergmann and contributors.

...............................................................  63 / 118 ( 53%)
.......................................................         118 / 118 (100%)

Time: 3.13 minutes, Memory: 14.00 MB

OK (118 tests, 295 assertions)
```

> We generally choose an existing installation based on `civibuild`
> configuration like `dmaster`. The above example assumes that your
> build is located at `/home/me/buildkit/build/dmaster/`.


To be quite thorough, you may want to test against multiple builds (e.g.
with various CMS's and file structures).  Prepare these builds separately
and loop through them, e.g.

```
$ for CV_TEST_BUILD in /home/me/buildkit/build/{dmaster,wpmaster,bmaster} ; do export CV_TEST_BUILD; phpunit7 --group std; done
```

Unit-Tests (Installer)
======================

The `cv core:install` and `cv core:uninstall` commands have more stringent execution requirements, e.g.

* Each test-run needs to work with an empty build (which does not have a Civi database or settings file).
  It specifically calls `civibuild` and `amp` to create+destroy builds during execution.
* These commands, in turn, may add new vhosts and databases. This can require elevated privileges (`sudo`).
* These commands have more potential failure points (e.g. intermittent networking issues can disrupt
  the test). To monitor them, you should set `DEBUG=1`.
* There must be a copy of the `civicrm-setup` source tree.  At time of writing, this is not yet bundled with
  the main tarballs, but you can set `CV_SETUP_PATH` to point to your own copy.

Given these extra requirements, this test runs as a separate group.

A typical execution might look like:

```
$ env DEBUG=1 OFFLINE=1 CV_SETUP_PATH=$HOME/src/civicrm-setup phpunit7 --group installer
```

Release Process
===============

For the official releases, the process requires:

* Google Cloud CLI tools (with authentication and suitable permissions)
	<!-- gcloud cli has login command that should be sufficient -->
* Github CLI tools (with authentication and suitable permissions)
	<!-- you can create personal developer API key in github web UI -->
* GPG (with appropriate private key loadedd; e.g. `7A1E75CB`)
* Nix

Then, on a suitably configured host:

```bash
cd cv
git checkout master
git pull

## Open subshell with suitable versions of most tools
nix-shell

## Do a dry-run -- Preview what will happen
./scripts/releaser.php release <VERSION> --dry-run

## Perform the actual release
./scripts/releaser.php release <VERSION>
```
