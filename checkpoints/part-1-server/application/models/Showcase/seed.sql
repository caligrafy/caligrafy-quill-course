-- Sample projects (safe to re-run: clears the table first)
DELETE FROM projects;
INSERT INTO projects (title, description) VALUES
('Caligrafy', 'A light PHP MVC framework with Pug views, Vue apps and a Claude Code plugin.'),
('Portfolio Website', 'A personal website that showcases projects, built with Caligrafy views.'),
('Vue Dashboard', 'A single page application that consumes the Showcase REST API.');
