## NOTE: This file has only be used for local development shells.  It has
## not be tested for use as a deployment script.

{pkgs ? import (fetchTarball { url = "https://github.com/nixos/nixpkgs/archive/7e9b0dff974c89e070da1ad85713ff3c20b0ca97.tar.gz"; sha256 = "1ckzhh24mgz6jd1xhfgx0i9mijk6xjqxwsshnvq789xsavrmsc36"; }) {
    inherit system;
  },
  system ? builtins.currentSystem,
  noDev ? false,
  php ? pkgs.php74,
  phpPackages ? pkgs.php74Packages
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
