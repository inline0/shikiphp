#set page(width: 10cm, height: auto)
#set heading(numbering: "1.")

= Introduction

This is *bold* and _italic_ text.

#let fib(n) = {
  if n < 2 { n } else { fib(n - 1) + fib(n - 2) }
}

The 10th Fibonacci number is #fib(10).

$ sum_(i=1)^n i = (n(n+1)) / 2 $

#figure(
  rect(width: 4cm),
  caption: [A rectangle],
)
