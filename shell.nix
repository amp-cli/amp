/**
 * Suggested shell for interactive development/testing.
 *
 * Other shells can be run with:
 *   nix-shell nix/shells.nix -A phpXXmXX
 */

{ pkgs ? import <nixpkgs> {} }:

let

  shells = (import ./nix/shells.nix) { inherit pkgs; };

in shells.php82m80
