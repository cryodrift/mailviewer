with toaddresses as (SELECT a2.email 'to',
                            a2.name,
                            a1.mail_id
                     FROM mail_address_mail a1,
                          mail_address a2
                     where a1.mail_address_id = a2.id
                       and a1.type = 'to'),
     fromaddresses as (SELECT a2.email 'from',
                              a2.name,
                              a1.mail_id
                       FROM mail_address_mail a1,
                            mail_address a2
                       where a1.mail_address_id = a2.id
                         and a1.type = 'from')
SELECT m1.*,
       t1."to",
       t2."from",
       t1.name
FROM mail m1
         LEFT JOIN toaddresses t1 ON t1.mail_id = m1.id
         LEFT JOIN fromaddresses t2 ON t2.mail_id = m1.id
where m1.id in (:ids)
order by date desc
LIMIT :limit OFFSET :offset
