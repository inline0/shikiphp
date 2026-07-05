import { defineConfig } from "onedocs/config";
import {
  Braces,
  FileCode,
  Palette,
  Plug,
  Terminal,
  TestTube,
  Wand2,
  Zap,
} from "lucide-react";
const iconClass = "h-5 w-5 text-fd-primary";

export default defineConfig({
  title: "shikiphp",
  description:
    "Pure PHP syntax highlighter — a port of Shiki. Style code blocks with VS Code themes and TextMate grammars, in PHP only. No Node, no extensions; output byte-identical to Shiki.",
  logo: {
    light: "/logo-light.svg",
    dark: "/logo-dark.svg",
  },
  icon: { light: "/icon.png", dark: "/icon-dark.png" },
  nav: {
    github: "inline0/shikiphp",
  },
  footer: {
    links: [{ label: "Inline0.com", href: "https://inline0.com" }],
  },
  homepage: {
    features: [
      {
        title: "Real TextMate Grammars",
        description:
          "A faithful port of vscode-textmate: embedded languages, injections, and nested repositories. The same grammars Shiki and VS Code tokenize with.",
        icon: <FileCode className={iconClass} />,
      },
      {
        title: "VS Code Themes",
        description:
          "Scope-selector specificity and parent-scope matching, ported from vscode-textmate. Single theme or dual light/dark via CSS variables.",
        icon: <Palette className={iconClass} />,
      },
      {
        title: "Byte-Identical Output",
        description:
          "The HTML matches Shiki.js character for character, validated token-for-token by an oracle harness of 214 language/theme scenarios.",
        icon: <TestTube className={iconClass} />,
      },
      {
        title: "Pure-PHP Regex Engine",
        description:
          "A vendored JavaScript RegExp engine from inline0/phasis, fed by an oniguruma-to-es port — the same Oniguruma to RegExp path modern Shiki uses.",
        icon: <Braces className={iconClass} />,
      },
      {
        title: "Transformers",
        description:
          "Every Shiki hook (preprocess, tokens, root, pre, code, line, span, postprocess) plus the built-in notation, meta, and whitespace transformers.",
        icon: <Wand2 className={iconClass} />,
      },
      {
        title: "The Full Bundle",
        description:
          "Every tm-grammars language (200+) and tm-themes theme (65) ships in the box. ANSI terminal output, decorations, and colorReplacements included.",
        icon: <Plug className={iconClass} />,
      },
      {
        title: "CLI",
        description:
          "Highlight a file to HTML, or list bundled languages and themes, straight from the command line with bin/shikiphp.",
        icon: <Terminal className={iconClass} />,
      },
      {
        title: "No Node, No Extensions",
        description:
          "Runs on PHP 8.2+ with only ext-json and ext-mbstring. No Node runtime, no native Oniguruma binding, no shelling out.",
        icon: <Zap className={iconClass} />,
      },
    ],
  },
});
