-- Upgrade Actra Backend to 1.11.0
-- Password hashes of yuf v4.37 (password_hash(), Argon2id) need up to 255 characters.

ALTER TABLE `auth_user`
    MODIFY `passwordHash` varchar(255) DEFAULT NULL;
