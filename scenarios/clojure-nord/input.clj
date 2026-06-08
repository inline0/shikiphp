(ns demo.core
  (:require [clojure.string :as str]))

;; Compute factorial recursively
(defn factorial [n]
  (if (<= n 1)
    1
    (* n (factorial (dec n)))))

(def greeting "Hello, world!")

(defn greet [name]
  (str/join " " ["Hi" name "you are number" (factorial 5)]))

(let [nums [1 2 3 4 5]
      total (reduce + 0 nums)]
  (println greeting)
  (println (map #(* % %) nums))
  {:total total :keyword :ok :ratio 3/4})
