-- Domain-Umzug (Beta 0.7.26): Vorbereitung für den Wechsel von der alten auf die neue Adresse.

-- 1) Einmal-Links, mit denen ein Mitglied von der alten Adresse angemeldet auf die neue wechselt.
--    Gespeichert wird nur der Hash; ein Link gilt 30 Minuten und genau einmal.
CREATE TABLE domain_handoff_tokens (
    token_hash CHAR(64) PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL
);
CREATE INDEX idx_domain_handoff_expiry ON domain_handoff_tokens(expires_at);

-- 2) Unter welcher Adresse ein Push-Abo angelegt wurde. Ein Abo gehört immer zur Adresse, auf der die App
--    installiert ist. Bestehende Abos haben NULL und stammen damit von der bisherigen Adresse.
ALTER TABLE push_subscriptions ADD COLUMN origin_host VARCHAR(253) NULL;
