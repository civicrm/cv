# Download

<!-- The instructions for `cv` and `civix` are nearly identical. Consider updating them in tandem. -->

`cv` is available as an executable binary (`cv.phar`) and as source-code (`cv.git`).  It may be deployed as a standalone (system-wide) tool, or it may be deployed as part of an
existing web-project. Below, we give a general download summary and several example procedures.

* [Download URLs for alternate versions](#urls)
* [Comparison of install options](#comparison)
* Install `cv` as a system-wide/standalone tool
    * [Install `cv.phar` (binary) as system-wide tool (Linux/BSD/macOS)](#phar-unix)
    * [Install `cv.git` (source) as standalone project (Linux/BSD/macOS)](#src-unix)
    * [Install `cv.git` (source) as standalone project (Windows)](#src-win)
* Install `cv` as a tool within another project
    * [Install `cv.phar` (binary) as project tool (composer)](#phar-composer)
    * [Install `cv.phar` (binary) as project tool (phive)](#phar-phive)
    * [Install `cv.git` (source) as project tool (composer)](#src-composer)

<a name="urls"></a>
## Download URLs for alternate versions

| Format            | Version(s)           | URLs |
| --                | --                   | --   |
| Executable binary | Latest               | PHAR: https://download.civicrm.org/cv/cv.phar<br/>GPG: https://download.civicrm.org/cv/cv.phar.asc<br/>SHA256: https://download.civicrm.org/cv/cv.SHA256SUMS |
|                   | Edge (*autobuild*)   | PHAR: https://download.civicrm.org/cv/cv-EDGE.phar<br/>Logs: https://test.civicrm.org/view/Tools/job/Tool-Publish-cv/ |
|                   | Historical           | PHAR: `https://download.civicrm.org/cv/cv-X.Y.Z.phar`<br/>GPG: `https://download.civicrm.org/cv/cv-X.Y.Z.phar.asc`<br/>SHA256: `https://download.civicrm.org/cv/cv-X.Y.Z.SHA256SUMS`<br/>Browse: https://github.com/civicrm/cv/releases/<br/><br/>(*Note: Prior to v0.3.47, binaries were not posted to Github.*) |
| Source code       | All versions         | Git: https://github.com/civicrm/cv |

<a name="comparison"></a>
## Comparison of install options

There are a few procedures for installing. Here are key differences:

* __Executable binary vs source code__:
    * __Executable binary (`cv.phar`)__: The PHAR executable is designed to be portable.  It is a single file which can be called in any supported environment.
    * __Source code (`cv.git`)__:  The source-code is used for developing and debugging `cv`.  It requires more tooling and lacks some of the portability
      features in the PHAR.  (However, if there is a compatibility bug, then the source-code may make it easier to work-around or diagnose.)
* __System-wide tool vs project tool__:
    * __System-wide tool__: Install one copy of `cv` on each workstation or server. (You may need to re-install for each additional workstation or server.)
    * __Project tool__: Include a copy of `cv` with each web-project that you develop. (You may need to re-install for each additional project or site.)

<a name="phar-unix"></a>
## Install `cv.phar` as system-wide tool (Linux/BSD/macOS)

Choose the appropriate download (eg `https://download.civicrm.org/cv/cv.phar`). You may place the file directly in `/usr/local/bin`:

```bash
sudo curl -LsS https://download.civicrm.org/cv/cv.phar -o /usr/local/bin/cv
sudo chmod +x /usr/local/bin/cv
```

That is the quickest procedure, but it does not defend against supply-chain attacks. For improved security, split the process into three steps:

1. Download a specific binary (such as `cv-X.Y.Z.phar`):

    ```bash
    curl -LsS https://download.civicrm.org/cv/cv-X.Y.Z.phar -o cv-X.Y.Z.phar
    ```

2. Verify the binary with GPG or SHA256:

    ```bash
    ## Verify with GPG
    curl -LsS https://download.civicrm.org/cv/cv-X.Y.Z.phar.asc -o cv-X.Y.Z.phar.asc
    gpg --keyserver hkps://keys.openpgp.org --recv-keys 61819CB662DA5FFF79183EF83801D1B07A1E75CB
    gpg --verify cv-X.Y.Z.phar.asc cv-X.Y.Z.phar

    ## Verify with SHA256
    curl -LsS https://download.civicrm.org/cv/cv-X.Y.Z.SHA256SUMS -o cv-X.Y.Z.SHA256SUMS
    sha256sum -c < cv-X.Y.Z.SHA256SUMS
    ```

    (*GPG provides stronger verification. However, in some build-systems, it is easier and sufficient to integrate SHA256 checksums.*)

3. Finally, install the binary:

    ```bash
    chmod +x cv-X.Y.Z.phar
    sudo mv cv-X.Y.Z.phar /usr/local/bin/cv
    sudo chown root:root /usr/local/bin/cv   # For most Linux distributions
    sudo chown root:wheel /usr/local/bin/cv  # For most BSD/macOS distributions
    ```

<a name="phar-composer"></a>
## Install `cv.phar` as project tool (composer)

If you are developing a web-project with [`composer`](https://getcomposer.org) (e.g.  Drupal 8/9/10/11) and wish to add `cv.phar` to your project,
then use [civicrm/cli-tools](https://github.com/totten/civicrm-cli-tools).

```bash
composer require civicrm/cli-tools
```

The CLI tools will be placed in [composer's `vendor/bin` folder](https://getcomposer.org/doc/articles/vendor-binaries.md).

To enable easy/fluid CLI usage, add this folder to your `PATH`:

```bash
PATH="/path/to/vendor/bin:$PATH"
cv --version
```

(*Alternatively, if you prefer to pick a specific version of each tool, then use [composer-downloads-plugin](https://github.com/civicrm/composer-downloads-plugin)
to download the specific PHAR release.*)

<a name="phar-phive"></a>
## Install `cv.phar` as project tool (phive)

Like `composer`, [`phive`](https://phar.io/) allows you to add tools to a web-project. It has built-in support
for downloading, verifying, caching, and upgrading PHARs. Add `cv` to your project by running:

```bash
phive install civicrm/cv
```

By default, this will download the latest binary in `./tools/cv` and update the settings in `.phive/`.

<a name="src-unix"></a>
## Install `cv.git` as system-wide tool (Linux/BSD/macOS)

To download the source tree and all dependencies, use [`git`](https://git-scm.com) and [`composer`](https://getcomposer.org/).
For example, you might download to `$HOME/src/cv`:

```bash
git clone https://github.com/civicrm/cv $HOME/src/cv
cd $HOME/src/cv
composer install
./bin/cv --version
```

You may then add `$HOME/src/cv/bin` to your `PATH`. The command will be available in other folders:

```bash
export PATH="$HOME/src/cv/bin:$PATH"
cd /var/www/example.com/
cv --version
```

__TIP__: If your web-site uses Symfony components (as in D8/9/10), then you may see dependency-conflicts. You can resolve these by [building a custom PHAR](develop.md).

<a name="src-win"></a>
## Install `cv.git` as system-wide tool (Windows)

```
# Install composer
In a browser, visit http://getcomposer.org
Click on the download button.
Scroll down to Windows Installer and click on Composer-Setup.exe.
Choose Run when prompted.

# Install git
If you don't already have git, then in a browser visit http://git-scm.com/download/win.
Choose Run when prompted.
Leave all the defaults.

# Download cv
Decide where you want to install cv. You might want to put it in C:\Program Files, but you might get hassled about admin rights, in which case you can pick somewhere else, like C:\users\<your name>.
From the start menu choose All Programs -> Git -> Git Bash.
In the window that appears, type:
  cd "/c/users/<your name>"
  (note the forward slashes)
git clone git://github.com/civicrm/cv.git
exit

# Download dependencies
In windows explorer, navigate to C:\users\<your name> (or whereever you installed cv).
Shift-right-click on the cv folder.
Choose open command window here.
In the window that appears, type:
  composer install

# Add cv to the PATH
Either temporarily add it:
set PATH=%PATH%;C:\users\<your name>\cv\bin

OR permanently:
Start Menu -> Control Panel -> System -> Advanced -> Environment Variables
```

<a name="src-composer"></a>
## Install `cv.git` as project tool (composer)

If you have are developing a web-project with [`composer`](https://getcomposer.org) (e.g.  Drupal 8/9/10) and wish to add the `cv.git` source-code` to your project,
then use `composer require`:

```bash
cd /var/www/example.com
composer require civicrm/cv
```

By default, this will create the command `./vendor/bin/cv`.

__Warning__: If your web-project uses Symfony components (as in D8/9/10/11), then `composer` will attempt to reconcile the versions.  However, this is a
moving target, and the results may differ from the standard binaries.  It may occasionally require overrides or patches for compatibility.

__Tip__: You may have better experience with [installing `cv.phar` (binary) via composer)(#phar-composer).
