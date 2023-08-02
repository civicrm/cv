# Release

## Pre-Releases

Pre-releases are automatically posted online after any update to the `master` branch.

* For the latest pre-release, download https://download.civicrm.org/cv/cv-EDGE.phar
* To find specific pre-releases, browse the logs from https://test.civicrm.org/view/Tools/job/Tool-Publish-cv/

## Release Archive

* For v0.3.47+...
    * Archival releases are available under `https://download.civicrm.org/cv/` with files:
        * `cv-X.Y.Z.phar`
        * `cv-X.Y.Z.phar.asc`
        * `cv-X.Y.Z.SHA256SUMS`
    * Archival releases are available under https://github.com/civicrm/cv/releases/
* For v0.3.16 - v0.3.46...
    * Archival releases are available under `https://download.civicrm.org/cv/` with files:
        * `cv.phar-vX.Y.Z`

## Final Release Process

Requirements:

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
