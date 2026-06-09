module Main exposing (main)

import Html exposing (Html, text)


type Tree a
    = Leaf
    | Node (Tree a) a (Tree a)


insert : comparable -> Tree comparable -> Tree comparable
insert x tree =
    case tree of
        Leaf ->
            Node Leaf x Leaf

        Node left v right ->
            if x < v then
                Node (insert x left) v right

            else
                Node left v (insert x right)


main : Html msg
main =
    text "hello"
