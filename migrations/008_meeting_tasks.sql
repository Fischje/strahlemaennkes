-- Aufgaben aus Protokollen: entstehen je Tagesordnungspunkt und erscheinen im nächsten Protokoll zum Abhaken.
CREATE TABLE meeting_tasks (
    id BIGSERIAL PRIMARY KEY,
    meeting_id BIGINT NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    agenda_item_id BIGINT REFERENCES meeting_agenda_items(id) ON DELETE SET NULL,
    title VARCHAR(250) NOT NULL,
    assignee_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    due_date DATE,
    status VARCHAR(10) NOT NULL DEFAULT 'open' CHECK (status IN ('open','done')),
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    done_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    done_at TIMESTAMP,
    done_meeting_id BIGINT REFERENCES meetings(id) ON DELETE SET NULL
);
CREATE INDEX idx_meeting_tasks_meeting ON meeting_tasks(meeting_id);
CREATE INDEX idx_meeting_tasks_open ON meeting_tasks(status) WHERE status='open';
