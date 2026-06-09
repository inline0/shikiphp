const styled = (cls: string) => `component-${cls}-${Date.now()}`;
const css = `
  .box {
    color: ${"#fff"};
    margin: ${[1, 2, 3].map((n) => `${n}px`).join(" ")};
  }
`;

export const Tag = ({ items }: { items: string[] }) => (
  <ul className={styled("list")}>
    {items.map((item, i) => (
      <li key={`${item}-${i}`} data-css={css}>
        {`#${i + 1}: ${item.toUpperCase()}`}
      </li>
    ))}
  </ul>
);
