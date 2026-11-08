-- make_reader1_admin
-- Runs once (php caligrafer.php migrate, and at every deploy). Never edit it after it ran: write a new one.


-- The first administrator of Bookshelf online. The account must exist first (create it on the site), then commit,
-- push and deploy. It only gives a level: it never creates an account and never holds a password.
UPDATE users SET permissions = 1 WHERE username = 'reader1@example.com';
