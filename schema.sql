CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT UNIQUE,
    password_hash TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('member','spiess','admin')),
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    created_by INTEGER NOT NULL,
    reason TEXT NOT NULL,
    amount NUMERIC NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','paid','cancelled')),
    occurred_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS drinks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE COLLATE NOCASE,
    emoji TEXT,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    is_approved INTEGER NOT NULL DEFAULT 1 CHECK (is_approved IN (0,1)),
    created_by INTEGER,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS user_drink_preferences (
    user_id INTEGER PRIMARY KEY,
    drink_id INTEGER,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (drink_id) REFERENCES drinks(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS drink_status_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reset_by INTEGER NOT NULL,
    reason TEXT,
    reset_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reset_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS drink_reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sent_by INTEGER NOT NULL,
    target TEXT NOT NULL CHECK (target IN ('all_members','members_without_choice')),
    message TEXT NOT NULL,
    recipients_count INTEGER NOT NULL DEFAULT 0,
    sent_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sent_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    endpoint TEXT NOT NULL UNIQUE,
    p256dh TEXT NOT NULL,
    auth_token TEXT NOT NULL,
    user_agent TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chronicle_years (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    year INTEGER NOT NULL UNIQUE,
    zugkoenig TEXT,
    zugfuehrer TEXT,
    kassierer TEXT,
    schriftfuehrer TEXT,
    erster_offizier TEXT,
    zweiter_offizier TEXT,
    notes TEXT,
    published INTEGER NOT NULL DEFAULT 1 CHECK (published IN (0,1))
);

CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active);
CREATE INDEX IF NOT EXISTS idx_drinks_active_approved ON drinks(is_active, is_approved, sort_order);
CREATE INDEX IF NOT EXISTS idx_push_user ON push_subscriptions(user_id);

INSERT OR IGNORE INTO drinks(name, emoji, is_active, is_approved, sort_order) VALUES
('Alt', '🍺', 1, 1, 10),
('Pils', '🍻', 1, 1, 20),
('Radler', '🍋', 1, 1, 30),
('Cola', '🥤', 1, 1, 40),
('Wasser', '💧', 1, 1, 50);
