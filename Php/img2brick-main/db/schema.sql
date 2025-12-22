--
-- PostgreSQL database dump
--




SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;


CREATE FUNCTION public.fn_block_changes_on_validated_order_lines() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE v_validated timestamptz;
DECLARE v_status text;
DECLARE v_order_id bigint;
BEGIN
  v_order_id := COALESCE(OLD.order_id, NEW.order_id);

  SELECT o.validated_at, o.status
  INTO v_validated, v_status
  FROM public.orders o
  WHERE o.id = v_order_id;

  IF (v_validated IS NOT NULL) OR (v_status = 'PAID') THEN
    RAISE EXCEPTION 'Commande validée : modification des lignes interdite (order_id=%)', v_order_id;
  END IF;

  RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_block_changes_on_validated_order_lines() OWNER TO postgres;



CREATE FUNCTION public.fn_block_changes_on_validated_orders() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
  IF (OLD.validated_at IS NOT NULL) OR (OLD.status = 'PAID') THEN
    RAISE EXCEPTION 'Commande validée : modification/suppression interdite (order_id=%)', OLD.id;
  END IF;
  RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_block_changes_on_validated_orders() OWNER TO postgres;



CREATE FUNCTION public.fn_block_invoice_changes() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
  RAISE EXCEPTION 'Facture immuable : modification/suppression interdite (invoice_id=%)', OLD.id;
END;
$$;


ALTER FUNCTION public.fn_block_invoice_changes() OWNER TO postgres;



CREATE FUNCTION public.fn_decrement_stock_on_validation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE r record;
DECLARE v_q int;
BEGIN
  IF (OLD.validated_at IS NULL AND NEW.validated_at IS NOT NULL)
     OR (OLD.status <> 'PAID' AND NEW.status = 'PAID') THEN

    -- contrôle de présence des lignes
    IF NOT EXISTS (SELECT 1 FROM public.order_lines ol WHERE ol.order_id = NEW.id) THEN
      RAISE EXCEPTION 'Commande % sans lignes : validation impossible', NEW.id;
    END IF;

    -- 1) vérification stock
    FOR r IN
      SELECT part_id, SUM(quantity)::int AS qty
      FROM public.order_lines
      WHERE order_id = NEW.id
      GROUP BY part_id
    LOOP
      SELECT si.quantity INTO v_q
      FROM public.stock_items si
      WHERE si.part_id = r.part_id
      FOR UPDATE;

      IF v_q IS NULL THEN
        RAISE EXCEPTION 'Stock manquant pour part_id=% (ajoute une ligne stock_items)', r.part_id;
      END IF;

      IF v_q < r.qty THEN
        RAISE EXCEPTION 'Stock insuffisant pour part_id=% (dispo=% demandé=%)', r.part_id, v_q, r.qty;
      END IF;
    END LOOP;

    -- 2) déstockage
    FOR r IN
      SELECT part_id, SUM(quantity)::int AS qty
      FROM public.order_lines
      WHERE order_id = NEW.id
      GROUP BY part_id
    LOOP
      UPDATE public.stock_items
      SET quantity = quantity - r.qty,
          updated_at = now()
      WHERE part_id = r.part_id;
    END LOOP;

  END IF;

  RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_decrement_stock_on_validation() OWNER TO postgres;


CREATE FUNCTION public.fn_issue_invoice_on_validation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE v_num text;
BEGIN
  -- déclenchement si passage en validé (validated_at devient non null) OU status devient PAID
  IF (OLD.validated_at IS NULL AND NEW.validated_at IS NOT NULL)
     OR (OLD.status <> 'PAID' AND NEW.status = 'PAID') THEN

    IF EXISTS (SELECT 1 FROM public.invoices i WHERE i.order_id = NEW.id) THEN
      RETURN NEW;
    END IF;

    v_num := 'INV-' || to_char(now(), 'YYYYMMDD') || '-' || lpad(NEW.id::text, 8, '0');

    INSERT INTO public.invoices(order_id, invoice_number, created_at, pdf_path)
    VALUES (NEW.id, v_num, now(), NULL);
  END IF;

  RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_issue_invoice_on_validation() OWNER TO postgres;



CREATE FUNCTION public.fn_recompute_order_total() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE v_order_id bigint;
BEGIN
  v_order_id := COALESCE(NEW.order_id, OLD.order_id);

  UPDATE public.orders o
  SET total_amount = COALESCE((
    SELECT round(SUM(ol.line_total)::numeric, 2)
    FROM public.order_lines ol
    WHERE ol.order_id = v_order_id
  ), 0)
  WHERE o.id = v_order_id;

  RETURN NULL;
END;
$$;


ALTER FUNCTION public.fn_recompute_order_total() OWNER TO postgres;



CREATE FUNCTION public.fn_set_line_total() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
  IF NEW.quantity <= 0 THEN
    RAISE EXCEPTION 'quantity doit être > 0';
  END IF;

  IF NEW.unit_price < 0 THEN
    RAISE EXCEPTION 'unit_price doit être >= 0';
  END IF;

  NEW.line_total := round((NEW.quantity::numeric * NEW.unit_price)::numeric, 2);
  RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_set_line_total() OWNER TO postgres;



