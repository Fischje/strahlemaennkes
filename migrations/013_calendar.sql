-- Kalender (Beta 0.7.28): Termine mit festen Kategorien.
-- Datum und Uhrzeiten werden als Ortszeit (Europe/Berlin) gespeichert, wie bei kirmes_days.
-- Stammtische werden nicht kopiert, sondern direkt aus meetings angezeigt.
CREATE TABLE calendar_events (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    category VARCHAR(30) NOT NULL CHECK (category IN ('fruehkirmes','spaetkirmes','zugtreffen','ausserhalb','ausflug','weihnachtsfeier')),
    event_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    location VARCHAR(250) NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    source VARCHAR(20) NOT NULL DEFAULT 'manual' CHECK (source IN ('manual','assembly')),
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_calendar_events_date ON calendar_events(event_date, start_time);

-- Antreten trägt sich automatisch in den Kalender ein; hier steht, welcher Kalendereintrag zum aktuellen Antreten gehört.
ALTER TABLE assembly_status ADD COLUMN calendar_event_id BIGINT NULL REFERENCES calendar_events(id) ON DELETE SET NULL;

-- Ein bereits eingetragenes Antreten gleich übernehmen.
WITH a AS (
    SELECT ((starts_at_utc AT TIME ZONE 'UTC') AT TIME ZONE 'Europe/Berlin') AS local_ts, location, attire, updated_by
    FROM assembly_status WHERE id=1
), ins AS (
    INSERT INTO calendar_events(title,category,event_date,start_time,location,note,source,created_by,updated_by)
    SELECT 'Antreten','zugtreffen',local_ts::date,local_ts::time,location,
           CASE WHEN attire<>'' THEN 'Anzug: '||attire ELSE '' END,'assembly',updated_by,updated_by
    FROM a
    RETURNING id
)
UPDATE assembly_status SET calendar_event_id=(SELECT id FROM ins) WHERE id=1 AND EXISTS(SELECT 1 FROM ins);
