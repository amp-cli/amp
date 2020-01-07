## NOTE: This file has only be used for local development shells.  It has
## not be tested for use as a deployment script.

{pkgs ? import <nixpkgs> {
    inherit system;
  },
  system ? builtins.currentSystem,
  noDev ? false,
  php ? pkgs.php72,
  phpPackages ? pkgs.php72Packages
  }:

let
  stdenv = pkgs.stdenv;

  makeLatestSrc = stdenv.mkDerivation rec {
    ## There must be a better way...
    name = "amp-src";

    src = ./src;
    bin = ./bin;
    scripts = ./scripts;
    composerJson = ./composer.json;
    composerLock = ./composer.lock;

    buildCommand = ''
      mkdir $out
      cp -r $src $out/src
      cp -r $bin $out/bin
      cp -r $scripts $out/scripts
      cp $composerJson $out/composer.json
      cp $composerLock $out/composer.lock
    '';
  };

in stdenv.mkDerivation rec {
    name = "amp";

    src = makeLatestSrc;

    #src = pkgs.fetchFromGitHub {
    #  owner = "amp-cli";
    #  repo = "amp";
    #  rev = "FIXME";
    #  sha256 = "FIXME";
    #};

    buildInputs = [ php phpPackages.composer ];
    builder = "${src}/scripts/nix-builder.sh";
    shellHook = ''
      PATH="$PWD/bin:$PWD/extern:$PATH"
      export PATH
    '';
}
