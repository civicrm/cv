# Download

## Verify a download

Each official release provides three files:

* _Executable file_: `cv.phar` or `cv-X.Y.Z.phar`
* _GPG signature_: `cv.phar.asc` or `cv-X.Y.Z.phar.asc`
* _SHA-256 checksum_: `cv.SHA256SUMS` or `cv-X.Y.Z.SHA256SUMS`

For example, suppose you want to download the latest release, verify with GPG, and then install.

```bash
## Download
curl -LsS https://download.civicrm.org/cv/cv.phar -o cv.phar
curl -LsS https://download.civicrm.org/cv/cv.phar.asc -o cv.phar.asc

## Verify
gpg --keyserver hkps://keys.openpgp.org --recv-keys 61819CB662DA5FFF79183EF83801D1B07A1E75CB
gpg --verify cv.phar.asc cv.phar

## Install
chmod +x cv.phar
sudo mv cv.phar /usr/local/bin/cv
sudo chown root /usr/local/bin/cv
```

Similarly, suppose you want to download a specific release, verify with SHA-256, and then install.

```bash
## Download
curl -LsS https://download.civicrm.org/cv/cv-0.3.47.phar -o cv-0.3.47.phar
curl -LsS https://download.civicrm.org/cv/cv-0.3.47.SHA256SUMS -o cv-0.3.47.SHA256SUMS

## Verify
sha256sum -c < cv-0.3.47.SHA256SUMS

## Install
chmod +x cv-0.3.47.phar
sudo mv cv-0.3.47.phar /usr/local/bin/cv
sudo chown root /usr/local/bin/cv
```

## Download pre-releases

Pre-releases are automatically posted online on `download.civicrm.org` after any update to the `master` branch.

* For the latest pre-release, download https://download.civicrm.org/cv/cv-EDGE.phar
* To find specific pre-releases, browse the logs from https://test.civicrm.org/view/Tools/job/Tool-Publish-cv/

## Download historical releases

* For v0.3.47+...
    * Historical releases are available on Github under https://github.com/civicrm/cv/releases/
    * Historical releases are available on `download.civicrm.org` with this convention:
        * `https://download.civicrm.org/cv/cv-X.Y.Z.phar`
        * `https://download.civicrm.org/cv/cv-X.Y.Z.phar.asc`
        * `https://download.civicrm.org/cv/cv-X.Y.Z.SHA256SUMS`
* For v0.3.16 - v0.3.46...
    * Historical releases are available on `download.civicrm.org` with this convention:
        * `https://download.civicrm.org/cv/cv.phar-vX.Y.Z`
