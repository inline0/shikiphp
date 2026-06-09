const memo = new Map();

function fib(n) {
  if (n < 2) return n;
  if (memo.has(n)) return memo.get(n);
  const result = fib(n - 1) + fib(n - 2);
  memo.set(n, result);
  return result;
}

const nums = Array.from({ length: 10 }, (_, i) => fib(i));
const total = nums.reduce((acc, x) => acc + x, 0);
console.log(`fibs: ${nums.join(", ")} total=${total}`);

export default { fib, nums };
