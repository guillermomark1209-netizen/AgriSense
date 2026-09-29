-- Supabase SQL Editor, or the matching Laravel migration (preferred).
-- Run as a database administrator. No tables, rows, or relationships are removed.
DO $$
DECLARE added_role boolean;
BEGIN
    SELECT NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'users' AND column_name = 'role'
    ) INTO added_role;

    ALTER TABLE public.users ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'user';
    UPDATE public.users SET role = 'user' WHERE role IS NULL;

    -- Preserve existing administrators only on the initial conversion.
    -- Rerunning this script must never resurrect a demoted legacy administrator.
    IF added_role AND to_regclass('public.user_roles') IS NOT NULL THEN
        UPDATE public.users u SET role = 'admin'
        WHERE EXISTS (SELECT 1 FROM public.user_roles r WHERE r.user_id = u.id AND r.role = 'admin');
    END IF;

    IF EXISTS (SELECT 1 FROM public.users WHERE role NOT IN ('admin', 'user')) THEN
        RAISE EXCEPTION 'Unexpected users.role values: review them before applying RBAC';
    END IF;

    ALTER TABLE public.users ALTER COLUMN role TYPE VARCHAR(20);
    ALTER TABLE public.users ALTER COLUMN role SET DEFAULT 'user';
    ALTER TABLE public.users ALTER COLUMN role SET NOT NULL;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'public.users'::regclass AND conname = 'users_role_check') THEN
        ALTER TABLE public.users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'user'));
    END IF;
END $$;
