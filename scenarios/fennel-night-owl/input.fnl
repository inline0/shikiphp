(local fennel (require :fennel))

(fn map [f tbl]
  (let [out []]
    (each [_ v (ipairs tbl)]
      (table.insert out (f v)))
    out))

(fn square [x] (* x x))

(local nums [1 2 3 4])
(print (table.concat (map square nums) " "))
