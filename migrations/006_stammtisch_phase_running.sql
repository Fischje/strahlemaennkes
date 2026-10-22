-- Phase 3 „Stammtisch läuft“: Protokoll wird erst nach dem Start geschrieben.
ALTER TABLE meetings DROP CONSTRAINT IF EXISTS meetings_status_check;
ALTER TABLE meetings ADD CONSTRAINT meetings_status_check CHECK (status IN ('poll','scheduled','running','completed','cancelled'));
ALTER TABLE meetings ADD COLUMN started_at TIMESTAMP;
ALTER TABLE meetings ADD COLUMN started_by BIGINT REFERENCES users(id) ON DELETE SET NULL;
DROP INDEX IF EXISTS uq_one_active_meeting;
CREATE UNIQUE INDEX uq_one_active_meeting ON meetings ((1)) WHERE status IN ('poll','scheduled','running');
