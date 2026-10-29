-- Bilder der Seite (Beta 0.7.38): je Platz genau ein Bild.
--   pattern     = Hintergrund-Muster (gekachelt, 15 % sichtbar)
--   group_photo = Gruppenfoto über dem Zeitstrahl der öffentlichen Chronik
-- Die wechselnden Hintergrundbilder (0.7.37) entfallen auf Wunsch samt Inhalt.
CREATE TABLE site_images (
    slot VARCHAR(20) PRIMARY KEY CHECK (slot IN ('pattern','group_photo')),
    mime VARCHAR(40) NOT NULL,
    data BYTEA NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    caption VARCHAR(250) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL
);
DROP TABLE site_backgrounds;
