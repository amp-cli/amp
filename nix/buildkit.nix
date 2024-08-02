{ pkgs ? import <nixpkgs> {} }:

## Get civicrm-buildkit from github.
## Based on "master" branch circa 2024-08-02 01:35 UTC
import (pkgs.fetchzip {
  url = "https://github.com/totten/civicrm-buildkit/archive/6ab787cdad7088dd798f3a11c201bd4080782a86.tar.gz";
  sha256 = "sha256-imu2c7gcN3tijjJ4hp/EJRdLKGmu9LtW3ExCLBvPXPI";
})

## Get a local copy of civicrm-buildkit. (Useful for developing patches.)
# import ((builtins.getEnv "HOME") + "/buildkit/default.nix")
# import ((builtins.getEnv "HOME") + "/bknix/default.nix")
