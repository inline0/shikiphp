import { HomePage, CTASection } from "onedocs";
import config from "../../onedocs.config";

export default function Home() {
  return (
    <HomePage config={config}>
      <CTASection
        title="Ready to highlight?"
        description="Install the Composer package and render your first themed code block in seconds."
        cta={{ label: "Read the Docs", href: "/docs" }}
      />
    </HomePage>
  );
}
