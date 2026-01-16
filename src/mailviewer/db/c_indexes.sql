create unique index if not exists mail_ofilename_unique
    on mail (ofilename);

create unique index if not exists mail_uuid_unique
    on mail (mail_uid);

create unique index if not exists mail_address_email_name_unique
    on mail_address (email, name);

create unique index if not exists mail_header_content_name_unique
    on mail_header (header_content, name);

create index if not exists mail_content_mail_id_unique
    on mail_content (contentmd5);

create index if not exists i_mail_header_mail
    on mail_header_mail (mail_header_id,mail_id);
