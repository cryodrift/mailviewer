WITH allFrom AS (SELECT domain_tld AS domain,
                        email,
                        id,
                        date
                 FROM v_allfrom_domain
                 ORDER BY date)
SELECT domain,
       compareDateName(date, :thedate) AS age,
       date,
       count(email)                    AS anz,
       GROUP_CONCAT(id, ',')           AS ids
FROM allFrom
WHERE email LIKE :email OR email LIKE :email2
  AND age = '1'
GROUP BY email
ORDER BY date DESC;
