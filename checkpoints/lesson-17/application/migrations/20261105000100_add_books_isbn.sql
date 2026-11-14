-- add_books_isbn
-- Runs once (php caligrafer.php migrate, and at every deploy). Never edit it after it ran: write a new one.
-- One statement: the column and its unique index are added together, or not at all.

ALTER TABLE books
    ADD COLUMN isbn VARCHAR(20) NULL AFTER summary,
    ADD UNIQUE KEY uq_books_isbn (isbn);
