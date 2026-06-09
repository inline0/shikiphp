WITH monthly AS (
    SELECT
        date_trunc('month', created_at) AS month,
        COUNT(*) AS orders,
        SUM(total) AS revenue
    FROM orders
    WHERE status = 'completed'
    GROUP BY 1
)
SELECT
    month,
    orders,
    revenue,
    LAG(revenue) OVER (ORDER BY month) AS prev_revenue
FROM monthly
ORDER BY month DESC
LIMIT 12;
