import { generateRobots } from "onedocs/seo";

const baseUrl = "https://shikiphp.dev";

export default function robots() {
  return generateRobots({ baseUrl });
}
