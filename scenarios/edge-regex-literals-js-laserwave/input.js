const patterns = {
  email: /^[\w.+-]+@[\w-]+\.[\w.-]+$/i,
  url: /https?:\/\/(?<host>[^\/\s]+)(?<path>\/[^\s?#]*)?/gu,
  date: /(\d{4})-(\d{2})-(\d{2})/,
  unicode: /\p{Emoji}\u{1F600}/u,
};

const text = "visit https://example.com/path on 2024-01-15";
const m = text.match(patterns.url);
console.log(m?.groups?.host);
const cleaned = text.replace(/\s+/g, "_").split(/[-:]/);
console.log(cleaned, patterns.email.test("a@b.co"));
