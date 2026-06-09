const std = @import("std");

const Color = enum { red, green, blue };

fn fib(n: u64) u64 {
    if (n < 2) return n;
    return fib(n - 1) + fib(n - 2);
}

pub fn main() !void {
    const stdout = std.io.getStdOut().writer();
    var sum: u64 = 0;
    for (0..10) |i| {
        sum += fib(@intCast(i));
    }
    try stdout.print("sum = {d}\n", .{sum});
    const c: Color = .green;
    std.debug.assert(c == .green);
}
