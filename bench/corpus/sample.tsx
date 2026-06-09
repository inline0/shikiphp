import React, { useCallback, useEffect, useMemo, useState } from "react";

interface Todo {
  id: number;
  title: string;
  done: boolean;
}

type Filter = "all" | "active" | "completed";

interface TodoListProps {
  initial?: Todo[];
  onChange?: (todos: Todo[]) => void;
}

const STORAGE_KEY = "todos.v1";

export function TodoApp({ initial = [], onChange }: TodoListProps): JSX.Element {
  const [todos, setTodos] = useState<Todo[]>(initial);
  const [filter, setFilter] = useState<Filter>("all");
  const [draft, setDraft] = useState("");

  useEffect(() => {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) setTodos(JSON.parse(raw) as Todo[]);
  }, []);

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(todos));
    onChange?.(todos);
  }, [todos, onChange]);

  const add = useCallback((title: string) => {
    if (!title.trim()) return;
    setTodos((prev) => [...prev, { id: Date.now(), title, done: false }]);
    setDraft("");
  }, []);

  const toggle = useCallback((id: number) => {
    setTodos((prev) =>
      prev.map((t) => (t.id === id ? { ...t, done: !t.done } : t)),
    );
  }, []);

  const visible = useMemo(() => {
    switch (filter) {
      case "active":
        return todos.filter((t) => !t.done);
      case "completed":
        return todos.filter((t) => t.done);
      default:
        return todos;
    }
  }, [todos, filter]);

  return (
    <section className="todo-app" data-count={todos.length}>
      <header>
        <h1>Todos ({visible.length})</h1>
        <input
          value={draft}
          placeholder="What needs doing?"
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && add(draft)}
        />
      </header>
      <ul>
        {visible.map((todo) => (
          <li key={todo.id} className={todo.done ? "done" : ""}>
            <label>
              <input
                type="checkbox"
                checked={todo.done}
                onChange={() => toggle(todo.id)}
              />
              {todo.title}
            </label>
          </li>
        ))}
      </ul>
      <footer>
        {(["all", "active", "completed"] as const).map((f) => (
          <button
            key={f}
            disabled={f === filter}
            onClick={() => setFilter(f)}
          >
            {f}
          </button>
        ))}
      </footer>
    </section>
  );
}
