<?hh

class Greeter {
  public function __construct(private string $name) {}

  public function greet(): string {
    return "Hello, " . $this->name;
  }
}

function main(): void {
  $vec = vec[1, 2, 3];
  $sum = 0;
  foreach ($vec as $n) {
    $sum += $n;
  }
  $g = new Greeter("world");
  echo $g->greet() . " sum=" . $sum . "\n";
}
