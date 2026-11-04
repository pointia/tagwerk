--
-- PostgreSQL database dump
--


-- Dumped from database version 16.11 (Debian 16.11-1.pgdg13+1)
-- Dumped by pg_dump version 16.15 (Debian 16.15-1.pgdg13+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: calendar_person; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.calendar_person (
    id bigint NOT NULL,
    calendar_id bigint NOT NULL,
    person_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: calendar_person_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.calendar_person_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: calendar_person_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.calendar_person_id_seq OWNED BY public.calendar_person.id;


--
-- Name: provider_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.provider_accounts (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    user_id bigint,
    account_label character varying(255) NOT NULL,
    access_token text,
    refresh_token text,
    token_expires_at timestamp(0) with time zone,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    synced_at timestamp(0) with time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    provider character varying(255) NOT NULL,
    settings json,
    area character varying(255) DEFAULT 'calendar'::character varying NOT NULL,
    credentials text
);


--
-- Name: calendar_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.calendar_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: calendar_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.calendar_sources_id_seq OWNED BY public.provider_accounts.id;


--
-- Name: calendars; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.calendars (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    provider_account_id bigint NOT NULL,
    external_id character varying(255) NOT NULL,
    summary character varying(255) NOT NULL,
    color character varying(7),
    timezone character varying(255),
    is_primary boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sync_cursor text,
    push_channel_id character varying(255),
    push_resource_id character varying(255),
    push_expires_at timestamp(0) with time zone,
    last_synced_at timestamp(0) with time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: calendars_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.calendars_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: calendars_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.calendars_id_seq OWNED BY public.calendars.id;


--
-- Name: catalog_recipes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.catalog_recipes (
    id bigint NOT NULL,
    source character varying(255) NOT NULL,
    source_id character varying(255) NOT NULL,
    revision character varying(255),
    title character varying(255) NOT NULL,
    intro text,
    steps text,
    ingredients jsonb DEFAULT '[]'::jsonb NOT NULL,
    servings smallint DEFAULT '4'::smallint NOT NULL,
    minutes smallint,
    difficulty character varying(16),
    categories jsonb DEFAULT '[]'::jsonb NOT NULL,
    vegetarian boolean,
    photo jsonb,
    source_url character varying(2048),
    license jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: catalog_recipes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.catalog_recipes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: catalog_recipes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.catalog_recipes_id_seq OWNED BY public.catalog_recipes.id;


--
-- Name: dashboard_widgets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dashboard_widgets (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    type character varying(255) NOT NULL,
    x smallint DEFAULT '0'::smallint NOT NULL,
    y smallint DEFAULT '0'::smallint NOT NULL,
    w smallint DEFAULT '4'::smallint NOT NULL,
    h smallint DEFAULT '3'::smallint NOT NULL,
    settings json,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    device_id bigint,
    user_id bigint,
    CONSTRAINT dashboard_widgets_one_owner CHECK (((device_id IS NULL) <> (user_id IS NULL)))
);


--
-- Name: dashboard_widgets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dashboard_widgets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dashboard_widgets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dashboard_widgets_id_seq OWNED BY public.dashboard_widgets.id;


--
-- Name: devices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.devices (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    kind character varying(255) DEFAULT 'wall'::character varying NOT NULL,
    pairing_code character varying(12),
    pairing_expires_at timestamp(0) with time zone,
    paired_at timestamp(0) with time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    user_id bigint,
    push_endpoint text,
    push_p256dh character varying(255),
    push_auth character varying(255),
    push_subscribed_at timestamp(0) with time zone,
    theme_preference character varying(255) DEFAULT 'auto'::character varying NOT NULL,
    preferences json,
    permissions jsonb,
    hidden_calendar_ids jsonb,
    locale character varying(5)
);


--
-- Name: devices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.devices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: devices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.devices_id_seq OWNED BY public.devices.id;


--
-- Name: event_person; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_person (
    id bigint NOT NULL,
    event_id bigint NOT NULL,
    person_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: event_person_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_person_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_person_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_person_id_seq OWNED BY public.event_person.id;


--
-- Name: events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.events (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    calendar_id bigint NOT NULL,
    external_id character varying(255),
    external_version character varying(255),
    recurring_event_id character varying(255),
    title character varying(255) NOT NULL,
    description text,
    location character varying(255),
    starts_at timestamp(0) with time zone NOT NULL,
    ends_at timestamp(0) with time zone NOT NULL,
    all_day boolean DEFAULT false NOT NULL,
    origin character varying(255) DEFAULT 'google'::character varying NOT NULL,
    revision_marker character varying(255),
    external_updated_at timestamp(0) with time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.events_id_seq OWNED BY public.events.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: households; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.households (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    timezone character varying(255) DEFAULT 'Europe/Vienna'::character varying NOT NULL,
    holiday_region character varying(255) DEFAULT 'AT'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    theme_mode character varying(255) DEFAULT 'fixed'::character varying NOT NULL,
    theme_light_at time(0) without time zone DEFAULT '07:00:00'::time without time zone NOT NULL,
    theme_dark_at time(0) without time zone DEFAULT '20:00:00'::time without time zone NOT NULL,
    latitude numeric(7,4),
    longitude numeric(7,4),
    sunrise_offset smallint DEFAULT '0'::smallint NOT NULL,
    sunset_offset smallint DEFAULT '0'::smallint NOT NULL,
    summary_at time(0) without time zone DEFAULT '19:30:00'::time without time zone NOT NULL,
    summary_sent_on date,
    disabled_features json,
    address character varying(255),
    suspended_at timestamp(0) without time zone,
    photo_rule character varying(255),
    locale character varying(5) DEFAULT 'de'::character varying NOT NULL,
    street character varying(255),
    postal_code character varying(16),
    city character varying(255),
    country_code character(2),
    day_bonus smallint DEFAULT '0'::smallint NOT NULL,
    pantry_staples jsonb,
    meal_kinds json,
    backdrop character varying(255) DEFAULT 'dawn'::character varying NOT NULL,
    opted_in_features json
);


--
-- Name: households_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.households_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: households_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.households_id_seq OWNED BY public.households.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: lesson_changes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lesson_changes (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    lesson_id bigint NOT NULL,
    date date NOT NULL,
    kind character varying(255) NOT NULL,
    title character varying(255),
    starts_at time(0) without time zone,
    ends_at time(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: lesson_changes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lesson_changes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lesson_changes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lesson_changes_id_seq OWNED BY public.lesson_changes.id;


--
-- Name: lesson_periods; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lesson_periods (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    person_id bigint NOT NULL,
    number smallint NOT NULL,
    starts_at time(0) without time zone NOT NULL,
    ends_at time(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: lesson_periods_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lesson_periods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lesson_periods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lesson_periods_id_seq OWNED BY public.lesson_periods.id;


--
-- Name: lessons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lessons (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    person_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    weekday smallint NOT NULL,
    period_number smallint,
    starts_at time(0) without time zone,
    ends_at time(0) without time zone,
    "interval" smallint DEFAULT '1'::smallint NOT NULL,
    cadence character varying(255) DEFAULT 'calendar'::character varying NOT NULL,
    anchor_on date NOT NULL,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    color character varying(7),
    CONSTRAINT lessons_period_or_time CHECK ((((period_number IS NOT NULL) AND (starts_at IS NULL) AND (ends_at IS NULL)) OR ((period_number IS NULL) AND (starts_at IS NOT NULL) AND (ends_at IS NOT NULL))))
);


--
-- Name: lessons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lessons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lessons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lessons_id_seq OWNED BY public.lessons.id;


--
-- Name: list_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.list_items (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    list_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    quantity character varying(255),
    done_at timestamp(0) with time zone,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: list_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.list_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: list_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.list_items_id_seq OWNED BY public.list_items.id;


--
-- Name: lists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lists (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    kind character varying(255) DEFAULT 'generic'::character varying NOT NULL,
    color character varying(7),
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    list_provider character varying(255),
    list_account_id bigint,
    list_target character varying(255)
);


--
-- Name: lists_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lists_id_seq OWNED BY public.lists.id;


--
-- Name: meal_plan_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.meal_plan_entries (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    planned_on date NOT NULL,
    kind character varying(255) NOT NULL,
    recipe_id character varying(255),
    title character varying(255) NOT NULL,
    notes character varying(255),
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    recipe_provider character varying(255),
    eaters jsonb
);


--
-- Name: meal_plan_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.meal_plan_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: meal_plan_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.meal_plan_entries_id_seq OWNED BY public.meal_plan_entries.id;


--
-- Name: memberships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.memberships (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    household_id bigint NOT NULL,
    person_id bigint,
    preferences json,
    accepted_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: memberships_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.memberships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: memberships_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.memberships_id_seq OWNED BY public.memberships.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: people; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.people (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    color character varying(7) NOT NULL,
    role character varying(255) DEFAULT 'child'::character varying NOT NULL,
    birthday date,
    avatar_path character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    weekly_goal integer DEFAULT 0 NOT NULL,
    savings_reward_id bigint,
    permissions jsonb,
    "position" smallint,
    gender character varying(16)
);


--
-- Name: people_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.people_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: people_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.people_id_seq OWNED BY public.people.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    household_id bigint
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: photo_album_photo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.photo_album_photo (
    photo_album_id bigint NOT NULL,
    photo_id bigint NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: photo_albums; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.photo_albums (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: photo_albums_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.photo_albums_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: photo_albums_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.photo_albums_id_seq OWNED BY public.photo_albums.id;


--
-- Name: photo_dates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.photo_dates (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    provider_account_id bigint NOT NULL,
    path text NOT NULL,
    taken_on date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: photo_dates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.photo_dates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: photo_dates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.photo_dates_id_seq OWNED BY public.photo_dates.id;


--
-- Name: photos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.photos (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    path character varying(255) NOT NULL,
    caption character varying(255),
    taken_on date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    fingerprint character varying(64),
    user_id bigint,
    deleted_at timestamp(0) without time zone,
    favorite boolean DEFAULT false NOT NULL
);


--
-- Name: photos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.photos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: photos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.photos_id_seq OWNED BY public.photos.id;


--
-- Name: plugin_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plugin_settings (
    key character varying(255) NOT NULL,
    enabled boolean DEFAULT false NOT NULL,
    settings jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: point_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.point_entries (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    person_id bigint NOT NULL,
    points integer NOT NULL,
    reason character varying(255) NOT NULL,
    task_completion_id bigint,
    reward_redemption_id bigint,
    created_by_user_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    bonus_on date,
    gift_id uuid
);


--
-- Name: point_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.point_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: point_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.point_entries_id_seq OWNED BY public.point_entries.id;


--
-- Name: recipe_favorites; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recipe_favorites (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    provider character varying(255) NOT NULL,
    recipe_id character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: recipe_favorites_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recipe_favorites_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recipe_favorites_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recipe_favorites_id_seq OWNED BY public.recipe_favorites.id;


--
-- Name: recipes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recipes (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    notes text,
    ingredients jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    intro text,
    steps text,
    servings smallint DEFAULT '4'::smallint NOT NULL,
    minutes smallint,
    difficulty character varying(16),
    categories jsonb DEFAULT '[]'::jsonb NOT NULL,
    vegetarian boolean,
    photo_path character varying(255),
    source character varying(255),
    source_id character varying(255),
    source_url character varying(2048),
    license jsonb,
    pages json
);


--
-- Name: recipes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recipes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recipes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recipes_id_seq OWNED BY public.recipes.id;


--
-- Name: reward_redemptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reward_redemptions (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    reward_id bigint NOT NULL,
    person_id bigint NOT NULL,
    cost integer NOT NULL,
    approved_at timestamp(0) with time zone,
    rejected_at timestamp(0) with time zone,
    approved_by_user_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: reward_redemptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reward_redemptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reward_redemptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reward_redemptions_id_seq OWNED BY public.reward_redemptions.id;


--
-- Name: rewards; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rewards (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    cost integer NOT NULL,
    active boolean DEFAULT true NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    icon character varying(16)
);


--
-- Name: rewards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rewards_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rewards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rewards_id_seq OWNED BY public.rewards.id;


--
-- Name: school_closures; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.school_closures (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    person_id bigint,
    title character varying(255) NOT NULL,
    starts_on date NOT NULL,
    ends_on date NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: school_closures_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.school_closures_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: school_closures_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.school_closures_id_seq OWNED BY public.school_closures.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: support_accesses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.support_accesses (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    household_id bigint NOT NULL,
    created_at timestamp(0) with time zone NOT NULL
);


--
-- Name: support_accesses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.support_accesses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: support_accesses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.support_accesses_id_seq OWNED BY public.support_accesses.id;


--
-- Name: task_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_assignments (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    task_id bigint NOT NULL,
    person_id bigint NOT NULL,
    occurred_on date NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: task_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_assignments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_assignments_id_seq OWNED BY public.task_assignments.id;


--
-- Name: task_completions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.task_completions (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    task_id bigint NOT NULL,
    person_id bigint,
    occurred_on date NOT NULL,
    completed_at timestamp(0) with time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    for_person_id bigint,
    daypart character varying(8),
    skipped boolean DEFAULT false NOT NULL
);


--
-- Name: task_completions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.task_completions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: task_completions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.task_completions_id_seq OWNED BY public.task_completions.id;


--
-- Name: tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tasks (
    id bigint NOT NULL,
    household_id bigint NOT NULL,
    person_id bigint,
    title character varying(255) NOT NULL,
    notes text,
    due_on date,
    rrule character varying(255),
    points integer DEFAULT 0 NOT NULL,
    active boolean DEFAULT true NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    rotation json,
    provider character varying(255) DEFAULT 'builtin'::character varying NOT NULL,
    task_account_id bigint,
    external_id character varying(255),
    external_version character varying(255),
    icon character varying(16),
    per_person boolean DEFAULT false NOT NULL,
    per_person_ids jsonb,
    dayparts jsonb,
    skip_holidays boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone
);


--
-- Name: tasks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tasks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tasks_id_seq OWNED BY public.tasks.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    household_id bigint,
    person_id bigint,
    theme_preference character varying(255) DEFAULT 'auto'::character varying NOT NULL,
    preferences json,
    is_operator boolean DEFAULT false NOT NULL,
    hidden_calendar_ids jsonb,
    pin_hash character varying(255),
    locale character varying(5),
    app_authentication_secret text,
    app_authentication_recovery_codes text
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: calendar_person id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_person ALTER COLUMN id SET DEFAULT nextval('public.calendar_person_id_seq'::regclass);


--
-- Name: calendars id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendars ALTER COLUMN id SET DEFAULT nextval('public.calendars_id_seq'::regclass);


--
-- Name: catalog_recipes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.catalog_recipes ALTER COLUMN id SET DEFAULT nextval('public.catalog_recipes_id_seq'::regclass);


--
-- Name: dashboard_widgets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_widgets ALTER COLUMN id SET DEFAULT nextval('public.dashboard_widgets_id_seq'::regclass);


--
-- Name: devices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devices ALTER COLUMN id SET DEFAULT nextval('public.devices_id_seq'::regclass);


--
-- Name: event_person id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_person ALTER COLUMN id SET DEFAULT nextval('public.event_person_id_seq'::regclass);


--
-- Name: events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events ALTER COLUMN id SET DEFAULT nextval('public.events_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: households id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.households ALTER COLUMN id SET DEFAULT nextval('public.households_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: lesson_changes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_changes ALTER COLUMN id SET DEFAULT nextval('public.lesson_changes_id_seq'::regclass);


--
-- Name: lesson_periods id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_periods ALTER COLUMN id SET DEFAULT nextval('public.lesson_periods_id_seq'::regclass);


--
-- Name: lessons id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lessons ALTER COLUMN id SET DEFAULT nextval('public.lessons_id_seq'::regclass);


--
-- Name: list_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items ALTER COLUMN id SET DEFAULT nextval('public.list_items_id_seq'::regclass);


--
-- Name: lists id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lists ALTER COLUMN id SET DEFAULT nextval('public.lists_id_seq'::regclass);


--
-- Name: meal_plan_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meal_plan_entries ALTER COLUMN id SET DEFAULT nextval('public.meal_plan_entries_id_seq'::regclass);


--
-- Name: memberships id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.memberships ALTER COLUMN id SET DEFAULT nextval('public.memberships_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: people id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.people ALTER COLUMN id SET DEFAULT nextval('public.people_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: photo_albums id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_albums ALTER COLUMN id SET DEFAULT nextval('public.photo_albums_id_seq'::regclass);


--
-- Name: photo_dates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_dates ALTER COLUMN id SET DEFAULT nextval('public.photo_dates_id_seq'::regclass);


--
-- Name: photos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photos ALTER COLUMN id SET DEFAULT nextval('public.photos_id_seq'::regclass);


--
-- Name: point_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.point_entries ALTER COLUMN id SET DEFAULT nextval('public.point_entries_id_seq'::regclass);


--
-- Name: provider_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provider_accounts ALTER COLUMN id SET DEFAULT nextval('public.calendar_sources_id_seq'::regclass);


--
-- Name: recipe_favorites id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recipe_favorites ALTER COLUMN id SET DEFAULT nextval('public.recipe_favorites_id_seq'::regclass);


--
-- Name: recipes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recipes ALTER COLUMN id SET DEFAULT nextval('public.recipes_id_seq'::regclass);


--
-- Name: reward_redemptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reward_redemptions ALTER COLUMN id SET DEFAULT nextval('public.reward_redemptions_id_seq'::regclass);


--
-- Name: rewards id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rewards ALTER COLUMN id SET DEFAULT nextval('public.rewards_id_seq'::regclass);


--
-- Name: school_closures id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.school_closures ALTER COLUMN id SET DEFAULT nextval('public.school_closures_id_seq'::regclass);


--
-- Name: support_accesses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.support_accesses ALTER COLUMN id SET DEFAULT nextval('public.support_accesses_id_seq'::regclass);


--
-- Name: task_assignments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_assignments ALTER COLUMN id SET DEFAULT nextval('public.task_assignments_id_seq'::regclass);


--
-- Name: task_completions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_completions ALTER COLUMN id SET DEFAULT nextval('public.task_completions_id_seq'::regclass);


--
-- Name: tasks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks ALTER COLUMN id SET DEFAULT nextval('public.tasks_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: calendar_person calendar_person_calendar_id_person_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_person
    ADD CONSTRAINT calendar_person_calendar_id_person_id_unique UNIQUE (calendar_id, person_id);


--
-- Name: calendar_person calendar_person_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_person
    ADD CONSTRAINT calendar_person_pkey PRIMARY KEY (id);


--
-- Name: provider_accounts calendar_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provider_accounts
    ADD CONSTRAINT calendar_sources_pkey PRIMARY KEY (id);


--
-- Name: calendars calendars_calendar_source_id_google_calendar_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendars
    ADD CONSTRAINT calendars_calendar_source_id_google_calendar_id_unique UNIQUE (provider_account_id, external_id);


--
-- Name: calendars calendars_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendars
    ADD CONSTRAINT calendars_pkey PRIMARY KEY (id);


--
-- Name: catalog_recipes catalog_recipes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.catalog_recipes
    ADD CONSTRAINT catalog_recipes_pkey PRIMARY KEY (id);


--
-- Name: catalog_recipes catalog_recipes_source_source_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.catalog_recipes
    ADD CONSTRAINT catalog_recipes_source_source_id_unique UNIQUE (source, source_id);


--
-- Name: dashboard_widgets dashboard_widgets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_widgets
    ADD CONSTRAINT dashboard_widgets_pkey PRIMARY KEY (id);


--
-- Name: devices devices_pairing_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devices
    ADD CONSTRAINT devices_pairing_code_unique UNIQUE (pairing_code);


--
-- Name: devices devices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devices
    ADD CONSTRAINT devices_pkey PRIMARY KEY (id);


--
-- Name: event_person event_person_event_id_person_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_person
    ADD CONSTRAINT event_person_event_id_person_id_unique UNIQUE (event_id, person_id);


--
-- Name: event_person event_person_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_person
    ADD CONSTRAINT event_person_pkey PRIMARY KEY (id);


--
-- Name: events events_calendar_id_google_event_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_calendar_id_google_event_id_unique UNIQUE (calendar_id, external_id);


--
-- Name: events events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: households households_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.households
    ADD CONSTRAINT households_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: lesson_changes lesson_changes_lesson_id_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_changes
    ADD CONSTRAINT lesson_changes_lesson_id_date_unique UNIQUE (lesson_id, date);


--
-- Name: lesson_changes lesson_changes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_changes
    ADD CONSTRAINT lesson_changes_pkey PRIMARY KEY (id);


--
-- Name: lesson_periods lesson_periods_person_id_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_periods
    ADD CONSTRAINT lesson_periods_person_id_number_unique UNIQUE (person_id, number);


--
-- Name: lesson_periods lesson_periods_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_periods
    ADD CONSTRAINT lesson_periods_pkey PRIMARY KEY (id);


--
-- Name: lessons lessons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lessons
    ADD CONSTRAINT lessons_pkey PRIMARY KEY (id);


--
-- Name: list_items list_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items
    ADD CONSTRAINT list_items_pkey PRIMARY KEY (id);


--
-- Name: lists lists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lists
    ADD CONSTRAINT lists_pkey PRIMARY KEY (id);


--
-- Name: meal_plan_entries meal_plan_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meal_plan_entries
    ADD CONSTRAINT meal_plan_entries_pkey PRIMARY KEY (id);


--
-- Name: memberships memberships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_pkey PRIMARY KEY (id);


--
-- Name: memberships memberships_user_id_household_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_user_id_household_id_unique UNIQUE (user_id, household_id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: people people_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.people
    ADD CONSTRAINT people_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: photo_album_photo photo_album_photo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_album_photo
    ADD CONSTRAINT photo_album_photo_pkey PRIMARY KEY (photo_album_id, photo_id);


--
-- Name: photo_albums photo_albums_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_albums
    ADD CONSTRAINT photo_albums_pkey PRIMARY KEY (id);


--
-- Name: photo_dates photo_dates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_dates
    ADD CONSTRAINT photo_dates_pkey PRIMARY KEY (id);


--
-- Name: photo_dates photo_dates_provider_account_id_path_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_dates
    ADD CONSTRAINT photo_dates_provider_account_id_path_unique UNIQUE (provider_account_id, path);


--
-- Name: photos photos_household_id_fingerprint_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photos
    ADD CONSTRAINT photos_household_id_fingerprint_unique UNIQUE (household_id, fingerprint);


--
-- Name: photos photos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photos
    ADD CONSTRAINT photos_pkey PRIMARY KEY (id);


--
-- Name: plugin_settings plugin_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plugin_settings
    ADD CONSTRAINT plugin_settings_pkey PRIMARY KEY (key);


--
-- Name: point_entries point_entries_person_id_bonus_on_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.point_entries
    ADD CONSTRAINT point_entries_person_id_bonus_on_unique UNIQUE (person_id, bonus_on);


--
-- Name: point_entries point_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.point_entries
    ADD CONSTRAINT point_entries_pkey PRIMARY KEY (id);


--
-- Name: point_entries point_entries_reward_redemption_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.point_entries
    ADD CONSTRAINT point_entries_reward_redemption_id_unique UNIQUE (reward_redemption_id);


--
-- Name: provider_accounts provider_accounts_household_id_area_provider_account_label_uniq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provider_accounts
    ADD CONSTRAINT provider_accounts_household_id_area_provider_account_label_uniq UNIQUE (household_id, area, provider, account_label);


--
-- Name: recipe_favorites recipe_favorites_household_id_provider_recipe_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recipe_favorites
    ADD CONSTRAINT recipe_favorites_household_id_provider_recipe_id_unique UNIQUE (household_id, provider, recipe_id);


--
-- Name: recipe_favorites recipe_favorites_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recipe_favorites
    ADD CONSTRAINT recipe_favorites_pkey PRIMARY KEY (id);


--
-- Name: recipes recipes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recipes
    ADD CONSTRAINT recipes_pkey PRIMARY KEY (id);


--
-- Name: reward_redemptions reward_redemptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reward_redemptions
    ADD CONSTRAINT reward_redemptions_pkey PRIMARY KEY (id);


--
-- Name: rewards rewards_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rewards
    ADD CONSTRAINT rewards_pkey PRIMARY KEY (id);


--
-- Name: school_closures school_closures_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.school_closures
    ADD CONSTRAINT school_closures_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: support_accesses support_accesses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.support_accesses
    ADD CONSTRAINT support_accesses_pkey PRIMARY KEY (id);


--
-- Name: task_assignments task_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_assignments
    ADD CONSTRAINT task_assignments_pkey PRIMARY KEY (id);


--
-- Name: task_assignments task_assignments_task_id_occurred_on_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_assignments
    ADD CONSTRAINT task_assignments_task_id_occurred_on_unique UNIQUE (task_id, occurred_on);


--
-- Name: task_completions task_completions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_completions
    ADD CONSTRAINT task_completions_pkey PRIMARY KEY (id);


--
-- Name: tasks tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_pkey PRIMARY KEY (id);


--
-- Name: tasks tasks_task_account_id_external_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_task_account_id_external_id_unique UNIQUE (task_account_id, external_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: calendar_sources_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX calendar_sources_household_id_index ON public.provider_accounts USING btree (household_id);


--
-- Name: calendars_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX calendars_household_id_index ON public.calendars USING btree (household_id);


--
-- Name: calendars_watch_channel_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX calendars_watch_channel_id_index ON public.calendars USING btree (push_channel_id);


--
-- Name: catalog_recipes_search; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX catalog_recipes_search ON public.catalog_recipes USING gin (to_tsvector('german'::regconfig, (((((COALESCE(title, ''::character varying))::text || ' '::text) || COALESCE((categories)::text, ''::text)) || ' '::text) || COALESCE((jsonb_path_query_array(ingredients, '$[*]."name"'::jsonpath))::text, ''::text))));


--
-- Name: dashboard_widgets_device_id_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboard_widgets_device_id_position_index ON public.dashboard_widgets USING btree (device_id, "position");


--
-- Name: dashboard_widgets_user_id_position_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dashboard_widgets_user_id_position_index ON public.dashboard_widgets USING btree (user_id, "position");


--
-- Name: devices_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX devices_household_id_index ON public.devices USING btree (household_id);


--
-- Name: events_calendar_id_starts_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_calendar_id_starts_at_index ON public.events USING btree (calendar_id, starts_at);


--
-- Name: events_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_household_id_index ON public.events USING btree (household_id);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: lessons_household_id_person_id_weekday_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lessons_household_id_person_id_weekday_index ON public.lessons USING btree (household_id, person_id, weekday);


--
-- Name: list_items_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX list_items_household_id_index ON public.list_items USING btree (household_id);


--
-- Name: list_items_list_id_done_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX list_items_list_id_done_at_index ON public.list_items USING btree (list_id, done_at);


--
-- Name: lists_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lists_household_id_index ON public.lists USING btree (household_id);


--
-- Name: meal_plan_entries_household_id_planned_on_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX meal_plan_entries_household_id_planned_on_index ON public.meal_plan_entries USING btree (household_id, planned_on);


--
-- Name: memberships_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX memberships_household_id_index ON public.memberships USING btree (household_id);


--
-- Name: memberships_person_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX memberships_person_id_index ON public.memberships USING btree (person_id);


--
-- Name: people_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX people_household_id_index ON public.people USING btree (household_id);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: photos_deleted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX photos_deleted_at_index ON public.photos USING btree (deleted_at);


--
-- Name: photos_household_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX photos_household_id_created_at_index ON public.photos USING btree (household_id, created_at);


--
-- Name: point_entries_gift_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX point_entries_gift_id_index ON public.point_entries USING btree (gift_id);


--
-- Name: point_entries_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX point_entries_household_id_index ON public.point_entries USING btree (household_id);


--
-- Name: point_entries_person_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX point_entries_person_id_created_at_index ON public.point_entries USING btree (person_id, created_at);


--
-- Name: recipes_household_id_title_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX recipes_household_id_title_index ON public.recipes USING btree (household_id, title);


--
-- Name: recipes_search; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX recipes_search ON public.recipes USING gin (to_tsvector('german'::regconfig, (((((COALESCE(title, ''::character varying))::text || ' '::text) || COALESCE((categories)::text, ''::text)) || ' '::text) || COALESCE((jsonb_path_query_array(ingredients, '$[*]."name"'::jsonpath))::text, ''::text))));


--
-- Name: reward_redemptions_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reward_redemptions_household_id_index ON public.reward_redemptions USING btree (household_id);


--
-- Name: reward_redemptions_person_id_approved_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reward_redemptions_person_id_approved_at_index ON public.reward_redemptions USING btree (person_id, approved_at);


--
-- Name: rewards_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX rewards_household_id_index ON public.rewards USING btree (household_id);


--
-- Name: school_closures_household_id_starts_on_ends_on_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX school_closures_household_id_starts_on_ends_on_index ON public.school_closures USING btree (household_id, starts_on, ends_on);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: support_accesses_household_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX support_accesses_household_id_created_at_index ON public.support_accesses USING btree (household_id, created_at);


--
-- Name: task_assignments_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX task_assignments_household_id_index ON public.task_assignments USING btree (household_id);


--
-- Name: task_completions_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX task_completions_household_id_index ON public.task_completions USING btree (household_id);


--
-- Name: task_completions_occurrence_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX task_completions_occurrence_unique ON public.task_completions USING btree (task_id, occurred_on, for_person_id, daypart) NULLS NOT DISTINCT;


--
-- Name: tasks_active_due_on_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_active_due_on_index ON public.tasks USING btree (active, due_on);


--
-- Name: tasks_deleted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_deleted_at_index ON public.tasks USING btree (deleted_at);


--
-- Name: tasks_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tasks_household_id_index ON public.tasks USING btree (household_id);


--
-- Name: users_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_household_id_index ON public.users USING btree (household_id);


--
-- Name: calendar_person calendar_person_calendar_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_person
    ADD CONSTRAINT calendar_person_calendar_id_foreign FOREIGN KEY (calendar_id) REFERENCES public.calendars(id) ON DELETE CASCADE;


--
-- Name: calendar_person calendar_person_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendar_person
    ADD CONSTRAINT calendar_person_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE CASCADE;


--
-- Name: provider_accounts calendar_sources_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provider_accounts
    ADD CONSTRAINT calendar_sources_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: provider_accounts calendar_sources_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provider_accounts
    ADD CONSTRAINT calendar_sources_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: calendars calendars_calendar_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendars
    ADD CONSTRAINT calendars_calendar_source_id_foreign FOREIGN KEY (provider_account_id) REFERENCES public.provider_accounts(id) ON DELETE CASCADE;


--
-- Name: calendars calendars_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendars
    ADD CONSTRAINT calendars_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: dashboard_widgets dashboard_widgets_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_widgets
    ADD CONSTRAINT dashboard_widgets_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.devices(id) ON DELETE CASCADE;


--
-- Name: dashboard_widgets dashboard_widgets_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_widgets
    ADD CONSTRAINT dashboard_widgets_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: dashboard_widgets dashboard_widgets_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dashboard_widgets
    ADD CONSTRAINT dashboard_widgets_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: devices devices_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devices
    ADD CONSTRAINT devices_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: devices devices_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devices
    ADD CONSTRAINT devices_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: event_person event_person_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_person
    ADD CONSTRAINT event_person_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: event_person event_person_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_person
    ADD CONSTRAINT event_person_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE CASCADE;


--
-- Name: events events_calendar_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_calendar_id_foreign FOREIGN KEY (calendar_id) REFERENCES public.calendars(id) ON DELETE CASCADE;


--
-- Name: events events_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: lesson_changes lesson_changes_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_changes
    ADD CONSTRAINT lesson_changes_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: lesson_changes lesson_changes_lesson_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_changes
    ADD CONSTRAINT lesson_changes_lesson_id_foreign FOREIGN KEY (lesson_id) REFERENCES public.lessons(id) ON DELETE CASCADE;


--
-- Name: lesson_periods lesson_periods_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_periods
    ADD CONSTRAINT lesson_periods_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: lesson_periods lesson_periods_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lesson_periods
    ADD CONSTRAINT lesson_periods_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE CASCADE;


--
-- Name: lessons lessons_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lessons
    ADD CONSTRAINT lessons_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: lessons lessons_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lessons
    ADD CONSTRAINT lessons_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE CASCADE;


--
-- Name: list_items list_items_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items
    ADD CONSTRAINT list_items_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: list_items list_items_list_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.list_items
    ADD CONSTRAINT list_items_list_id_foreign FOREIGN KEY (list_id) REFERENCES public.lists(id) ON DELETE CASCADE;


--
-- Name: lists lists_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lists
    ADD CONSTRAINT lists_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: lists lists_list_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lists
    ADD CONSTRAINT lists_list_account_id_foreign FOREIGN KEY (list_account_id) REFERENCES public.provider_accounts(id) ON DELETE SET NULL;


--
-- Name: meal_plan_entries meal_plan_entries_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.meal_plan_entries
    ADD CONSTRAINT meal_plan_entries_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: memberships memberships_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: memberships memberships_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE SET NULL;


--
-- Name: memberships memberships_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: people people_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.people
    ADD CONSTRAINT people_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: people people_savings_reward_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.people
    ADD CONSTRAINT people_savings_reward_id_foreign FOREIGN KEY (savings_reward_id) REFERENCES public.rewards(id) ON DELETE SET NULL;


--
-- Name: personal_access_tokens personal_access_tokens_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE SET NULL;


--
-- Name: photo_album_photo photo_album_photo_photo_album_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_album_photo
    ADD CONSTRAINT photo_album_photo_photo_album_id_foreign FOREIGN KEY (photo_album_id) REFERENCES public.photo_albums(id) ON DELETE CASCADE;


--
-- Name: photo_album_photo photo_album_photo_photo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_album_photo
    ADD CONSTRAINT photo_album_photo_photo_id_foreign FOREIGN KEY (photo_id) REFERENCES public.photos(id) ON DELETE CASCADE;


--
-- Name: photo_albums photo_albums_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_albums
    ADD CONSTRAINT photo_albums_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: photo_dates photo_dates_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_dates
    ADD CONSTRAINT photo_dates_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: photo_dates photo_dates_provider_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photo_dates
    ADD CONSTRAINT photo_dates_provider_account_id_foreign FOREIGN KEY (provider_account_id) REFERENCES public.provider_accounts(id) ON DELETE CASCADE;


--
-- Name: photos photos_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photos
    ADD CONSTRAINT photos_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: photos photos_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.photos
    ADD CONSTRAINT photos_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: point_entries point_entries_created_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.point_entries
    ADD CONSTRAINT point_entries_created_by_user_id_foreign FOREIGN KEY (created_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: point_entries point_entries_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.point_entries
    ADD CONSTRAINT point_entries_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: point_entries point_entries_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.point_entries
    ADD CONSTRAINT point_entries_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE CASCADE;


--
-- Name: point_entries point_entries_task_completion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.point_entries
    ADD CONSTRAINT point_entries_task_completion_id_foreign FOREIGN KEY (task_completion_id) REFERENCES public.task_completions(id) ON DELETE CASCADE;


--
-- Name: recipe_favorites recipe_favorites_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recipe_favorites
    ADD CONSTRAINT recipe_favorites_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: recipes recipes_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recipes
    ADD CONSTRAINT recipes_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: reward_redemptions reward_redemptions_approved_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reward_redemptions
    ADD CONSTRAINT reward_redemptions_approved_by_user_id_foreign FOREIGN KEY (approved_by_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: reward_redemptions reward_redemptions_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reward_redemptions
    ADD CONSTRAINT reward_redemptions_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: reward_redemptions reward_redemptions_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reward_redemptions
    ADD CONSTRAINT reward_redemptions_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE CASCADE;


--
-- Name: reward_redemptions reward_redemptions_reward_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reward_redemptions
    ADD CONSTRAINT reward_redemptions_reward_id_foreign FOREIGN KEY (reward_id) REFERENCES public.rewards(id) ON DELETE CASCADE;


--
-- Name: rewards rewards_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rewards
    ADD CONSTRAINT rewards_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: school_closures school_closures_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.school_closures
    ADD CONSTRAINT school_closures_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: school_closures school_closures_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.school_closures
    ADD CONSTRAINT school_closures_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE CASCADE;


--
-- Name: support_accesses support_accesses_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.support_accesses
    ADD CONSTRAINT support_accesses_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: support_accesses support_accesses_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.support_accesses
    ADD CONSTRAINT support_accesses_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: task_assignments task_assignments_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_assignments
    ADD CONSTRAINT task_assignments_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: task_assignments task_assignments_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_assignments
    ADD CONSTRAINT task_assignments_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE CASCADE;


--
-- Name: task_assignments task_assignments_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_assignments
    ADD CONSTRAINT task_assignments_task_id_foreign FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE CASCADE;


--
-- Name: task_completions task_completions_for_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_completions
    ADD CONSTRAINT task_completions_for_person_id_foreign FOREIGN KEY (for_person_id) REFERENCES public.people(id) ON DELETE CASCADE;


--
-- Name: task_completions task_completions_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_completions
    ADD CONSTRAINT task_completions_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: task_completions task_completions_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_completions
    ADD CONSTRAINT task_completions_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE SET NULL;


--
-- Name: task_completions task_completions_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.task_completions
    ADD CONSTRAINT task_completions_task_id_foreign FOREIGN KEY (task_id) REFERENCES public.tasks(id);


--
-- Name: tasks tasks_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: tasks tasks_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE SET NULL;


--
-- Name: tasks tasks_task_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_task_account_id_foreign FOREIGN KEY (task_account_id) REFERENCES public.provider_accounts(id) ON DELETE SET NULL;


--
-- Name: users users_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.households(id) ON DELETE CASCADE;


--
-- Name: users users_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_person_id_foreign FOREIGN KEY (person_id) REFERENCES public.people(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 16.11 (Debian 16.11-1.pgdg13+1)
-- Dumped by pg_dump version 16.15 (Debian 16.15-1.pgdg13+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_09_14_100000_create_households_table	1
5	2026_09_14_100100_create_people_table	1
6	2026_09_14_100200_add_household_to_users_table	1
7	2026_09_14_100300_create_devices_table	1
8	2026_09_14_125712_create_personal_access_tokens_table	1
9	2026_09_14_140000_create_calendar_sources_table	1
10	2026_09_14_140100_create_calendars_table	1
11	2026_09_14_140200_create_events_table	1
12	2026_09_14_140300_create_event_person_table	1
13	2026_09_14_150000_create_lists_table	1
14	2026_09_14_150100_create_list_items_table	1
15	2026_09_14_150200_create_tasks_table	1
16	2026_09_14_150300_create_task_completions_table	1
17	2026_09_14_160000_add_pin_to_households_table	1
18	2026_09_14_160100_create_point_entries_table	1
19	2026_09_14_160200_create_rewards_table	1
20	2026_09_14_160300_create_reward_redemptions_table	1
21	2026_09_14_170000_create_meal_plan_entries_table	1
22	2026_09_14_180000_add_push_subscription_to_devices_table	1
23	2026_09_15_090000_add_rotation_to_tasks	1
24	2026_09_15_090100_create_task_assignments_table	1
25	2026_09_15_120000_add_theme_schedule_to_households	1
26	2026_09_15_120100_add_theme_preference_to_devices_and_users	1
27	2026_09_15_130000_unique_redemption_on_point_entries	1
28	2026_09_17_090000_add_daily_summary_to_households	1
29	2026_09_17_140000_add_goals_to_people	1
30	2026_09_21_090000_add_provider_to_calendar_sources	1
31	2026_09_21_100000_rename_provider_columns	1
32	2026_09_21_110000_rename_sync_enabled_to_is_active	1
33	2026_09_21_120000_add_builtin_calendar_to_households	1
34	2026_09_21_120000_create_photos_table	1
35	2026_09_21_130000_create_dashboard_widgets_table	1
36	2026_09_21_130000_replace_pushes_to_bring_with_list_provider	1
37	2026_09_21_140000_add_preferences_to_devices_and_users	1
38	2026_09_21_140000_rename_tandoor_recipe_id	1
39	2026_09_21_150000_generalise_calendar_sources	1
40	2026_09_21_160000_add_list_account_to_lists	1
41	2026_09_21_170000_add_disabled_features_to_households	1
42	2026_09_21_180000_create_recipes_table	1
43	2026_09_21_190000_widen_recipe_id_on_meal_plan_entries	1
44	2026_09_21_200000_add_provider_to_tasks	1
45	2026_09_21_210000_add_address_to_households	1
46	2026_09_21_220000_add_credentials_to_provider_accounts	1
47	2026_09_22_100000_create_lesson_periods_table	1
48	2026_09_22_110000_create_lessons_table	1
49	2026_09_22_120000_create_lesson_changes_table	1
50	2026_09_22_130000_create_school_closures_table	1
51	2026_09_22_140000_add_icon_to_tasks_and_rewards	1
52	2026_09_24_090000_protect_task_completions_from_task_deletion	1
53	2026_09_24_120000_drop_google_default_from_provider_accounts	1
54	2026_09_25_090000_add_operator_and_suspension	1
55	2026_09_25_100000_create_calendar_person_table	1
56	2026_09_25_110000_add_permissions_to_people_and_devices	1
57	2026_09_25_120000_create_support_accesses_table	1
58	2026_09_29_100000_mark_existing_accounts_verified	1
59	2026_09_29_110000_add_hidden_calendars_to_users_and_devices	1
60	2026_09_29_120000_add_color_to_lessons	1
61	2026_09_29_130000_add_per_person_and_dayparts_to_tasks	1
62	2026_09_29_140000_add_position_to_people	1
63	2026_09_29_170000_move_parent_pin_to_accounts	1
64	2026_09_30_090000_add_photo_rules	1
65	2026_09_30_120000_add_upload_fields_to_photos	1
66	2026_09_30_130000_move_bring_to_plugin	1
67	2026_09_30_140000_add_skip_holidays_to_tasks	1
68	2026_10_01_090000_add_locale	1
69	2026_10_01_100000_add_address_parts_to_households	1
70	2026_10_01_110000_add_trash_to_photos	1
71	2026_10_02_090000_add_soft_deletes_to_tasks	1
72	2026_10_05_090000_add_day_bonus	1
73	2026_10_05_090000_create_plugin_settings_and_second_factor	1
74	2026_10_05_100000_extend_recipes	1
75	2026_10_05_110000_add_portions	1
76	2026_10_05_120000_add_pantry_staples	1
77	2026_10_06_090000_add_meal_kinds_to_households	1
78	2026_10_09_100000_give_dashboards_an_owner	1
79	2026_10_09_100100_merge_phone_preferences	1
80	2026_10_12_090000_add_pages_to_recipes	1
81	2026_10_14_090000_add_backdrop_to_households	1
82	2026_10_24_090000_create_memberships_table	1
83	2026_10_26_090000_add_opted_in_features_to_households	1
84	2026_10_26_100000_add_skipped_to_task_completions	1
85	2026_10_26_120000_create_photo_albums	1
86	2026_10_31_090000_add_gift_id_to_point_entries	1
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 86, true);


--
-- PostgreSQL database dump complete
--


