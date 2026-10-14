---
title: "Languages"
description: "The full tm-grammars catalog ships with shikiphp: 235 languages with their ids and aliases, and how to list them."
path: "languages"
order: 60
section: "Languages & Themes"
meta_title: "Languages"
meta_description: "The full tm-grammars catalog ships with shikiphp: 235 languages with their ids and aliases, and how to list them."
---

# Languages

shikiphp bundles the **full Shiki language set**: every grammar from
[`tm-grammars`](https://github.com/shikijs/textmate-grammars-themes) 1.31.15,
the set Shiki 3.23.0 ships. That is 235 languages plus 18 helper grammars they
embed, 253 grammars in all. These are the same TextMate grammars VS Code and
Shiki tokenize with, so language coverage, aliases, and scope names match.

You reference a language by its id or one of its aliases — for example `ts` or
`typescript`, `js` or `javascript`, `php`, `rust`, `python`, `html`, `yaml`,
`json`, and so on. Ids are matched exactly, so use them in lowercase as listed
below; an id the bundle does not know throws
`Shikiphp\Exceptions\Highlight` with the message `Unknown language "…"`.

The special id `'ansi'` is not a grammar; it highlights terminal output. See
[ANSI](/docs/ansi).

Shiki's plain-text ids `text`, `txt`, `plain`, and `plaintext` are not
supported yet and throw `Unknown language`
([#2](https://github.com/inline0/shikiphp/issues/2)).

## Listing the bundled languages

From PHP, ask the highlighter:

```php
use Shikiphp\Shikiphp;

$highlighter = Shikiphp::highlighter();

$highlighter->bundledLanguages(); // list<string> — ids shipped in the box
$highlighter->loadedLanguages();  // list<string> — bundled plus any you registered
```

Both lists hold every id that resolves: the 253 grammar ids followed by the 97
aliases, 350 entries for the bundle.

Or from the command line, one id per line:

```bash
vendor/bin/shikiphp langs
```

## Catalog

The 235 bundled languages, sorted by name. Any id or alias in a row selects the
same grammar.

| Language | Id | Aliases |
| --- | --- | --- |
| 1C (Enterprise) | `bsl` | `1c` |
| 1C (Query) | `sdbl` | `1c-query` |
| ABAP | `abap` |  |
| ActionScript | `actionscript-3` |  |
| Ada | `ada` |  |
| Angular HTML | `angular-html` |  |
| Angular TypeScript | `angular-ts` |  |
| Apache Conf | `apache` |  |
| Apex | `apex` |  |
| APL | `apl` |  |
| AppleScript | `applescript` |  |
| Ara | `ara` |  |
| AsciiDoc | `asciidoc` | `adoc` |
| ASP.NET Razor | `razor` |  |
| Assembly | `asm` |  |
| Astro | `astro` |  |
| AWK | `awk` |  |
| Ballerina | `ballerina` |  |
| Batch File | `bat` | `batch` |
| Beancount | `beancount` |  |
| Berry | `berry` | `be` |
| BibTeX | `bibtex` |  |
| Bicep | `bicep` |  |
| BIRD2 Configuration | `bird2` | `bird` |
| Blade | `blade` |  |
| C | `c` |  |
| C# | `csharp` | `c#`, `cs` |
| C++ | `cpp` | `c++` |
| C3 | `c3` |  |
| Cadence | `cadence` | `cdc` |
| Cairo | `cairo` |  |
| Clarity | `clarity` |  |
| Clojure | `clojure` | `clj` |
| Closure Templates | `soy` | `closure-templates` |
| CMake | `cmake` |  |
| COBOL | `cobol` |  |
| CODEOWNERS | `codeowners` |  |
| CodeQL | `codeql` | `ql` |
| CoffeeScript | `coffee` | `coffeescript` |
| Common Lisp | `common-lisp` | `lisp` |
| Coq | `coq` |  |
| Crystal | `crystal` |  |
| CSS | `css` |  |
| CSV | `csv` |  |
| CUE | `cue` |  |
| Cypher | `cypher` | `cql` |
| D | `d` |  |
| Dart | `dart` |  |
| DAX | `dax` |  |
| Desktop | `desktop` |  |
| Diff | `diff` |  |
| Dockerfile | `docker` | `dockerfile` |
| dotEnv | `dotenv` |  |
| Dream Maker | `dream-maker` |  |
| Edge | `edge` |  |
| Elixir | `elixir` |  |
| Elm | `elm` |  |
| Emacs Lisp | `emacs-lisp` | `elisp` |
| ERB | `erb` |  |
| Erlang | `erlang` | `erl` |
| F# | `fsharp` | `f#`, `fs` |
| Fennel | `fennel` |  |
| Fish | `fish` |  |
| Fluent | `fluent` | `ftl` |
| Fortran (Fixed Form) | `fortran-fixed-form` | `f`, `for`, `f77` |
| Fortran (Free Form) | `fortran-free-form` | `f90`, `f95`, `f03`, `f08`, `f18` |
| GDResource | `gdresource` | `tscn`, `tres` |
| GDScript | `gdscript` | `gd` |
| GDShader | `gdshader` |  |
| Genie | `genie` |  |
| Gettext PO | `po` | `pot`, `potx` |
| Gherkin | `gherkin` |  |
| Git Commit Message | `git-commit` |  |
| Git Rebase Message | `git-rebase` |  |
| Gleam | `gleam` |  |
| Glimmer JS | `glimmer-js` | `gjs` |
| Glimmer TS | `glimmer-ts` | `gts` |
| GLSL | `glsl` |  |
| GN | `gn` |  |
| Gnuplot | `gnuplot` |  |
| Go | `go` |  |
| GraphQL | `graphql` | `gql` |
| Groovy | `groovy` |  |
| Hack | `hack` |  |
| Handlebars | `handlebars` | `hbs` |
| HashiCorp HCL | `hcl` |  |
| Haskell | `haskell` | `hs` |
| Haxe | `haxe` |  |
| Hjson | `hjson` |  |
| HLSL | `hlsl` |  |
| HTML | `html` |  |
| HTML (Derivative) | `html-derivative` |  |
| HTTP | `http` |  |
| Hurl | `hurl` |  |
| HXML | `hxml` |  |
| Hy | `hy` |  |
| Imba | `imba` |  |
| INI | `ini` | `properties` |
| Java | `java` |  |
| JavaScript | `javascript` | `js`, `cjs`, `mjs` |
| Jinja | `jinja` |  |
| Jison | `jison` |  |
| JSON | `json` |  |
| JSON Lines | `jsonl` |  |
| JSON with Comments | `jsonc` |  |
| JSON5 | `json5` |  |
| Jsonnet | `jsonnet` |  |
| JSSM | `jssm` | `fsl` |
| JSX | `jsx` |  |
| Julia | `julia` | `jl` |
| Just | `just` |  |
| KDL | `kdl` |  |
| Kotlin | `kotlin` | `kt`, `kts` |
| Kusto | `kusto` | `kql` |
| LaTeX | `latex` |  |
| Lean 4 | `lean` | `lean4` |
| Less | `less` |  |
| Liquid | `liquid` |  |
| LLVM IR | `llvm` |  |
| Log file | `log` |  |
| Logo | `logo` |  |
| Lua | `lua` |  |
| Luau | `luau` |  |
| Makefile | `make` | `makefile` |
| Markdown | `markdown` | `md` |
| Marko | `marko` |  |
| MATLAB | `matlab` |  |
| MDC | `mdc` |  |
| MDX | `mdx` |  |
| Mermaid | `mermaid` | `mmd` |
| MIPS Assembly | `mipsasm` | `mips` |
| Mojo | `mojo` |  |
| MoonBit | `moonbit` | `mbt`, `mbti` |
| Move | `move` |  |
| Narrat Language | `narrat` | `nar` |
| Nextflow | `nextflow` | `nf` |
| Nextflow Groovy | `nextflow-groovy` |  |
| Nginx | `nginx` |  |
| Nim | `nim` |  |
| Nix | `nix` |  |
| nushell | `nushell` | `nu` |
| Objective-C | `objective-c` | `objc` |
| Objective-C++ | `objective-cpp` |  |
| OCaml | `ocaml` |  |
| Odin | `odin` |  |
| OpenSCAD | `openscad` | `scad` |
| Pascal | `pascal` |  |
| Perl | `perl` |  |
| PHP | `php` |  |
| Pkl | `pkl` |  |
| PL/SQL | `plsql` |  |
| Polar | `polar` |  |
| PostCSS | `postcss` |  |
| PowerQuery | `powerquery` |  |
| PowerShell | `powershell` | `ps`, `ps1` |
| Prisma | `prisma` |  |
| Prolog | `prolog` |  |
| Protocol Buffer 3 | `proto` | `protobuf` |
| Pug | `pug` | `jade` |
| Puppet | `puppet` |  |
| PureScript | `purescript` |  |
| Python | `python` | `py` |
| QML | `qml` |  |
| QML Directory | `qmldir` |  |
| Qt Style Sheets | `qss` |  |
| R | `r` |  |
| Racket | `racket` |  |
| Raku | `raku` | `perl6` |
| RegExp | `regexp` | `regex` |
| Rel | `rel` |  |
| reStructuredText | `rst` |  |
| RISC-V | `riscv` |  |
| RON | `ron` |  |
| ROS Interface | `rosmsg` |  |
| Ruby | `ruby` | `rb` |
| Ruby Haml | `haml` |  |
| Rust | `rust` | `rs` |
| SAS | `sas` |  |
| Sass | `sass` |  |
| Scala | `scala` |  |
| Scheme | `scheme` |  |
| SCSS | `scss` |  |
| ShaderLab | `shaderlab` | `shader` |
| Shell | `shellscript` | `bash`, `sh`, `shell`, `zsh` |
| Shell Session | `shellsession` | `console` |
| Smalltalk | `smalltalk` |  |
| Solidity | `solidity` |  |
| SPARQL | `sparql` |  |
| Splunk Query Language | `splunk` | `spl` |
| SQL | `sql` |  |
| SSH Config | `ssh-config` |  |
| Stata | `stata` |  |
| Stylus | `stylus` | `styl` |
| SurrealQL | `surrealql` | `surql` |
| Svelte | `svelte` |  |
| Swift | `swift` |  |
| Systemd Units | `systemd` |  |
| SystemVerilog | `system-verilog` |  |
| TalonScript | `talonscript` | `talon` |
| Tasl | `tasl` |  |
| Tcl | `tcl` |  |
| Templ | `templ` |  |
| Terraform | `terraform` | `tf`, `tfvars` |
| TeX | `tex` |  |
| TOML | `toml` |  |
| TSV | `tsv` |  |
| TSX | `tsx` |  |
| Turtle | `turtle` |  |
| Twig | `twig` |  |
| TypeScript | `typescript` | `ts`, `cts`, `mts` |
| TypeScript with Tags | `ts-tags` | `lit` |
| TypeSpec | `typespec` | `tsp` |
| Typst | `typst` | `typ` |
| V | `v` |  |
| Vala | `vala` |  |
| Verilog | `verilog` |  |
| VHDL | `vhdl` |  |
| Vim Script | `viml` | `vim`, `vimscript` |
| Visual Basic | `vb` | `cmd` |
| Vue | `vue` |  |
| Vue HTML | `vue-html` |  |
| Vue Vine | `vue-vine` |  |
| Vyper | `vyper` | `vy` |
| WebAssembly | `wasm` |  |
| WebAssembly Interface Types | `wit` |  |
| Wenyan | `wenyan` | `文言` |
| WGSL | `wgsl` |  |
| Wikitext | `wikitext` | `mediawiki`, `wiki` |
| Windows Registry Script | `reg` |  |
| Wolfram | `wolfram` | `wl` |
| XML | `xml` |  |
| XSL | `xsl` |  |
| YAML | `yaml` | `yml` |
| ZenScript | `zenscript` |  |
| Zig | `zig` |  |

### Helper grammars

These 18 grammars exist so other grammars can embed or inject them (Angular,
Vue, C++ macros, Jinja, Nix, and tagged template literals in `ts-tags`). They
are loaded automatically with the language that needs them, and their ids also
appear in `bundledLanguages()` and `shikiphp langs`:

`angular-expression`, `angular-let-declaration`, `angular-template`, `angular-template-blocks`, `angular-inline-style`, `angular-inline-template`, `cpp-macro`, `jinja-html`, `markdown-nix`, `es-tag-css`, `es-tag-glsl`, `es-tag-html`, `es-tag-sql`, `es-tag-xml`, `markdown-vue`, `vue-directives`, `vue-interpolations`, `vue-sfc-style-variable-injection`.

## Adding your own

Need a language that is not bundled, or a private grammar? Register it with
`loadGrammar`. See [Custom languages and themes](/docs/custom-languages-themes).
