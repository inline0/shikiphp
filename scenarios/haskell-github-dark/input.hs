module Main where

import Data.List (sort)

data Shape = Circle Double | Rect Double Double

area :: Shape -> Double
area (Circle r) = pi * r * r
area (Rect w h) = w * h

f :: Int -> Int
f x = x * 2 - 1

double :: Int -> Int
double = \x -> x + x

compose :: (b -> c) -> (a -> b) -> a -> c
compose g h = g . h

main :: IO ()
main = print result
  where
    nums = [1, 2, 3, 4]
    result = sum (map f nums) $ 0
