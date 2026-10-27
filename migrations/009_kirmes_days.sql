-- Kirmes-Planung: je Tag Antreten, Kleidung und Mitbringsel.
CREATE TABLE kirmes_days (
    id BIGSERIAL PRIMARY KEY,
    event_date DATE NOT NULL,
    title VARCHAR(160) NOT NULL,
    assembly_time TIME,
    location VARCHAR(250) NOT NULL DEFAULT '',
    attire TEXT NOT NULL DEFAULT '',
    bring TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_kirmes_days_date ON kirmes_days(event_date, assembly_time);
