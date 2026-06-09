package main

import (
	"errors"
	"fmt"
	"sort"
	"strings"
	"sync"
	"time"
)

const maxRetries = 5

type Role string

const (
	RoleAdmin  Role = "admin"
	RoleMember Role = "member"
	RoleGuest  Role = "guest"
)

type User struct {
	ID        uint64
	Email     string
	Role      Role
	CreatedAt time.Time
}

func (u User) Describe() string {
	return fmt.Sprintf("%s <%s>", u.Email, u.Role)
}

type UserStore struct {
	mu    sync.RWMutex
	users map[uint64]User
	seq   uint64
}

func NewUserStore() *UserStore {
	return &UserStore{users: make(map[uint64]User)}
}

func (s *UserStore) Add(email string, role Role) User {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.seq++
	u := User{ID: s.seq, Email: strings.ToLower(email), Role: role, CreatedAt: time.Now()}
	s.users[u.ID] = u
	return u
}

func (s *UserStore) Find(id uint64) (User, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	u, ok := s.users[id]
	if !ok {
		return User{}, errors.New("not found")
	}
	return u, nil
}

func (s *UserStore) Filter(pred func(User) bool) []User {
	s.mu.RLock()
	defer s.mu.RUnlock()
	var out []User
	for _, u := range s.users {
		if pred(u) {
			out = append(out, u)
		}
	}
	sort.Slice(out, func(i, j int) bool { return out[i].ID < out[j].ID })
	return out
}

func main() {
	store := NewUserStore()
	store.Add("a@example.com", RoleAdmin)
	store.Add("b@example.com", RoleMember)

	admins := store.Filter(func(u User) bool { return u.Role == RoleAdmin })
	for _, u := range admins {
		fmt.Println(u.Describe())
	}

	if u, err := store.Find(1); err == nil {
		fmt.Printf("found: %+v\n", u)
	} else {
		fmt.Println("error:", err)
	}
}
