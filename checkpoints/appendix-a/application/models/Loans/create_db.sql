/* Loans: who borrowed which book. Safe to run more than once: it never drops anything.
 * Run it with:
 * docker compose exec dev-box php caligrafer.php sql application/models/Loans/create_db.sql
 */

CREATE TABLE IF NOT EXISTS loans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id INT UNSIGNED NOT NULL,
    borrower VARCHAR(100) NOT NULL,
    due_on DATE NOT NULL,
    returned_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loans_book_id (book_id),
    CONSTRAINT fk_loans_book_id FOREIGN KEY (book_id) REFERENCES books (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
