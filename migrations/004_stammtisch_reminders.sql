-- Erinnerungen eine Woche vorher und am Tag des Stammtischs (zusätzlich zur Stunden-Erinnerung).
ALTER TABLE meetings ADD COLUMN reminder_week_sent_at TIMESTAMP;
ALTER TABLE meetings ADD COLUMN reminder_day_sent_at TIMESTAMP;
