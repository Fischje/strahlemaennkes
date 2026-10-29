-- Mehrere Hintergrundbilder (Beta 0.7.37): angehakte Bilder (is_active=1) wechseln sich ab.
CREATE TABLE site_backgrounds (
    id BIGSERIAL PRIMARY KEY,
    mime VARCHAR(40) NOT NULL,
    data BYTEA NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- Bisheriges Einzelbild übernehmen.
INSERT INTO site_backgrounds(mime,data,width,height,is_active,created_at,created_by,updated_at)
SELECT mime,data,width,height,1,updated_at,updated_by,updated_at FROM site_background;
DROP TABLE site_background;
