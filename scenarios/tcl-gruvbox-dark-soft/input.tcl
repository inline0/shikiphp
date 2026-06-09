proc factorial {n} {
    if {$n <= 1} {
        return 1
    }
    return [expr {$n * [factorial [expr {$n - 1}]]}]
}

set total 0
foreach i {1 2 3 4 5} {
    set f [factorial $i]
    puts "factorial($i) = $f"
    incr total $f
}

puts "sum = $total"
