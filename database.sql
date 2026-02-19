-- ============================================
-- Expense Tracker Database Schema
-- Project 02 — Khush Patel GitHub Blueprint
-- ============================================

CREATE DATABASE IF NOT EXISTS expense_tracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE expense_tracker;

-- ---- Users Table ----
CREATE TABLE IF NOT EXISTS users (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(500) NOT NULL,
    name          VARCHAR(100) NOT NULL DEFAULT '',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ---- Categories Table ----
CREATE TABLE IF NOT EXISTS categories (
    id    INT PRIMARY KEY AUTO_INCREMENT,
    name  VARCHAR(100) NOT NULL,
    icon  VARCHAR(50)  NOT NULL DEFAULT '💰',
    type  ENUM('Income','Expense') NOT NULL,
    color VARCHAR(20)  NOT NULL DEFAULT '#6366f1'
);

-- ---- Transactions Table ----
CREATE TABLE IF NOT EXISTS transactions (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    category_id INT NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    note        VARCHAR(500),
    date        DATE NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- ---- Budgets Table ----
CREATE TABLE IF NOT EXISTS budgets (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    category_id INT NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    month       INT NOT NULL,   -- 1-12
    year        INT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_budget (user_id, category_id, month, year),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- ============================================
-- Seed Default Categories
-- ============================================
INSERT IGNORE INTO categories (name, icon, type, color) VALUES
  -- Income Categories
  ('Salary',        '💼', 'Income',  '#10b981'),
  ('Freelance',     '💻', 'Income',  '#06b6d4'),
  ('Investment',    '📈', 'Income',  '#8b5cf6'),
  ('Gift',          '🎁', 'Income',  '#f59e0b'),
  ('Other Income',  '💰', 'Income',  '#6366f1'),

  -- Expense Categories
  ('Food & Dining', '🍔', 'Expense', '#ef4444'),
  ('Housing',       '🏠', 'Expense', '#f97316'),
  ('Transport',     '🚗', 'Expense', '#eab308'),
  ('Shopping',      '🛍️', 'Expense', '#ec4899'),
  ('Healthcare',    '⚕️', 'Expense', '#14b8a6'),
  ('Education',     '📚', 'Expense', '#a855f7'),
  ('Entertainment', '🎮', 'Expense', '#3b82f6'),
  ('Utilities',     '💡', 'Expense', '#f59e0b'),
  ('Travel',        '✈️', 'Expense', '#06b6d4'),
  ('Other Expense', '📦', 'Expense', '#6b7280');
