-- Hintergrundbild der Website (wird in der Datenbank gehalten, damit keine Ordnerrechte nötig sind).
CREATE TABLE site_background (
    id SMALLINT PRIMARY KEY CHECK (id = 1),
    mime VARCHAR(40) NOT NULL,
    data BYTEA NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL
);
