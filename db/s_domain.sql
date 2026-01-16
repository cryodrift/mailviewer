WITH allFrom AS (
    SELECT
        domain_tld AS domain,
        email,
        id,
        date
    FROM v_allfrom_domain2
    WHERE email LIKE :email OR email LIKE :email2
),
     allSorted AS (
         SELECT
             domain,
             MAX(date)              AS last_date,
             COUNT(*)               AS anz,
             GROUP_CONCAT(id, ',')  AS ids
         FROM allFrom
         GROUP BY domain
     )
SELECT
    domain,
    last_date AS date,
    anz,
    ids
FROM allSorted
ORDER BY last_date DESC
LIMIT :limit OFFSET :offset;
