-- ============================================================
-- Free Would - AI Tools Platform
-- Complete Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS freewould_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE freewould_db;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('user','admin') DEFAULT 'user',
  credits INT DEFAULT 100,
  avatar VARCHAR(255) DEFAULT NULL,
  plan VARCHAR(50) DEFAULT 'free',
  status ENUM('active','banned') DEFAULT 'active',
  email_verified TINYINT DEFAULT 0,
  reset_token VARCHAR(255) DEFAULT NULL,
  reset_expires DATETIME DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API KEYS TABLE
-- ============================================================
CREATE TABLE api_keys (
  id INT PRIMARY KEY AUTO_INCREMENT,
  provider_name VARCHAR(100) NOT NULL,
  type ENUM('image','video','chat') NOT NULL,
  api_key TEXT NOT NULL,
  api_secret TEXT DEFAULT NULL,
  model VARCHAR(100) DEFAULT NULL,
  endpoint VARCHAR(255) DEFAULT NULL,
  priority INT DEFAULT 1,
  rate_limit INT DEFAULT 100,
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- IMAGE GENERATIONS TABLE
-- ============================================================
CREATE TABLE image_generations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  prompt TEXT NOT NULL,
  negative_prompt TEXT DEFAULT NULL,
  image_url TEXT NOT NULL,
  provider VARCHAR(100) NOT NULL,
  model VARCHAR(100) DEFAULT NULL,
  size VARCHAR(20) DEFAULT '1024x1024',
  style VARCHAR(50) DEFAULT NULL,
  quality VARCHAR(20) DEFAULT 'standard',
  credits_used INT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VIDEO GENERATIONS TABLE
-- ============================================================
CREATE TABLE video_generations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  prompt TEXT NOT NULL,
  video_url TEXT DEFAULT NULL,
  provider VARCHAR(100) NOT NULL,
  duration INT DEFAULT 5,
  resolution VARCHAR(20) DEFAULT '720p',
  fps INT DEFAULT 24,
  style VARCHAR(50) DEFAULT NULL,
  status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
  credits_used INT DEFAULT 10,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CHAT HISTORIES TABLE
-- ============================================================
CREATE TABLE chat_histories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  session_id VARCHAR(100) NOT NULL,
  session_name VARCHAR(255) DEFAULT 'New Chat',
  role ENUM('user','assistant','system') NOT NULL,
  message LONGTEXT NOT NULL,
  model VARCHAR(100) DEFAULT NULL,
  provider VARCHAR(100) DEFAULT NULL,
  tokens_used INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PLANS TABLE
-- ============================================================
CREATE TABLE plans (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) DEFAULT 0.00,
  credits INT DEFAULT 100,
  duration ENUM('monthly','yearly','lifetime') DEFAULT 'monthly',
  features JSON DEFAULT NULL,
  image_limit INT DEFAULT 10,
  video_limit INT DEFAULT 2,
  chat_limit INT DEFAULT 100,
  is_popular TINYINT DEFAULT 0,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SUBSCRIPTIONS TABLE
-- ============================================================
CREATE TABLE subscriptions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  plan_id INT NOT NULL,
  start_date DATETIME NOT NULL,
  end_date DATETIME DEFAULT NULL,
  status ENUM('active','expired','cancelled') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRANSACTIONS TABLE
-- ============================================================
CREATE TABLE transactions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  type ENUM('credit','debit','refund') NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','completed','failed') DEFAULT 'completed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SITE SETTINGS TABLE
-- ============================================================
CREATE TABLE site_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value LONGTEXT DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API USAGE LOGS TABLE
-- ============================================================
CREATE TABLE api_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  provider VARCHAR(100) NOT NULL,
  type VARCHAR(50) NOT NULL,
  status ENUM('success','failed') DEFAULT 'success',
  response_time INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_api_keys_provider ON api_keys(provider_name);
CREATE INDEX idx_api_keys_type ON api_keys(type);
CREATE INDEX idx_api_keys_active ON api_keys(is_active);
CREATE INDEX idx_image_gen_user ON image_generations(user_id);
CREATE INDEX idx_image_gen_created ON image_generations(created_at);
CREATE INDEX idx_video_gen_user ON video_generations(user_id);
CREATE INDEX idx_video_gen_status ON video_generations(status);
CREATE INDEX idx_video_gen_created ON video_generations(created_at);
CREATE INDEX idx_chat_user ON chat_histories(user_id);
CREATE INDEX idx_chat_session ON chat_histories(session_id);
CREATE INDEX idx_chat_created ON chat_histories(created_at);
CREATE INDEX idx_subscriptions_user ON subscriptions(user_id);
CREATE INDEX idx_subscriptions_status ON subscriptions(status);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_api_logs_user ON api_logs(user_id);
CREATE INDEX idx_api_logs_provider ON api_logs(provider);
CREATE INDEX idx_api_logs_created ON api_logs(created_at);

-- ============================================================
-- DEFAULT DATA: Admin User
-- Password: Admin@123 (bcrypt hash)
-- ============================================================
INSERT INTO users (name, email, password, role, credits, status, email_verified)
VALUES (
  'Admin',
  'admin@freewould.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin',
  999999,
  'active',
  1
);

-- ============================================================
-- DEFAULT DATA: Plans
-- ============================================================
INSERT INTO plans (name, price, credits, duration, features, image_limit, video_limit, chat_limit, is_popular, status) VALUES
(
  'Free',
  0.00,
  100,
  'monthly',
  '["10 Images/month","2 Videos/month","100 Chats/month","Standard Quality","Basic Models"]',
  10,
  2,
  100,
  0,
  'active'
),
(
  'Pro',
  19.99,
  1000,
  'monthly',
  '["100 Images/month","20 Videos/month","Unlimited Chats","HD Quality","All Models","Priority Support"]',
  100,
  20,
  1000,
  1,
  'active'
),
(
  'Enterprise',
  49.99,
  5000,
  'monthly',
  '["Unlimited Images","Unlimited Videos","Unlimited Chats","4K Quality","All Models","24/7 Support","API Access"]',
  99999,
  99999,
  99999,
  0,
  'active'
);

-- ============================================================
-- DEFAULT DATA: Site Settings
-- ============================================================
INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'Free Would'),
('site_description', 'AI Tools Platform - Text to Image, Video & Chat'),
('site_email', 'admin@freewould.com'),
('default_credits', '100'),
('maintenance_mode', '0'),
('allow_registration', '1'),
('email_verification', '0'),
('primary_color', '#7C3AED'),
('secondary_color', '#06B6D4');
