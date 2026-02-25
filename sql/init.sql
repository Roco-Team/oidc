-- Database Initialization Script for OIDC Service
-- Integrates all required tables and fixes foreign key constraints

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(64) UNIQUE NOT NULL,
  `email` VARCHAR(128) UNIQUE NOT NULL,
  `email_verified` BOOLEAN DEFAULT FALSE,
  `password_hash` VARCHAR(255),
  `phone_e164` VARCHAR(32),
  `wechat_openid` VARCHAR(128) UNIQUE,
  `wechat_login_2fa` BOOLEAN DEFAULT FALSE,
  `ban` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. WebAuthn Credentials (Passkeys)
-- Fixed: user_id type matches users.id (BIGINT)
-- Fixed: id is auto-increment for compatibility with AccountController
CREATE TABLE IF NOT EXISTS `webauthn_credentials` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL,
  `credential_id` TEXT NOT NULL,
  `public_key` TEXT NOT NULL,
  `attestation_object` TEXT,
  `sign_count` INT NOT NULL DEFAULT 0,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_webauthn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Rate Limits
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `key_hash` VARCHAR(64) NOT NULL,
  `counter` INT NOT NULL DEFAULT 1,
  `reset_at` INT NOT NULL,
  PRIMARY KEY (`key_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. OAuth2 Clients
CREATE TABLE IF NOT EXISTS `oauth2_clients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` VARCHAR(64) NOT NULL UNIQUE,
    `client_secret` VARCHAR(128) NOT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `redirect_uris` JSON NOT NULL,
    `scopes` JSON NOT NULL,
    `pkce_required` TINYINT(1) NOT NULL DEFAULT 1,
    `wechat_required` TINYINT(1) NOT NULL DEFAULT 0,
    `revoked` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. OAuth2 Auth Codes
-- Fixed: user_id matches users.id (BIGINT)
CREATE TABLE IF NOT EXISTS `oauth2_auth_codes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(128) NOT NULL UNIQUE,
    `user_id` BIGINT NOT NULL,
    `client_id` VARCHAR(64) NOT NULL,
    `redirect_uri` VARCHAR(255) NOT NULL,
    `code_challenge` VARCHAR(255) NOT NULL,
    `nonce` TEXT DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `revoked` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. OAuth2 Access Tokens
-- Fixed: user_id matches users.id (BIGINT)
CREATE TABLE IF NOT EXISTS `oauth2_access_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token` VARCHAR(128) NOT NULL UNIQUE,
    `user_id` BIGINT NOT NULL,
    `client_id` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `revoked` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. OAuth2 Refresh Tokens
CREATE TABLE IF NOT EXISTS `oauth2_refresh_tokens` (
  `token` VARCHAR(256) PRIMARY KEY,
  `access_token` VARCHAR(256) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `revoked` BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Email Verifications
CREATE TABLE IF NOT EXISTS `email_verifications` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL,
  `email` VARCHAR(128) NOT NULL,
  `code` VARCHAR(16) NOT NULL,
  `request_token` TEXT DEFAULT NULL,
  `expire_at` TIMESTAMP NOT NULL,
  `verified` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Admin Users
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(64) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `totp_secret` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. User Sessions
-- Fixed: user_id matches users.id (BIGINT)
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL,
  `session_token` VARCHAR(64) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `revoked` TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Admin Sessions
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `session_token` VARCHAR(64) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `revoked` TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. User Audit Logs
-- Fixed: user_id matches users.id (BIGINT)
CREATE TABLE IF NOT EXISTS `user_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL,
  `action` TEXT NOT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Admin Audit Logs
-- Fixed: target_user_id matches users.id (BIGINT)
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `action` TEXT NOT NULL,
  `target_user_id` BIGINT DEFAULT NULL,
  `target_client_id` INT DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_webauthn_credentials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` BIGINT NOT NULL,
    `credential_id` TEXT NOT NULL,
    `public_key` TEXT NOT NULL,
    `sign_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
