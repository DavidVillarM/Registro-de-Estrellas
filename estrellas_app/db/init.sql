-- Estrellas NW: schema + seed
CREATE TABLE IF NOT EXISTS companies (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS star_types (
  id SERIAL PRIMARY KEY,
  code TEXT NOT NULL UNIQUE CHECK (code IN ('FUNNY','TEACHE','EARLY','BUDDY','SMARTY')),
  name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS employees (
  id SERIAL PRIMARY KEY,
  full_name TEXT NOT NULL,
  company_id INT NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (company_id, full_name)
);

CREATE TABLE IF NOT EXISTS challenges (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS star_awards (
  id SERIAL PRIMARY KEY,
  employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
  star_type_id INT NOT NULL REFERENCES star_types(id) ON DELETE RESTRICT,
  award_date DATE NOT NULL,
  challenge_id INT NULL REFERENCES challenges(id) ON DELETE SET NULL,
  note TEXT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_star_awards_employee_date ON star_awards(employee_id, award_date DESC);
CREATE INDEX IF NOT EXISTS idx_star_awards_date ON star_awards(award_date);
CREATE INDEX IF NOT EXISTS idx_star_awards_type ON star_awards(star_type_id);

-- Seed
INSERT INTO companies(name) VALUES ('Newton Centro de Estudios') ON CONFLICT DO NOTHING;
INSERT INTO companies(name) VALUES ('Crextar S.A.') ON CONFLICT DO NOTHING;

INSERT INTO star_types(code, name) VALUES
  ('FUNNY','Funny'),
  ('TEACHE','Teache'),
  ('EARLY','Early'),
  ('BUDDY','Buddy'),
  ('SMARTY','Smarty')
ON CONFLICT DO NOTHING;

INSERT INTO challenges(name) VALUES ('Misión 00 - Fin de año') ON CONFLICT DO NOTHING;
