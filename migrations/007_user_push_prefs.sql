-- Persönliche Push-Einstellungen: fehlender Eintrag = Kategorie ist eingeschaltet.
CREATE TABLE user_push_prefs (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category VARCHAR(30) NOT NULL,
    enabled SMALLINT NOT NULL DEFAULT 1 CHECK (enabled IN (0,1)),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(user_id,category)
);
