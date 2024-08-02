## These lists help generate every possible combination of {PHP}x{MySQL}, i.e.
##
##  - php73m57 php73m80 php73m84 php73r105 php73r106
##  - php74m57 php74m80 php74m84 php74r105 php74r106
##  - php80m57 php80m80 php80m84 php80r105 php80r106
##  - php81m57 php81m80 php81m84 php81r105 php81r106
##  - php82m57 php82m80 php82m84 php82r105 php82r106
##  - php83m57 php83m80 php83m84 php83r105 php83r106

{ pkgs ? import <nixpkgs> {} }:

let

  buildkit = (import ./buildkit.nix) { inherit pkgs; };
  dists = buildkit.profiles;
  attrsets = buildkit.pins.default.lib;

  ## Example: rekeyRecord foo bar {foo_1=100;foo_2=200}
  ## Output:              ======> {bar_1=100;bar_2=200}
  rekeyRecord = prefixOld: prefixNew: record:
    let
      rekey = key:
        if builtins.match "^${prefixOld}(.*)" key != null then
          "${prefixNew}${builtins.elemAt (builtins.match "^${prefixOld}(.*)" key) 0}"
        else
          key;
    in attrsets.mapAttrs' (k: v: { name=(rekey k); value=v; }) record;

  ## phpVersions = { php73=PKG, php80=PKG, ... }
  phpVersions = (attrsets.filterAttrs (name: value: builtins.match "php[0-9]+" name != null) buildkit.pkgs);

  ## mysqlVersions = { mysql57=PKG, mysql80=PKG, ... }
  mysqlVersions = (attrsets.filterAttrs (name: value: builtins.match "mysql[0-9]+" name != null) buildkit.pkgs);

  ## mariadbVersions = { mariadb105=PKG, mariadb106=PKG, ...}
  mariadbVersions = (attrsets.filterAttrs (name: value: builtins.match "mariadb[0-9]+" name != null) buildkit.pkgs);

  ## dbmsVersions = { m57=PKG, m80=PKG, r105=PKG, r106=PKG, ...}
  dbmsVersions = (rekeyRecord "mysql" "m" mysqlVersions) // (rekeyRecord "mariadb" "r" mariadbVersions);

  mkShell = name: myPackages: pkgs.mkShell {
    nativeBuildInputs = myPackages;
    shellHook = ''
      source ${pkgs.bash-completion}/etc/profile.d/bash_completion.sh
      PS1='\n\[\033[1;32m\][${name}:\w]\$\[\033[0m\] '
    '';
  };

  baseProfile = buildkit.profiles.base ++ [
    buildkit.pkgs.box
    buildkit.pkgs.composer
    buildkit.pkgs.phpunit8
    buildkit.pkgs.phpunit9
    pkgs.bash-completion
  ];

  combinations = builtins.foldl' (acc: phpVersion:
    builtins.foldl' (innerAcc: dbmsVersion:
      innerAcc // {
        "${phpVersion}${dbmsVersion}" = baseProfile ++ [ (phpVersions.${phpVersion}) (dbmsVersions.${dbmsVersion}) ];
      }
    ) acc (builtins.attrNames dbmsVersions)
  ) {} (builtins.attrNames phpVersions);

in attrsets.mapAttrs mkShell combinations
