-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Erstellungszeit: 06. Jul 2026 um 17:53
-- Server-Version: 11.8.6-MariaDB-ubu2404-log
-- PHP-Version: 8.3.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Datenbank: `db`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_api_keys`
--

CREATE TABLE `auth_api_keys`
(
    `user_id`     mediumint(8) UNSIGNED NOT NULL,
    `public_id`   char(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `api_key`     varchar(200)          NOT NULL,
    `salt`       char(16)              NOT NULL,
    `created_at` timestamp             NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_groups`
--

CREATE TABLE `auth_groups`
(
    `id`    mediumint(8) UNSIGNED NOT NULL,
    `title` varchar(200)          NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_group_right`
--

CREATE TABLE `auth_group_right`
(
    `id`        mediumint(8) UNSIGNED NOT NULL,
    `group_id`   mediumint(8) UNSIGNED NOT NULL,
    `right_name` varchar(200)          NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_ip_whitelists`
--

CREATE TABLE `auth_ip_whitelists`
(
    `id`        mediumint(8) UNSIGNED NOT NULL,
    `user_id`    mediumint(8) UNSIGNED NOT NULL,
    `ip_address` varchar(200)          NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_logins`
--

CREATE TABLE `auth_logins`
(
    `id`         mediumint(8) UNSIGNED NOT NULL,
    `user_id`     mediumint(8) UNSIGNED          DEFAULT NULL,
    `created_at` timestamp             NOT NULL DEFAULT current_timestamp(),
    `php_session_id`  varchar(200)          NOT NULL,
    `ip_address`  varchar(200)          NOT NULL,
    `email`      varchar(200)          NOT NULL,
    `result`     tinyint(3) UNSIGNED   NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_rights`
--

CREATE TABLE `auth_rights`
(
    `name`  varchar(200) NOT NULL,
    `title` varchar(200) NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_sessions`
--

CREATE TABLE `auth_sessions`
(
    `id`         mediumint(8) UNSIGNED NOT NULL,
    `parent_id`   mediumint(8) UNSIGNED DEFAULT NULL,
    `user_id`     mediumint(8) UNSIGNED NOT NULL,
    `last_action_at` datetime              DEFAULT NULL,
    `php_session_id`  varchar(200)          NOT NULL,
    `ip_address`  varchar(200)          NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_tokens`
--

CREATE TABLE `auth_tokens`
(
    `id`               mediumint(8) UNSIGNED NOT NULL,
    `user_id`           mediumint(8) UNSIGNED NOT NULL,
    `created_at`       timestamp             NOT NULL DEFAULT current_timestamp(),
    `created_client` text                  NOT NULL,
    `type`             varchar(200)          NOT NULL,
    `claimed_at`          datetime                       DEFAULT NULL,
    `claimed_client`    text                           DEFAULT NULL,
    `token_hash`       char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_users`
--

CREATE TABLE `auth_users`
(
    `id`                  mediumint(8) UNSIGNED NOT NULL,
    `created_by_user_id`      mediumint(8) UNSIGNED          DEFAULT NULL,
    `created_at`          timestamp             NOT NULL DEFAULT current_timestamp(),
    `invited_at`             datetime                       DEFAULT NULL,
    `email`               varchar(200)          NOT NULL,
    `phone`               varchar(200)          NOT NULL,
    `first_name`           varchar(200)          NOT NULL,
    `last_name`            varchar(200)          NOT NULL,
    `language`            varchar(10)                    DEFAULT NULL,
    `is_active`              tinyint(3) UNSIGNED   NOT NULL,
    `last_successful_login_at` datetime                       DEFAULT NULL,
    `password_salt`        char(16)                       DEFAULT NULL,
    `password_hash`        varchar(255)                   DEFAULT NULL,
    `wrong_login_attempts`  tinyint(3) UNSIGNED   NOT NULL DEFAULT 0
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_group_user`
--

CREATE TABLE `auth_group_user`
(
    `id`      mediumint(8) UNSIGNED NOT NULL,
    `user_id`  mediumint(8) UNSIGNED NOT NULL,
    `group_id` mediumint(8) UNSIGNED NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user_notifications`
--

CREATE TABLE `auth_user_notifications`
(
    `id`          mediumint(8) UNSIGNED NOT NULL,
    `group_id` mediumint(8) UNSIGNED NOT NULL,
    `sent_by_user_id`    mediumint(8) UNSIGNED NOT NULL,
    `sent_at`    timestamp             NOT NULL DEFAULT current_timestamp(),
    `subject`     varchar(200)          NOT NULL,
    `message`     text                  NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user_notification_recipients`
--

CREATE TABLE `auth_user_notification_recipients`
(
    `id`             mediumint(8) UNSIGNED NOT NULL,
    `user_notification_id` mediumint(8) UNSIGNED NOT NULL,
    `user_id`     mediumint(8) UNSIGNED NOT NULL,
    `sent_at`       timestamp             NOT NULL DEFAULT current_timestamp(),
    `email`          varchar(200)          NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sent_emails`
--

CREATE TABLE `sent_emails`
(
    `id`         int(10) UNSIGNED NOT NULL,
    `sent_at`    datetime         NOT NULL,
    `from_email` varchar(254)     NOT NULL,
    `to_emails`  text             NOT NULL,
    `cc_emails`  text             NOT NULL,
    `bcc_emails` text             NOT NULL,
    `subject`    text             NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `auth_api_keys`
--
ALTER TABLE `auth_api_keys`
    ADD PRIMARY KEY (`user_id`),
    ADD UNIQUE KEY `public_id` (`public_id`),
    ADD KEY `api_key` (`api_key`);

--
-- Indizes für die Tabelle `auth_groups`
--
ALTER TABLE `auth_groups`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `auth_group_right`
--
ALTER TABLE `auth_group_right`
    ADD PRIMARY KEY (`id`),
    ADD KEY `group_id` (`group_id`),
    ADD KEY `right_name` (`right_name`);

--
-- Indizes für die Tabelle `auth_ip_whitelists`
--
ALTER TABLE `auth_ip_whitelists`
    ADD PRIMARY KEY (`id`),
    ADD KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `auth_logins`
--
ALTER TABLE `auth_logins`
    ADD PRIMARY KEY (`id`),
    ADD KEY `user_id` (`user_id`),
    ADD KEY `created_at` (`created_at`);

--
-- Indizes für die Tabelle `auth_rights`
--
ALTER TABLE `auth_rights`
    ADD PRIMARY KEY (`name`);

--
-- Indizes für die Tabelle `auth_sessions`
--
ALTER TABLE `auth_sessions`
    ADD PRIMARY KEY (`id`),
    ADD KEY `parent_id` (`parent_id`),
    ADD KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `auth_tokens`
--
ALTER TABLE `auth_tokens`
    ADD PRIMARY KEY (`id`),
    ADD KEY `token_hash` (`token_hash`),
    ADD KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `auth_users`
--
ALTER TABLE `auth_users`
    ADD PRIMARY KEY (`id`),
    ADD UNIQUE KEY `email` (`email`),
    ADD KEY `created_by_user_id` (`created_by_user_id`);

--
-- Indizes für die Tabelle `auth_group_user`
--
ALTER TABLE `auth_group_user`
    ADD PRIMARY KEY (`id`),
    ADD KEY `user_id` (`user_id`),
    ADD KEY `group_id` (`group_id`);

--
-- Indizes für die Tabelle `auth_user_notifications`
--
ALTER TABLE `auth_user_notifications`
    ADD PRIMARY KEY (`id`),
    ADD KEY `group_id` (`group_id`),
    ADD KEY `sent_by_user_id` (`sent_by_user_id`);

--
-- Indizes für die Tabelle `auth_user_notification_recipients`
--
ALTER TABLE `auth_user_notification_recipients`
    ADD PRIMARY KEY (`id`),
    ADD KEY `user_notification_id` (`user_notification_id`),
    ADD KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `sent_emails`
--
ALTER TABLE `sent_emails`
    ADD PRIMARY KEY (`id`),
    ADD KEY `sent_at` (`sent_at`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `auth_groups`
--
ALTER TABLE `auth_groups`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_group_right`
--
ALTER TABLE `auth_group_right`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_ip_whitelists`
--
ALTER TABLE `auth_ip_whitelists`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_logins`
--
ALTER TABLE `auth_logins`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_sessions`
--
ALTER TABLE `auth_sessions`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_tokens`
--
ALTER TABLE `auth_tokens`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_users`
--
ALTER TABLE `auth_users`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_group_user`
--
ALTER TABLE `auth_group_user`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_user_notifications`
--
ALTER TABLE `auth_user_notifications`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_user_notification_recipients`
--
ALTER TABLE `auth_user_notification_recipients`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `sent_emails`
--
ALTER TABLE `sent_emails`
    MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `auth_api_keys`
--
ALTER TABLE `auth_api_keys`
    ADD CONSTRAINT `auth_api_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_group_right`
--
ALTER TABLE `auth_group_right`
    ADD CONSTRAINT `auth_group_right_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `auth_groups` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `auth_group_right_ibfk_2` FOREIGN KEY (`right_name`) REFERENCES `auth_rights` (`name`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_ip_whitelists`
--
ALTER TABLE `auth_ip_whitelists`
    ADD CONSTRAINT `auth_ip_whitelists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_logins`
--
ALTER TABLE `auth_logins`
    ADD CONSTRAINT `auth_logins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_sessions`
--
ALTER TABLE `auth_sessions`
    ADD CONSTRAINT `auth_sessions_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `auth_sessions` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `auth_sessions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_tokens`
--
ALTER TABLE `auth_tokens`
    ADD CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_users`
--
ALTER TABLE `auth_users`
    ADD CONSTRAINT `auth_users_ibfk_1` FOREIGN KEY (`created_by_user_id`) REFERENCES `auth_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_group_user`
--
ALTER TABLE `auth_group_user`
    ADD CONSTRAINT `auth_group_user_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `auth_group_user_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `auth_groups` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_user_notifications`
--
ALTER TABLE `auth_user_notifications`
    ADD CONSTRAINT `auth_user_notifications_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `auth_groups` (`id`),
    ADD CONSTRAINT `auth_user_notifications_ibfk_2` FOREIGN KEY (`sent_by_user_id`) REFERENCES `auth_users` (`id`),
    ADD CONSTRAINT `auth_user_notifications_ibfk_3` FOREIGN KEY (`group_id`) REFERENCES `auth_groups` (`id`),
    ADD CONSTRAINT `auth_user_notifications_ibfk_4` FOREIGN KEY (`sent_by_user_id`) REFERENCES `auth_users` (`id`);

--
-- Constraints der Tabelle `auth_user_notification_recipients`
--
ALTER TABLE `auth_user_notification_recipients`
    ADD CONSTRAINT `auth_user_notification_recipients_ibfk_1` FOREIGN KEY (`user_notification_id`) REFERENCES `auth_user_notifications` (`id`),
    ADD CONSTRAINT `auth_user_notification_recipients_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`);
COMMIT;