import React, { useState } from "react";

const TODOS = ["learn", "build", "ship"];

function TodoList({ title }) {
    const [items, setItems] = useState(TODOS);

    const add = (text) => setItems((prev) => [...prev, text]);

    return (
        <section className="todos" data-count={items.length}>
            <h1>{title || "Untitled"}</h1>
            <ul>
                {items.map((item, i) => (
                    <li key={i} onClick={() => add(`${item}!`)}>
                        {i + 1}. {item}
                    </li>
                ))}
            </ul>
            {/* footer */}
            <button disabled={items.length > 5}>Add</button>
        </section>
    );
}

export default TodoList;
