-- PostgreSQL baseline for Strahlemännkes Beta 0.6.0.
-- This is intentionally a clean current-state schema, not a replay of the historical SQLite migrations.

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(80) NOT NULL,
    email VARCHAR(254),
    password_hash TEXT NOT NULL,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'member' CHECK (role IN ('member','spiess','admin')),
    is_active SMALLINT NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    is_treasurer SMALLINT NOT NULL DEFAULT 0 CHECK (is_treasurer IN (0,1)),
    is_leader SMALLINT NOT NULL DEFAULT 0 CHECK (is_leader IN (0,1)),
    is_secretary SMALLINT NOT NULL DEFAULT 0 CHECK (is_secretary IN (0,1)),
    must_change_password SMALLINT NOT NULL DEFAULT 0 CHECK (must_change_password IN (0,1)),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX uq_users_username_ci ON users (LOWER(username));
CREATE UNIQUE INDEX uq_users_email_ci ON users (LOWER(email)) WHERE email IS NOT NULL AND email <> '';
CREATE INDEX idx_users_active ON users(is_active);

CREATE TABLE drinks (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    emoji VARCHAR(32),
    is_active SMALLINT NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    is_approved SMALLINT NOT NULL DEFAULT 1 CHECK (is_approved IN (0,1)),
    is_sektbar SMALLINT NOT NULL DEFAULT 0 CHECK (is_sektbar IN (0,1)),
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX uq_drinks_name_ci ON drinks (LOWER(name));
CREATE INDEX idx_drinks_active_approved ON drinks(is_active,is_approved,sort_order);

CREATE TABLE user_drink_preferences (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    drink_id BIGINT REFERENCES drinks(id) ON DELETE SET NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE user_drink_favorites (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    drink_id BIGINT NOT NULL REFERENCES drinks(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(user_id,drink_id)
);

CREATE TABLE drink_status_resets (
    id BIGSERIAL PRIMARY KEY,
    reset_by BIGINT NOT NULL REFERENCES users(id),
    reason TEXT,
    reset_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE drink_reminders (
    id BIGSERIAL PRIMARY KEY,
    sent_by BIGINT NOT NULL REFERENCES users(id),
    target VARCHAR(40) NOT NULL CHECK (target IN ('all_members','members_without_choice')),
    message TEXT NOT NULL,
    recipients_count INTEGER NOT NULL DEFAULT 0,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE fines (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    created_by BIGINT NOT NULL REFERENCES users(id),
    reason TEXT NOT NULL,
    details TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'open' CHECK (status IN ('open','paid','cancelled')),
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dispatched_at TIMESTAMP,
    dispatched_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    settled_at TIMESTAMP
);
CREATE UNIQUE INDEX idx_one_dispatched_round_per_user ON fines(user_id) WHERE status='open' AND dispatched_at IS NOT NULL;
CREATE INDEX idx_fines_status_occurred ON fines(status,occurred_at,id);

CREATE TABLE remember_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_remember_tokens_user ON remember_tokens(user_id);
CREATE INDEX idx_remember_tokens_expiry ON remember_tokens(expires_at);

CREATE TABLE password_reset_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_password_reset_user ON password_reset_tokens(user_id);
CREATE INDEX idx_password_reset_expiry ON password_reset_tokens(expires_at);

CREATE TABLE push_subscriptions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    endpoint TEXT NOT NULL UNIQUE,
    p256dh TEXT NOT NULL,
    auth_token TEXT NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_push_user ON push_subscriptions(user_id);

CREATE TABLE chronicle_years (
    id BIGSERIAL PRIMARY KEY,
    year INTEGER NOT NULL UNIQUE,
    zugkoenig TEXT,
    zugfuehrer TEXT,
    kassierer TEXT,
    schriftfuehrer TEXT,
    erster_offizier TEXT,
    zweiter_offizier TEXT,
    zugspiess TEXT,
    notes TEXT,
    published SMALLINT NOT NULL DEFAULT 1 CHECK (published IN (0,1))
);

CREATE TABLE cash_settings (
    id SMALLINT PRIMARY KEY CHECK(id=1),
    monthly_fee NUMERIC(12,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE cash_balance_history (
    id BIGSERIAL PRIMARY KEY,
    amount NUMERIC(12,2) NOT NULL,
    balance_date DATE NOT NULL,
    note TEXT,
    updated_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_cash_balance_date ON cash_balance_history(balance_date DESC,id DESC);

CREATE TABLE spiess_delegations (
    id BIGSERIAL PRIMARY KEY,
    representative_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP,
    ended_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    active SMALLINT NOT NULL DEFAULT 1 CHECK(active IN (0,1))
);
CREATE UNIQUE INDEX idx_one_active_spiess_delegation ON spiess_delegations(active) WHERE active=1;
CREATE INDEX idx_spiess_delegation_representative ON spiess_delegations(representative_user_id,active);

CREATE TABLE app_settings (
    setting_key VARCHAR(120) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE feature_settings (
    feature_key VARCHAR(120) PRIMARY KEY,
    is_enabled SMALLINT NOT NULL DEFAULT 0 CHECK(is_enabled IN(0,1)),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE assembly_status (
    id SMALLINT PRIMARY KEY CHECK(id=1),
    starts_at_utc TIMESTAMP NOT NULL,
    attire TEXT NOT NULL DEFAULT '',
    location VARCHAR(250) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE guest_drink_orders (
    id BIGSERIAL PRIMARY KEY,
    guest_name VARCHAR(120) NOT NULL,
    drink_id BIGINT NOT NULL REFERENCES drinks(id) ON DELETE RESTRICT,
    created_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_guest_drink_orders_drink ON guest_drink_orders(drink_id);

CREATE TABLE sektbar_state (
    id SMALLINT PRIMARY KEY CHECK(id=1),
    is_active SMALLINT NOT NULL DEFAULT 0 CHECK(is_active IN(0,1)),
    activated_at TIMESTAMP,
    activated_by BIGINT REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE sektbar_activated_drinks (
    drink_id BIGINT PRIMARY KEY REFERENCES drinks(id) ON DELETE CASCADE
);

INSERT INTO cash_settings(id,monthly_fee) VALUES(1,0);
INSERT INTO app_settings(setting_key,setting_value) VALUES('registration_enabled','0');
INSERT INTO feature_settings(feature_key,is_enabled) VALUES('sektbar',0);
INSERT INTO sektbar_state(id,is_active) VALUES(1,0);
INSERT INTO drinks(name,emoji,is_active,is_approved,sort_order) VALUES
('Alt','🍺',1,1,10),('Pils','🍻',1,1,20),('Radler','🍋',1,1,30),('Cola','🥤',1,1,40),('Wasser','💧',1,1,50);
