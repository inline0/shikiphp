#include <stdio.h>
#include <stdlib.h>
#include <string.h>

typedef struct {
    char *name;
    int age;
} Person;

int compare(const void *a, const void *b) {
    return ((Person *)a)->age - ((Person *)b)->age;
}

int main(void) {
    Person people[] = {{"Alice", 30}, {"Bob", 25}, {"Carol", 35}};
    size_t n = sizeof(people) / sizeof(people[0]);
    qsort(people, n, sizeof(Person), compare);
    for (size_t i = 0; i < n; i++) {
        printf("%s is %d\n", people[i].name, people[i].age);
    }
    return 0;
}
