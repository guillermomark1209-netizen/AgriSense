-- Execute manually in the Supabase SQL Editor after replacing the placeholder.
-- This affects one existing account because users.email is unique.
UPDATE public.users
SET role = 'admin', updated_at = CURRENT_TIMESTAMP
WHERE email = 'ADMIN_EMAIL_HERE'
RETURNING id, email, role;
