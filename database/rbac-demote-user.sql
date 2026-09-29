-- Execute manually in the Supabase SQL Editor after replacing the placeholder.
-- Keep another administrator available before demoting your last admin.
UPDATE public.users
SET role = 'user', updated_at = CURRENT_TIMESTAMP
WHERE email = 'ADMIN_EMAIL_HERE'
RETURNING id, email, role;
