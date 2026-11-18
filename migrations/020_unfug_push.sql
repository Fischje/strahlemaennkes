-- Push für Grober Unfug: Grober Unfug legt Aufträge ab („Alle benachrichtigen“), der Minuten-Cron verschickt sie.
-- Erinnerungen (Frist, Beitrag) berechnet der Cron selbst; das Protokoll verhindert doppelten Versand.
CREATE TABLE unfug_push_queue (
    id BIGSERIAL PRIMARY KEY,
    event_id BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    body VARCHAR(500) NOT NULL,
    url VARCHAR(300) NOT NULL,
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL
);
CREATE INDEX idx_unfug_push_queue_open ON unfug_push_queue(id) WHERE sent_at IS NULL;

CREATE TABLE unfug_push_log (
    event_id BIGINT NOT NULL,
    kind VARCHAR(20) NOT NULL,
    key VARCHAR(40) NOT NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id, kind, key)
);
