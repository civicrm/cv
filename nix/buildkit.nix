{ pkgs ? import <nixpkgs> {} }:

## Get civicrm-buildkit from github.
## Based on "master" branch circa 2026-08-24 00:16 UTC
import (pkgs.fetchzip {
  url = "https://github.com/civicrm/civicrm-buildkit/archive/e64ebce2f0ae5d7c12da89735a22bf35f21f9462.tar.gz";
  sha256 = "08g69vgkl30vsjmf1jsifsyrri6c2khas743isbp7dffc2y1grv9";
})

## Get a local copy of civicrm-buildkit. (Useful for developing patches.)
# import ((builtins.getEnv "HOME") + "/buildkit/default.nix")
# import ((builtins.getEnv "HOME") + "/bknix/default.nix")
