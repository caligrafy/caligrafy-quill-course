-- Showcase projects. Run after application/models/Auth/create_db.sql (users).
CREATE TABLE IF NOT EXISTS projects (
    id int NOT NULL AUTO_INCREMENT,
    title varchar(150) NOT NULL,
    description text NOT NULL,
    user_id int NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_projects_user_id (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
