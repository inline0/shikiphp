{ pkgs ? import <nixpkgs> {} }:

let
  name = "demo";
  deps = with pkgs; [ git curl jq ];
in
pkgs.stdenv.mkDerivation {
  pname = name;
  version = "1.0.0";
  src = ./.;
  buildInputs = deps;
  buildPhase = ''
    echo "building ${name}"
  '';
  meta = { description = "A ${name} package"; };
}
