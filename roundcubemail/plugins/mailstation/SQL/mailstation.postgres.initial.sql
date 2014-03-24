-- MailStation initial database structure

-- Table "popinfo"
-- Name: popinfo; Type: TABLE; Schema: public; Owner: postgres
--
BEGIN;
CREATE TABLE popinfo (
    identity_id integer PRIMARY KEY,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    ext integer DEFAULT 0 NOT NULL,
    extusername varchar(128),
    extpd varchar(128),
    popserver varchar(128),
    popport integer DEFAULT 110 NOT NULL,
    ifssl integer DEFAULT 0 NOT NULL,
    select_folder varchar(128) DEFAULT 'INBOX',
    remove_mail integer DEFAULT 0 NOT NULL,
    select_smtp integer
);

--
-- Sequence "smtp_ids"
-- Name: smtp_ids; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE smtp_ids
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

--
-- Table "smtp"
-- Name: smtp; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE smtp (
    smtp_id integer DEFAULT nextval('smtp_ids'::text) PRIMARY KEY,
    user_id integer NOT NULL
	REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint DEFAULT 0 NOT NULL,
    smtpdesc varchar(128) ,
    smtpserver varchar(128) NOT NULL,
    smtpport integer NOT NULL,
    smtpuser varchar(128) NOT NULL,
    smtppass varchar(128) NOT NULL,
    iftls integer DEFAULT 0 NOT NULL,
    ifdefault integer DEFAULT 0 NOT NULL

);

CREATE INDEX smtp_user_id_idx ON smtp (user_id, del);

--
-- Sequence "account_type_ids"
-- Name: account_type_ids; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE users_type_ids
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

--
-- Table "users_type"
-- Name: users_type; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE users_type (
    users_type_id integer DEFAULT nextval('users_type_ids'::text) PRIMARY KEY,
    user_id integer NOT NULL
	REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    account_type varchar(128) DEFAULT 'local' NOT NULL,
    domain_name varchar(512) DEFAULT '' NOT NULL

);

CREATE INDEX users_type_user_id_idx ON users_type (users_type_id, account_type, domain_name);
END;
