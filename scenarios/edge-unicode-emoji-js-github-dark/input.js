const greetings = {
  "日本語": "こんにちは 🌸",
  "العربية": "مرحبا 👋",
  "emoji": "🚀💡🎉🦄🔥",
  "combining": "é(é) ñ(ñ) Z̵a̶l̷g̸o",
};

const flag = "🇯🇵🇺🇸🇪🇺";
const family = "👨‍👩‍👧‍👦";
console.log(`${greetings["日本語"]} ${flag} ${family}`);
for (const [k, v] of Object.entries(greetings)) {
  console.log(`${k}: ${v.length} units`);
}
