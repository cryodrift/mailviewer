select mail_header.*
from mail_header,
     mail_header_mail
where mail_header.id = mail_header_mail.mail_header_id
  and mail_header_mail.mail_id = :id;
