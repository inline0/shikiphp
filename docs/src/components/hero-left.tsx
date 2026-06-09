import { Download } from "lucide-react";
import { CodeBlock, Button } from "onedocs";

async function getLatestVersion(): Promise<string | null> {
  try {
    const res = await fetch(
      "https://api.github.com/repos/inline0/shikiphp/releases/latest",
      { next: { revalidate: 3600 } },
    );
    if (!res.ok) return null;
    const data = await res.json();
    return data.tag_name ?? null;
  } catch {
    return null;
  }
}

export async function HeroLeft() {
  const version = await getLatestVersion();

  return (
    <>
      <h1 className="text-left text-4xl font-medium leading-tight text-fd-foreground sm:text-5xl">
        Pure PHP
        <br />
        syntax highlighting
      </h1>
      <p className="text-left max-w-xl leading-normal text-fd-muted-foreground sm:text-lg sm:leading-normal text-balance mt-4">
        A port of Shiki. Style code blocks with the same VS Code themes and
        TextMate grammars, entirely in PHP. No Node, no extensions, no hosted
        API. Output byte-identical to Shiki.
      </p>
      <div className="mt-8 w-full">
        <CodeBlock
          lang="bash"
          code="composer require shikiphp/shikiphp"
          className="!my-0"
        />
        <div className="flex items-center gap-3 mt-4">
          <Button href="/docs">Get Started</Button>
          <a
            href="https://github.com/inline0/shikiphp/releases"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center justify-center gap-2 rounded-full border border-fd-border px-4 py-2 text-sm font-medium text-fd-foreground transition-colors hover:bg-fd-secondary whitespace-nowrap"
          >
            <Download className="size-4" />
            Download{version ? ` ${version}` : ""}
          </a>
        </div>
      </div>
    </>
  );
}
