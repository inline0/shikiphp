defmodule Calculator do
  @moduledoc "A tiny calculator module."

  @pi 3.14159

  @doc "Adds two numbers."
  def add(a, b), do: a + b

  def area(radius) when is_number(radius) do
    @pi * radius * radius
  end

  def describe(n) do
    cond do
      n < 0 -> "negative"
      n == 0 -> "zero"
      true -> "positive"
    end
  end
end

name = "world"
IO.puts("Hello, #{name}!")
result = [1, 2, 3] |> Enum.map(&(&1 * 2)) |> Enum.sum()
