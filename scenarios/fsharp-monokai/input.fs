module Demo

open System

/// Recursive factorial
let rec factorial n =
    if n <= 1 then 1
    else n * factorial (n - 1)

type Shape =
    | Circle of float
    | Rectangle of width: float * height: float

let area shape =
    match shape with
    | Circle r -> Math.PI * r * r
    | Rectangle (w, h) -> w * h

[<EntryPoint>]
let main argv =
    let nums = [1..5]
    let total = nums |> List.map (fun x -> x * x) |> List.sum
    printfn "Total: %d, fact: %d" total (factorial 5)
    0
