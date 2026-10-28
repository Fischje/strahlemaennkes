-- Aufräumen (Beta 0.7.25): entfernt nur Daten, die keine Funktion mehr haben. Vereinsdaten bleiben unberührt.

-- 1) Abgelaufene „Eingeloggt bleiben“-Tokens und benutzte/abgelaufene Passwort-Reset-Links.
DELETE FROM remember_tokens WHERE expires_at <= CURRENT_TIMESTAMP;
DELETE FROM password_reset_tokens WHERE used_at IS NOT NULL OR expires_at <= CURRENT_TIMESTAMP;

-- 2) Deaktivierte Mitglieder: gespeicherte Anmeldungen, offene Reset-Links und Push-Abos haben keine Funktion mehr
--    (Anmelden und Push sind für sie ohnehin gesperrt). Bei Reaktivierung meldet sich das Mitglied neu an.
DELETE FROM remember_tokens t USING users u WHERE u.id = t.user_id AND u.is_active = 0;
DELETE FROM password_reset_tokens t USING users u WHERE u.id = t.user_id AND u.is_active = 0;
DELETE FROM push_subscriptions s USING users u WHERE u.id = s.user_id AND u.is_active = 0;

-- 3) Push-Einstellung „Neuigkeiten“ gibt es nicht mehr (die Neuigkeiten-Seite wurde schon mit Migration 002 entfernt).
DELETE FROM user_push_prefs WHERE category NOT IN ('stammtisch', 'sektbar', 'getraenke', 'runden');

-- 4) Sektbar: Ohne aktiven Sektbarmodus darf es keine vom Modus freigeschalteten Getränke mehr geben.
DELETE FROM sektbar_activated_drinks WHERE EXISTS (SELECT 1 FROM sektbar_state WHERE id = 1 AND is_active = 0);

-- 5) Themenvorschläge, die nie entschieden wurden, obwohl der Stammtisch längst vorbei ist: als abgelehnt markieren,
--    damit sie nicht als „offen“ herumliegen. Der Vorschlag selbst bleibt erhalten.
UPDATE meeting_topic_suggestions s SET status = 'rejected', decided_at = CURRENT_TIMESTAMP
FROM meetings m
WHERE m.id = s.meeting_id AND s.status = 'pending' AND m.status IN ('completed', 'cancelled');

-- 6) Neu: Fehlversuche beim Anmelden (Schutz gegen Passwort-Raten, wird stündlich aufgeräumt).
CREATE TABLE login_attempts (
    id BIGSERIAL PRIMARY KEY,
    login_key VARCHAR(254) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_login_attempts_key_time ON login_attempts(login_key, attempted_at);

-- 7) Index für häufige Abfragen „Wer hat wie abgestimmt“ je Mitglied.
CREATE INDEX IF NOT EXISTS idx_meeting_votes_user ON meeting_votes(user_id);
