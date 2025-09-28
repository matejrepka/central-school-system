-- Migration: create mandatory and user subjects tables for PostgreSQL

-- Table: povinne_predmety (mandatory subjects shared by all students)
CREATE TABLE IF NOT EXISTS povinne_predmety (
    id SERIAL PRIMARY KEY,
    code VARCHAR(64) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Table: users (simple users table; passwords should be stored hashed - use bcrypt/argon2)
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    class_group VARCHAR(64), -- optional: user's class/group (e.g. "3", "B1")
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);


-- Table: subjects (user-specific subjects that students add themselves)
-- This is the table the existing API expects (name and user_id columns).
CREATE TABLE IF NOT EXISTS subjects (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    class_group VARCHAR(64), -- optional class / group (e.g. "3", "B1"), used to filter schedule
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
    CONSTRAINT fk_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Indexes for performance
-- Links and schedule for user-created subjects
CREATE TABLE IF NOT EXISTS subject_links (
    id SERIAL PRIMARY KEY,
    subject_id INTEGER NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    title VARCHAR(255),
    url TEXT NOT NULL,
    position INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS subject_schedule (
    id SERIAL PRIMARY KEY,
    subject_id INTEGER NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    day VARCHAR(16) NOT NULL, -- e.g. 'Pondelok', 'Utorok' or 'Mon','Tue'
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    type VARCHAR(32) NOT NULL, -- 'lecture' | 'exercise' | 'course'
    class_group VARCHAR(64), -- optional: which student class this entry applies to
    position INTEGER DEFAULT 0
);

-- Links and schedule for mandatory (shared) subjects
CREATE TABLE IF NOT EXISTS povinne_links (
    id SERIAL PRIMARY KEY,
    povinne_id INTEGER NOT NULL REFERENCES povinne_predmety(id) ON DELETE CASCADE,
    title VARCHAR(255),
    url TEXT NOT NULL,
    position INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS povinne_schedule (
    id SERIAL PRIMARY KEY,
    povinne_id INTEGER NOT NULL REFERENCES povinne_predmety(id) ON DELETE CASCADE,
    day VARCHAR(16) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    type VARCHAR(32) NOT NULL,
    class_group VARCHAR(64),
    position INTEGER DEFAULT 0
);

-- If there is a mapping table between users and mandatory subjects
CREATE TABLE IF NOT EXISTS user_povinne_predmety (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    povinne_predmety_id INTEGER NOT NULL REFERENCES povinne_predmety(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
    UNIQUE (user_id, povinne_predmety_id)
);