CREATE PROCEDURE public.validate_order(IN p_order_id bigint)
    LANGUAGE plpgsql
    AS $$
BEGIN
  -- validation => status PAID + validated_at => déclenche facture + déstockage
  UPDATE public.orders
  SET status = 'PAID',
      validated_at = COALESCE(validated_at, now())
  WHERE id = p_order_id;

  IF NOT FOUND THEN
    RAISE EXCEPTION 'Commande introuvable (id=%)', p_order_id;
  END IF;
END;
$$;


ALTER PROCEDURE public.validate_order(IN p_order_id bigint) OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;



CREATE TABLE public.images (
    id bigint NOT NULL,
    user_id bigint,
    file_path text NOT NULL,
    width integer,
    height integer,
    mime_type character varying(100),
    uploaded_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.images OWNER TO postgres;



CREATE SEQUENCE public.images_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.images_id_seq OWNER TO postgres;



ALTER SEQUENCE public.images_id_seq OWNED BY public.images.id;



CREATE TABLE public.invoices (
    id bigint NOT NULL,
    order_id bigint,
    invoice_number character varying(50) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    pdf_path text
);


ALTER TABLE public.invoices OWNER TO postgres;


CREATE SEQUENCE public.invoices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.invoices_id_seq OWNER TO postgres;



ALTER SEQUENCE public.invoices_id_seq OWNED BY public.invoices.id;




CREATE TABLE public.lego_parts (
    id bigint NOT NULL,
    reference character varying(50) NOT NULL,
    description text,
    color_code character varying(50),
    length integer NOT NULL,
    width integer NOT NULL,
    unit_price numeric(10,2) NOT NULL
);


ALTER TABLE public.lego_parts OWNER TO postgres;



CREATE SEQUENCE public.lego_parts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.lego_parts_id_seq OWNER TO postgres;



ALTER SEQUENCE public.lego_parts_id_seq OWNED BY public.lego_parts.id;




CREATE TABLE public.mosaics (
    id bigint NOT NULL,
    image_id bigint,
    board_size character varying(20) NOT NULL,
    variant character varying(50) NOT NULL,
    data_format character varying(20) NOT NULL,
    data_payload text NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.mosaics OWNER TO postgres;



CREATE SEQUENCE public.mosaics_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mosaics_id_seq OWNER TO postgres;


ALTER SEQUENCE public.mosaics_id_seq OWNED BY public.mosaics.id;




CREATE TABLE public.order_lines (
    id bigint NOT NULL,
    order_id bigint NOT NULL,
    part_id bigint NOT NULL,
    quantity integer NOT NULL,
    unit_price numeric(10,2) NOT NULL,
    line_total numeric(10,2) NOT NULL
);


ALTER TABLE public.order_lines OWNER TO postgres;



CREATE SEQUENCE public.order_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.order_lines_id_seq OWNER TO postgres;



ALTER SEQUENCE public.order_lines_id_seq OWNED BY public.order_lines.id;




CREATE TABLE public.orders (
    id bigint NOT NULL,
    user_id bigint,
    image_id bigint,
    mosaic_id bigint,
    status character varying(20) DEFAULT 'CART'::character varying NOT NULL,
    total_amount numeric(10,2) DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    validated_at timestamp with time zone,
    CONSTRAINT chk_orders_status CHECK (((status)::text = ANY (ARRAY['CART'::text, 'PENDING'::text, 'PAID'::text, 'CANCELLED'::text])))
);


ALTER TABLE public.orders OWNER TO postgres;


CREATE SEQUENCE public.orders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orders_id_seq OWNER TO postgres;


ALTER SEQUENCE public.orders_id_seq OWNED BY public.orders.id;




CREATE TABLE public.stock_items (
    id bigint NOT NULL,
    part_id bigint NOT NULL,
    quantity integer DEFAULT 0 NOT NULL,
    threshold integer DEFAULT 10 NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.stock_items OWNER TO postgres;



CREATE SEQUENCE public.stock_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.stock_items_id_seq OWNER TO postgres;



ALTER SEQUENCE public.stock_items_id_seq OWNED BY public.stock_items.id;




CREATE TABLE public.users (
    id bigint NOT NULL,
    email character varying(255) NOT NULL,
    password_hash character varying(255) NOT NULL,
    first_name character varying(100),
    last_name character varying(100),
    billing_address text,
    billing_zip character varying(20),
    billing_city character varying(100),
    billing_country character varying(100),
    phone character varying(30),
    is_admin boolean DEFAULT false,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.users OWNER TO postgres;



CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;



ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;




CREATE VIEW public.v_low_stock AS
 SELECT p.id AS part_id,
    p.reference,
    p.description,
    s.quantity,
    s.threshold,
    s.updated_at
   FROM (public.stock_items s
     JOIN public.lego_parts p ON ((p.id = s.part_id)))
  WHERE (s.quantity < s.threshold);


ALTER VIEW public.v_low_stock OWNER TO postgres;



ALTER TABLE ONLY public.images ALTER COLUMN id SET DEFAULT nextval('public.images_id_seq'::regclass);




ALTER TABLE ONLY public.invoices ALTER COLUMN id SET DEFAULT nextval('public.invoices_id_seq'::regclass);




ALTER TABLE ONLY public.lego_parts ALTER COLUMN id SET DEFAULT nextval('public.lego_parts_id_seq'::regclass);



ALTER TABLE ONLY public.mosaics ALTER COLUMN id SET DEFAULT nextval('public.mosaics_id_seq'::regclass);




ALTER TABLE ONLY public.order_lines ALTER COLUMN id SET DEFAULT nextval('public.order_lines_id_seq'::regclass);




ALTER TABLE ONLY public.orders ALTER COLUMN id SET DEFAULT nextval('public.orders_id_seq'::regclass);




ALTER TABLE ONLY public.stock_items ALTER COLUMN id SET DEFAULT nextval('public.stock_items_id_seq'::regclass);




ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);




ALTER TABLE ONLY public.images
    ADD CONSTRAINT images_pkey PRIMARY KEY (id);




ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_order_id_key UNIQUE (order_id);




ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (id);




ALTER TABLE ONLY public.lego_parts
    ADD CONSTRAINT lego_parts_pkey PRIMARY KEY (id);



ALTER TABLE ONLY public.lego_parts
    ADD CONSTRAINT lego_parts_reference_key UNIQUE (reference);




ALTER TABLE ONLY public.mosaics
    ADD CONSTRAINT mosaics_pkey PRIMARY KEY (id);



ALTER TABLE ONLY public.order_lines
    ADD CONSTRAINT order_lines_pkey PRIMARY KEY (id);




ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_pkey PRIMARY KEY (id);




ALTER TABLE ONLY public.stock_items
    ADD CONSTRAINT stock_items_pkey PRIMARY KEY (id);



ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);




ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);




CREATE INDEX idx_images_user_id ON public.images USING btree (user_id);




CREATE INDEX idx_mosaics_image_id ON public.mosaics USING btree (image_id);




CREATE INDEX idx_order_lines_order_id ON public.order_lines USING btree (order_id);



CREATE UNIQUE INDEX uniq_stock_items_part ON public.stock_items USING btree (part_id);




CREATE TRIGGER trg_block_invoices_changes BEFORE DELETE OR UPDATE ON public.invoices FOR EACH ROW EXECUTE FUNCTION public.fn_block_invoice_changes();



CREATE TRIGGER trg_block_order_lines_changes BEFORE INSERT OR DELETE OR UPDATE ON public.order_lines FOR EACH ROW EXECUTE FUNCTION public.fn_block_changes_on_validated_order_lines();




CREATE TRIGGER trg_block_orders_changes BEFORE DELETE OR UPDATE ON public.orders FOR EACH ROW EXECUTE FUNCTION public.fn_block_changes_on_validated_orders();


CREATE TRIGGER trg_decrement_stock AFTER UPDATE ON public.orders FOR EACH ROW EXECUTE FUNCTION public.fn_decrement_stock_on_validation();




CREATE TRIGGER trg_issue_invoice AFTER UPDATE ON public.orders FOR EACH ROW EXECUTE FUNCTION public.fn_issue_invoice_on_validation();




CREATE TRIGGER trg_recompute_total_del AFTER DELETE ON public.order_lines FOR EACH ROW EXECUTE FUNCTION public.fn_recompute_order_total();




CREATE TRIGGER trg_recompute_total_ins AFTER INSERT ON public.order_lines FOR EACH ROW EXECUTE FUNCTION public.fn_recompute_order_total();




CREATE TRIGGER trg_recompute_total_upd AFTER UPDATE ON public.order_lines FOR EACH ROW EXECUTE FUNCTION public.fn_recompute_order_total();



CREATE TRIGGER trg_set_line_total BEFORE INSERT OR UPDATE ON public.order_lines FOR EACH ROW EXECUTE FUNCTION public.fn_set_line_total();




ALTER TABLE ONLY public.images
    ADD CONSTRAINT images_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;




ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;




ALTER TABLE ONLY public.mosaics
    ADD CONSTRAINT mosaics_image_id_fkey FOREIGN KEY (image_id) REFERENCES public.images(id) ON DELETE CASCADE;




ALTER TABLE ONLY public.order_lines
    ADD CONSTRAINT order_lines_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;




ALTER TABLE ONLY public.order_lines
    ADD CONSTRAINT order_lines_part_id_fkey FOREIGN KEY (part_id) REFERENCES public.lego_parts(id);



ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_image_id_fkey FOREIGN KEY (image_id) REFERENCES public.images(id) ON DELETE SET NULL;




ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_mosaic_id_fkey FOREIGN KEY (mosaic_id) REFERENCES public.mosaics(id) ON DELETE SET NULL;




ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;




ALTER TABLE ONLY public.stock_items
    ADD CONSTRAINT stock_items_part_id_fkey FOREIGN KEY (part_id) REFERENCES public.lego_parts(id) ON DELETE CASCADE;






