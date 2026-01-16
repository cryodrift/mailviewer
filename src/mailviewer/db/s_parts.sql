WITH RECURSIVE RelatedMails AS (
    -- Base case: select the initial mail(s) by id
    SELECT m.*
    FROM mail m
    WHERE m.id = :id
      AND m.type != ''

    UNION ALL

    -- Recursive case: select mails related to the current mails
    SELECT m.*
    FROM mail m
             INNER JOIN mail_mail mm ON m.id = mm.mail_id2
             INNER JOIN RelatedMails rm ON mm.mail_id1 = rm.id
    WHERE m.type != ''
)
SELECT *,
       CASE type
           WHEN 'application/pdf' THEN 'application-pdf'
           WHEN 'image/jpeg' THEN 'image-type'
           WHEN 'image/png' THEN 'image-type'
           WHEN 'image/gif' THEN 'image-type'
           WHEN 'text/html' THEN 'text-html'
           WHEN 'text/plain' THEN 'text-plain'
           ELSE NULL
           END AS svgtype,
       CASE type
           WHEN 'application/pdf' THEN 'pdf'
           WHEN 'image/jpeg' THEN 'jpeg'
           WHEN 'image/png' THEN 'png'
           WHEN 'image/gif' THEN 'gif'
           WHEN 'text/html' THEN 'html'
           WHEN 'text/plain' THEN 'text'
           ELSE SUBSTR(type, INSTR(type, '/') + 1)
           END AS shorttype
FROM RelatedMails where type NOT IN ('multipart/mixed', 'multipart/related', 'multipart/alternative');

