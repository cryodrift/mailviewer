WITH allFrom AS (
    SELECT
        domain_tld AS domain,
        email,
        id,
        date
    FROM v_allfrom_domain
),
     allSorted AS (
         SELECT
             domain,
             MAX(date)             AS last_date,
             COUNT(email)          AS anz,
             GROUP_CONCAT(id, ',') AS ids
         FROM allFrom
         GROUP BY domain
     )
SELECT
    domain,
    compareDateName(last_date, :thedate) AS age,
    last_date AS date,
    anz,
    ids
FROM allSorted
WHERE age = '1'
ORDER BY last_date DESC
LIMIT :limit OFFSET :offset;
