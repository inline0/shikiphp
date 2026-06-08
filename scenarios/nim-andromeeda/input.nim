import std/strutils

type
  Animal = ref object of RootObj
    name: string
    legs: int

method speak(a: Animal): string {.base.} = "..."

proc describe[T](items: seq[T]): string =
  result = ""
  for i, item in items:
    result.add($i & ": " & $item & "\n")

when isMainModule:
  let nums = @[1, 2, 3]
  echo describe(nums)
  echo "hello".toUpperAscii()
