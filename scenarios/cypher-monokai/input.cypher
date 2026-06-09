MATCH (a:Person {name: 'Alice'})-[:KNOWS]->(b:Person)
WHERE b.age > 25
RETURN b.name AS friend, b.age AS age
ORDER BY age DESC
LIMIT 5;

CREATE (p:Person {name: 'Carol', age: 30})
MERGE (c:City {name: 'Paris'})
CREATE (p)-[:LIVES_IN]->(c);
