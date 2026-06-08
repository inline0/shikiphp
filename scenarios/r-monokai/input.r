# Summarize a numeric vector
summarize <- function(x, na.rm = TRUE) {
  list(
    mean = mean(x, na.rm = na.rm),
    sd   = sd(x, na.rm = na.rm),
    n    = length(x)
  )
}

nums <- c(1, 2, 3, 4, 5, NA)
result <- summarize(nums)

df <- data.frame(
  id = 1:3,
  name = c("Alice", "Bob", "Carol"),
  stringsAsFactors = FALSE
)

squares <- sapply(1:5, function(n) n^2)
for (i in seq_along(squares)) {
  cat(sprintf("%d^2 = %d\n", i, squares[i]))
}
