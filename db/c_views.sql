drop view if exists v_allfrom_domain2;

CREATE VIEW v_allfrom_domain2 AS
WITH RECURSIVE domain_cte(email, id, date, domain) AS (
    SELECT
        a1.email,
        a2.id,
        a2.date,
        substr(a1.email, instr(a1.email, '@') + 1) as domain
    FROM mail_address AS a1
             JOIN mail_address_mail AS a3 ON a1.id = a3.mail_address_id
             JOIN mail AS a2 ON a2.id = a3.mail_id
    WHERE a3.type = 'from'
      AND a2.ofilename IS NOT NULL
    UNION ALL
    SELECT
        email,
        id,
        date,
        substr(domain, instr(domain, '.') + 1) as domain
    FROM domain_cte
    WHERE length(domain) - length(replace(domain, '.', '')) > 1
)
SELECT DISTINCT
    email,
    id,
    date,
    domain AS domain_tld
FROM domain_cte
WHERE length(domain) - length(replace(domain, '.', '')) = 1;


DROP VIEW IF EXISTS v_allfrom_domain;

CREATE VIEW v_allfrom_domain AS
WITH RECURSIVE domain_cte(email, id, date, domain) AS (
    SELECT
        a1.email,
        a2.id,
        a2.date,
        substr(a1.email, instr(a1.email, '@') + 1) AS domain
    FROM mail_address AS a1
             JOIN mail_address_mail AS a3 ON a1.id = a3.mail_address_id
             JOIN mail AS a2 ON a2.id = a3.mail_id
    WHERE a3.type = 'from'
      AND a2.ofilename IS NOT NULL

    UNION ALL

    SELECT
        email,
        id,
        date,
        substr(domain, instr(domain, '.') + 1) AS domain
    FROM domain_cte
    WHERE length(domain) - length(replace(domain, '.', '')) > 1
),
               all_mails AS (
                   SELECT
                       email,
                       id,
                       date,
                       domain AS domain_tld
                   FROM domain_cte
                   WHERE length(domain) - length(replace(domain, '.', '')) = 1
               ),
               domain_agg AS (
                   SELECT
                       domain_tld,
                       COUNT(*)              AS anz,
                       MAX(date)             AS last_date,
                       GROUP_CONCAT(id, ',') AS ids
                   FROM all_mails
                   GROUP BY domain_tld
               )
SELECT
    m.email,
    m.id,
    m.date,
    m.domain_tld,
    a.anz,
    a.last_date,
    a.ids
FROM all_mails AS m
         JOIN domain_agg AS a ON a.domain_tld = m.domain_tld;
