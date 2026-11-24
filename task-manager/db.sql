USE task_manager;

-- Replace the example email with the address of your own registered account.
UPDATE users
SET role = 'admin'
WHERE email = 'you@example.com';
