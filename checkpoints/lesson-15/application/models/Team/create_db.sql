/* The administrators of Bookshelf online: php caligrafer.php deploy runs it when it is new or changed. The account
 * must exist first (register it on the site), then add its username here, commit, push and deploy.
 * It only gives permissions: it never creates an account and never contains a password.
 */

UPDATE users SET permissions = 1 WHERE username = 'reader1';
