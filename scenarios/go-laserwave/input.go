package main

import (
	"errors"
	"fmt"
	"sort"
)

type ByAge []Person

type Person struct {
	Name string
	Age  int
}

func (a ByAge) Len() int           { return len(a) }
func (a ByAge) Swap(i, j int)      { a[i], a[j] = a[j], a[i] }
func (a ByAge) Less(i, j int) bool { return a[i].Age < a[j].Age }

func oldest(people []Person) (Person, error) {
	if len(people) == 0 {
		return Person{}, errors.New("empty")
	}
	sort.Sort(ByAge(people))
	return people[len(people)-1], nil
}

func main() {
	people := []Person{
		{"Alice", 30},
		{"Bob", 25},
		{"Carol", 35},
	}
	p, err := oldest(people)
	if err != nil {
		panic(err)
	}
	fmt.Printf("oldest: %s (%d)\n", p.Name, p.Age)
}
