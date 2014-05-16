--
-- Sequence "contacts_google_seq"
-- Name: contact_google_ids; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE contacts_google_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

--
-- Table "contacts_google"
-- Name: contacts_google; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE contacts_google (
    contact_id integer DEFAULT nextval('contacts_google_seq'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint DEFAULT 0 NOT NULL,
    name varchar(128) DEFAULT '' NOT NULL,
    email text DEFAULT '' NOT NULL,
    firstname varchar(128) DEFAULT '' NOT NULL,
    surname varchar(128) DEFAULT '' NOT NULL,
    vcard text,
    words text
);

CREATE INDEX contacts_google_user_id_idx ON contacts_google (user_id, del);

