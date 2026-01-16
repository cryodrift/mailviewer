create table if not exists mail
(
    id              integer not null primary key autoincrement,
    date            datetime,
    subject         varchar,
    type            varchar,
    charset         varchar,
    encoding        varchar,
    boundary        varchar,
    filename        varchar,
    ofilename       varchar,
    cid             varchar,
    mail_uid        varchar unique,
    mail_content_id integer references mail_content on delete cascade
);

create table if not exists mail_address
(
    id    integer not null primary key autoincrement,
    email varchar not null,
    name  varchar not null,
    unique (name, email)
);


create table if not exists mail_header
(
    id             integer not null primary key autoincrement,
    name           varchar not null,
    header_content text    not null,
    unique (name, header_content)
);


create table if not exists mail_content
(
    id         integer not null primary key autoincrement,
    contentblob    blob    not null,
    contentlen text    not null,
    contentmd5 text    not null unique

);

create table if not exists mail_header_mail
(
    id             integer not null primary key autoincrement,
    mail_header_id integer references mail_header on delete cascade,
    mail_id        integer references mail on delete cascade,
    unique (mail_header_id, mail_id)
);

create table if not exists mail_address_mail
(
    id              integer not null primary key autoincrement,
    mail_id         integer not null references mail on delete cascade,
    mail_address_id integer not null references mail_address on delete cascade,
    type            varchar,
    unique (mail_id, mail_address_id, type)
);

create table if not exists mail_mail
(
    id       integer not null primary key autoincrement,
    mail_id1 integer not null references mail on delete cascade,
    mail_id2 integer not null references mail on delete cascade,
    unique (mail_id1, mail_id2)
);

create table if not exists host
(
    id   integer not null primary key autoincrement,
    name text unique,
    mailcount text
);

