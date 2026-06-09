import { useState, useCallback } from "react";

interface Props {
  initial?: number;
  label: string;
}

export function Counter({ initial = 0, label }: Props): JSX.Element {
  const [count, setCount] = useState<number>(initial);
  const inc = useCallback(() => setCount((c) => c + 1), []);

  return (
    <div className="counter">
      <span>{label}: {count}</span>
      <button onClick={inc} disabled={count > 10}>
        +
      </button>
    </div>
  );
}
