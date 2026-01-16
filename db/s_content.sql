select mail_content.*
from mail_content,
     mail
where mail.id = :id
  and mail_content.id = mail.mail_content_id;
