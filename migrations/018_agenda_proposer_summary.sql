-- Tagesordnung (Beta 0.7.43): Je TOP festhalten, von wem er kommt, und eine einzeilige Kurzbeschreibung.
ALTER TABLE meeting_agenda_items
    ADD COLUMN proposed_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    ADD COLUMN summary VARCHAR(200) NOT NULL DEFAULT '';

-- Bestehende TOPs aus übernommenen Themenvorschlägen nachtragen (gleicher Stammtisch, gleicher Titel).
UPDATE meeting_agenda_items a
SET proposed_by = s.suggested_by,
    summary = LEFT(REGEXP_REPLACE(TRIM(s.note), '\s+', ' ', 'g'), 200)
FROM meeting_topic_suggestions s
WHERE s.meeting_id = a.meeting_id AND s.status = 'accepted' AND s.title = a.title AND a.proposed_by IS NULL;
