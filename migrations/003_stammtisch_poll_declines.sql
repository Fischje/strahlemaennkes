-- "Ich kann an keinem der Termine": Absagen in der Terminfindung, damit sichtbar ist, wer abgestimmt hat.
CREATE TABLE meeting_poll_declines (
    meeting_id BIGINT NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(meeting_id,user_id)
);
