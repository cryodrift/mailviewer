WITH allFrom AS (SELECT domain_tld AS domain,
                        email,
                        id,
                        date
                 FROM v_allfrom_domain),
     allSorted as (SELECT domain,
                          email,
                          MAX(date)             AS last_date,
                          COUNT(email)          AS anz,
                          GROUP_CONCAT(id, ',') AS ids
                   FROM allFrom
                   GROUP BY domain)

select *, last_date as date
from allSorted
ORDER BY last_date DESC
LIMIT :limit OFFSET :offset;
