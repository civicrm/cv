/**
 * This shell is suitable for compiling civix.phar.... and not much else.
 *
 * Ex: `nix-shell --run ./scripts/build.sh`
 */

{ pkgs ? import <nixpkgs> {} }:

let

  pharnix = pkgs.callPackage (pkgs.fetchFromGitHub {
    owner = "totten";
    repo = "pharnix";
    rev = "v0.2.0";
    sha256 = "sha256-JCK4YMgxCxUPn88t164tPnxpDNZxUWPR4W9AExEMzEU=";
  }) {};

in
  pkgs.mkShell {
    nativeBuildInputs = pharnix.profiles.full ++ [
      pkgs.bash-completion
    ];
    shellHook = ''
      source ${pkgs.bash-completion}/etc/profile.d/bash_completion.sh
    '';
  }
