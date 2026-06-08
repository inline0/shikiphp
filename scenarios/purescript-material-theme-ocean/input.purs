module Main where

import Prelude
import Effect (Effect)
import Effect.Console (log)
import Data.Maybe (Maybe(..))

data Tree a = Leaf | Branch (Tree a) a (Tree a)

insert :: forall a. Ord a => a -> Tree a -> Tree a
insert x Leaf = Branch Leaf x Leaf
insert x (Branch l y r)
  | x < y = Branch (insert x l) y r
  | otherwise = Branch l y (insert x r)

main :: Effect Unit
main = log "tree built"
