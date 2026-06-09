import ballerina/io;

function add(int a, int b) returns int {
    return a + b;
}

public function main() {
    int[] nums = [1, 2, 3, 4, 5];
    int total = 0;
    foreach int n in nums {
        total = add(total, n);
    }
    boolean done = total > 10;
    io:println(total);
    io:println(done);
}
