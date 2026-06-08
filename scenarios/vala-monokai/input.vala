using GLib;

public class Calculator : Object {
    public int total { get; private set; default = 0; }

    public void add(int value) {
        this.total += value;
    }

    public static int main(string[] args) {
        var calc = new Calculator();
        foreach (string arg in args) {
            calc.add(int.parse(arg));
        }
        stdout.printf("Total: %d\n", calc.total);
        return 0;
    }
}
