# Compute factorial
sub factorial(Int $n --> Int) {
    return 1 if $n <= 1;
    return $n * factorial($n - 1);
}

my @nums = 1..5;
my %config = debug => True, retries => 3;

my $name = "World";
say "Hello, $name!";

for @nums -> $n {
    say "$n! = { factorial($n) }";
}

my @squares = @nums.map(* ** 2);
my @evens = @nums.grep(* %% 2);
say "Evens: @evens[]";
