package main

import (
	"fmt"
	"strings"
)

// Greeter builds greetings.
type Greeter struct {
	Prefix string
}

func (g *Greeter) Greet(names ...string) string {
	var b strings.Builder
	for i, n := range names {
		if i > 0 {
			b.WriteString(", ")
		}
		fmt.Fprintf(&b, "%s %s", g.Prefix, n)
	}
	return b.String()
}

func main() {
	g := &Greeter{Prefix: "Hello"}
	const limit = 0x1F
	msg := `raw
string`
	fmt.Println(g.Greet("Ada", "Linus"), limit, msg)
}
