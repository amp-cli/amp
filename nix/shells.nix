## Define a shell for every combination of {PHP}x{MySQL}, i.e.
##
##  - php73m57 php73m80 php73m84 php73r105 php73r106
##  - php74m57 php74m80 php74m84 php74r105 php74r106
##  - php80m57 php80m80 php80m84 php80r105 php80r106
##  - php81m57 php81m80 php81m84 php81r105 php81r106
##  - php82m57 php82m80 php82m84 php82r105 php82r106
##  - php83m57 php83m80 php83m84 php83r105 php83r106
##
## Usage Example:
##
##   nix-shell nix/shells.nix -A php83m80

{ pkgs ? import <nixpkgs> {} }:

let

  buildkit = (import ./buildkit.nix) { inherit pkgs; };
  dists = buildkit.profiles;
  attrsets = buildkit.pins.default.lib;

  mkShell = name: myPackages: pkgs.mkShell {
    nativeBuildInputs = myPackages;
    shellHook = ''
      source ${pkgs.bash-completion}/etc/profile.d/bash_completion.sh
      PS1='\n\[\033[1;32m\][${name}:\w]\$\[\033[0m\] '
    '';
  };

  combinations = (import ./profiles.nix) { inherit pkgs; };

in attrsets.mapAttrs mkShell combinations
