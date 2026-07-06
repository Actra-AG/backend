-- Upgrade Actra Backend to 0.12.0
-- Adds password login and password reset support for existing installations.

ALTER TABLE `auth_user`
    ADD `passwordSalt`       char(16)     DEFAULT NULL AFTER `lastSuccessfulLogin`,
    ADD `passwordHash`       varchar(200) DEFAULT NULL AFTER `passwordSalt`,
    ADD `wrongLoginAttempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `passwordHash`;