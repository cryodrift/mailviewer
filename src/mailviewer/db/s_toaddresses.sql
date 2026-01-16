SELECT a2.*,
       a1.type
FROM mail_address_mail a1,
     mail_address a2
where a1.mail_id = :id
  and a1.mail_address_id = a2.id
  and a1.type = 'to';
