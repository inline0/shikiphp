-- Monthly active users report
CREATE TABLE IF NOT EXISTS users (
    id          SERIAL PRIMARY KEY,
    email       VARCHAR(255) NOT NULL UNIQUE,
    created_at  TIMESTAMP DEFAULT NOW(),
    is_active   BOOLEAN DEFAULT TRUE
);

INSERT INTO users (email) VALUES ('ada@example.com'), ('linus@example.com');

SELECT
    DATE_TRUNC('month', created_at) AS month,
    COUNT(*) AS signups,
    AVG(EXTRACT(EPOCH FROM created_at)) AS avg_epoch
FROM users
WHERE is_active = TRUE
  AND email LIKE '%@example.com'
GROUP BY 1
HAVING COUNT(*) > 10
ORDER BY month DESC
LIMIT 100;
