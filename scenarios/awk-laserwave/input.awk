#!/usr/bin/awk -f
BEGIN {
    FS = ","
    count = 0
    total = 0
}

NF >= 2 && $2 > 0 {
    total += $2
    count++
    printf "row %d: %s = %d\n", NR, $1, $2
}

function average(sum, n) {
    if (n == 0)
        return 0
    return sum / n
}

END {
    print "rows:", count
    print "average:", average(total, count)
}
