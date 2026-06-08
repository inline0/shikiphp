#include <stdio.h>
#include <stdlib.h>

#define MAX 16
#define SQUARE(x) ((x) * (x))

/* A simple ring buffer. */
typedef struct {
    int data[MAX];
    size_t head;
} Ring;

static int sum(const int *arr, size_t n) {
    int total = 0;
    for (size_t i = 0; i < n; ++i) {
        total += arr[i];
    }
    return total;
}

int main(void) {
    Ring r = { .head = 0 };
    char *msg = "value:\t%d\n";
    for (int i = 0; i < MAX; i++) {
        r.data[i] = SQUARE(i);
    }
    printf(msg, sum(r.data, MAX));
    return EXIT_SUCCESS;
}
