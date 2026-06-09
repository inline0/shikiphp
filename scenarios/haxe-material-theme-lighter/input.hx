package;

class Main {
    static var counter:Int = 0;

    public static function main():Void {
        var names = ["alice", "bob", "carol"];
        for (name in names) {
            counter++;
            trace(counter + ": " + name);
        }
        var sum = names.map(function(s) return s.length).length;
        trace("sum=" + sum);
    }
}
