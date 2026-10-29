-- Kalender (Beta 0.7.29): Früh- und Spät-Kirmes werden zu einer Kategorie „Kirmes“; Antreten gehört fest zur Kirmes.
ALTER TABLE calendar_events DROP CONSTRAINT IF EXISTS calendar_events_category_check;
UPDATE calendar_events SET category='kirmes' WHERE category IN ('fruehkirmes','spaetkirmes') OR source='assembly';
ALTER TABLE calendar_events ADD CONSTRAINT calendar_events_category_check
    CHECK (category IN ('kirmes','zugtreffen','ausserhalb','ausflug','weihnachtsfeier'));

-- Kalender-Abo: persönlicher, geheimer Link je Mitglied (nur lesen). Erneuern macht den alten Link ungültig.
CREATE TABLE calendar_feed_tokens (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    token CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL
);
-- Abo-Links deaktivierter Mitglieder gleich entfernen.
DELETE FROM calendar_feed_tokens t USING users u WHERE u.id=t.user_id AND u.is_active=0;
