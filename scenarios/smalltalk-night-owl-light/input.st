Object subclass: Counter [
    | count |
    init [ count := 0 ]
    increment [ count := count + 1 ]
    count [ ^count ]
]

| c total |
c := Counter new.
c init.
1 to: 5 do: [ :i | c increment ].
total := c count.
Transcript showCr: 'count: ', total printString.
