"Compute the nth Fibonacci number."
function fib(n::Int)
    n < 2 && return n
    return fib(n - 1) + fib(n - 2)
end

struct Point{T<:Real}
    x::T
    y::T
end

norm(p::Point) = sqrt(p.x^2 + p.y^2)

const greeting = "Hello, Julia!"
nums = [1, 2, 3, 4, 5]
squares = [x^2 for x in nums if iseven(x)]

p = Point(3.0, 4.0)
println("$greeting norm=$(norm(p))")
println("fib(10) = ", fib(10))
