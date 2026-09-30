-- Stammtisch, Tagesordnung, Protokolle und geschütztes historisches Archiv.
DROP TABLE IF EXISTS news_posts;

CREATE TABLE meetings (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'poll' CHECK (status IN ('poll','scheduled','completed','cancelled')),
    starts_at_utc TIMESTAMP,
    location VARCHAR(250) NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    created_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    completed_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    reminder_sent_at TIMESTAMP
);
CREATE UNIQUE INDEX uq_one_active_meeting ON meetings ((1)) WHERE status IN ('poll','scheduled');
CREATE INDEX idx_meetings_archive ON meetings(status,starts_at_utc DESC,id DESC);

CREATE TABLE meeting_poll_options (
    id BIGSERIAL PRIMARY KEY,
    meeting_id BIGINT NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    starts_at_utc TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(meeting_id,starts_at_utc)
);
CREATE TABLE meeting_votes (
    option_id BIGINT NOT NULL REFERENCES meeting_poll_options(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(option_id,user_id)
);

CREATE TABLE meeting_agenda_items (
    id BIGSERIAL PRIMARY KEY,
    meeting_id BIGINT NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    title VARCHAR(240) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    protocol_html TEXT NOT NULL DEFAULT '',
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_agenda_meeting_order ON meeting_agenda_items(meeting_id,sort_order,id);

CREATE TABLE meeting_topic_suggestions (
    id BIGSERIAL PRIMARY KEY,
    meeting_id BIGINT NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    title VARCHAR(240) NOT NULL,
    note TEXT NOT NULL DEFAULT '',
    suggested_by BIGINT NOT NULL REFERENCES users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','accepted','rejected')),
    decided_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    decided_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_topic_suggestions ON meeting_topic_suggestions(meeting_id,status,id);

CREATE TABLE meeting_attendees (
    meeting_id BIGINT NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(meeting_id,user_id)
);

CREATE TABLE meeting_images (
    id BIGSERIAL PRIMARY KEY,
    meeting_id BIGINT NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    uploaded_by BIGINT NOT NULL REFERENCES users(id),
    storage_name VARCHAR(180) NOT NULL UNIQUE,
    mime_type VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE meeting_audit (
    id BIGSERIAL PRIMARY KEY,
    meeting_id BIGINT NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    event VARCHAR(80) NOT NULL,
    details TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_meeting_audit_meeting ON meeting_audit(meeting_id,id DESC);

CREATE TABLE protocol_documents (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    document_date DATE NOT NULL,
    note TEXT NOT NULL DEFAULT '',
    storage_name VARCHAR(180) NOT NULL UNIQUE,
    original_name VARCHAR(255) NOT NULL,
    uploaded_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_protocol_documents_date ON protocol_documents(document_date DESC,id DESC);
