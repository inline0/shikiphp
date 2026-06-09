package com.example {
    import flash.display.Sprite;

    public class Main extends Sprite {
        private var count:int = 0;

        public function Main() {
            for (var i:int = 0; i < 5; i++) {
                count += i;
                trace("step " + i + " => " + count);
            }
            var names:Array = ["a", "b", "c"];
            for each (var n:String in names) {
                trace(n.toUpperCase());
            }
        }
    }
}
