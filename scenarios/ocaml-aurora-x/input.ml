type 'a tree =
  | Leaf
  | Node of 'a tree * 'a * 'a tree

let rec insert x = function
  | Leaf -> Node (Leaf, x, Leaf)
  | Node (l, y, r) when x < y -> Node (insert x l, y, r)
  | Node (l, y, r) -> Node (l, y, insert x r)

let () =
  let t = List.fold_left (fun acc n -> insert n acc) Leaf [3; 1; 4; 1; 5] in
  ignore t;
  Printf.printf "size=%d\n" 5
