def greet [name: string] {
    $"Hello, ($name)!"
}

let nums = [1 2 3 4 5]
let total = $nums | math sum
print (greet "world")
print $"sum = ($total)"

ls | where size > 1kb | sort-by modified | first 3
