-- Zugangsart „nur Grober Unfug“: Solche Konten dürfen nur die Seite Grober Unfug nutzen.
-- Strahlemännkes blendet sie überall aus (Listen, Zählungen, Theke, Stammtisch, Push) und lässt sie nicht anmelden.
ALTER TABLE users ADD COLUMN unfug_only SMALLINT NOT NULL DEFAULT 0 CHECK (unfug_only IN (0,1));
