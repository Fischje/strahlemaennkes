-- Zu-/Absagen zum festgelegten Stammtisch (jederzeit änderbar, überschreibt die Stimme aus der Terminfindung).
CREATE TABLE meeting_rsvps (
    meeting_id BIGINT NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(3) NOT NULL CHECK (status IN ('yes','no')),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(meeting_id,user_id)
);
