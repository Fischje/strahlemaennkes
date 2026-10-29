-- Kalender (Beta 0.7.32): Felder „Was anziehen?“ und „Was mitbringen?“; ersetzt die Kirmes-Planung (kirmes_days).
ALTER TABLE calendar_events ADD COLUMN attire TEXT NOT NULL DEFAULT '';
ALTER TABLE calendar_events ADD COLUMN bring TEXT NOT NULL DEFAULT '';

-- Antreten: Die Kleidung stand bisher als „Anzug: …“ in der Bemerkung und bekommt jetzt ihr eigenes Feld.
UPDATE calendar_events
   SET attire = substr(note, 8), note = ''
 WHERE source = 'assembly' AND note LIKE 'Anzug: %';

-- Bereits geplante Kirmes-Tage als Kalendertermine übernehmen, danach die Kirmes-Planung entfernen.
INSERT INTO calendar_events(title,category,event_date,start_time,location,attire,bring,note,source,created_by,created_at,updated_by,updated_at)
SELECT title, 'kirmes', event_date, assembly_time, location, attire, bring, note, 'manual', created_by, created_at, updated_by, updated_at
  FROM kirmes_days;
DROP TABLE kirmes_days;
