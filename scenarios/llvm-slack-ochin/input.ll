define i32 @add(i32 %a, i32 %b) {
entry:
  %sum = add nsw i32 %a, %b
  ret i32 %sum
}

define i32 @main() {
entry:
  %result = call i32 @add(i32 3, i32 4)
  %cmp = icmp sgt i32 %result, 5
  br i1 %cmp, label %big, label %small

big:
  ret i32 1

small:
  ret i32 0
}
