# Release notes

## Requirements

* Google Cloud CLI tools (with authentication and suitable permissions) 
	<!-- gcloud cli has login command that should be sufficient -->
* Github CLI tools (with authentication and suitable permissions) 
	<!-- you can create personal developer API key in github web UI -->
* GPG (with appropriate private key loadedd; e.g. `7A1E75CB`)
* Nix

## Steps

On a suitably configured host:

```bash
cd cv
git pull

## Open subshell with suitable versions of most tools
nix-shell

## Do a dry-run -- Preview what will happen
./scripts/releaser.php release <VERSION> --dry-run

## Perform the actual release
./scripts/releaser.php release <VERSION>
```
