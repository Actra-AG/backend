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
-- Tabellenstruktur für Tabelle `auth_api_key`
--

CREATE TABLE `auth_api_key`
(
    `user_id`     mediumint(8) UNSIGNED NOT NULL,
    `public_id`   char(6)               NOT NULL,
    `api_key`     varchar(200)          NOT NULL,
    `salt`       char(16)              NOT NULL,
    `registered` timestamp             NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_group`
--

CREATE TABLE `auth_group`
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
-- Tabellenstruktur für Tabelle `auth_ip_whitelist`
--

CREATE TABLE `auth_ip_whitelist`
(
    `id`        mediumint(8) UNSIGNED NOT NULL,
    `user_id`    mediumint(8) UNSIGNED NOT NULL,
    `ip_address` varchar(200)          NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_login`
--

CREATE TABLE `auth_login`
(
    `id`         mediumint(8) UNSIGNED NOT NULL,
    `user_id`     mediumint(8) UNSIGNED          DEFAULT NULL,
    `registered` timestamp             NOT NULL DEFAULT current_timestamp(),
    `session_id`  varchar(200)          NOT NULL,
    `ip_address`  varchar(200)          NOT NULL,
    `email`      varchar(200)          NOT NULL,
    `result`     tinyint(3) UNSIGNED   NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_right`
--

CREATE TABLE `auth_right`
(
    `name`  varchar(200) NOT NULL,
    `title` varchar(200) NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_session`
--

CREATE TABLE `auth_session`
(
    `id`         mediumint(8) UNSIGNED NOT NULL,
    `parent_id`   mediumint(8) UNSIGNED DEFAULT NULL,
    `user_id`     mediumint(8) UNSIGNED NOT NULL,
    `last_action` datetime              DEFAULT NULL,
    `session_id`  varchar(200)          NOT NULL,
    `ip_address`  varchar(200)          NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_token`
--

CREATE TABLE `auth_token`
(
    `id`               mediumint(8) UNSIGNED NOT NULL,
    `user_id`           mediumint(8) UNSIGNED NOT NULL,
    `registered`       timestamp             NOT NULL DEFAULT current_timestamp(),
    `registered_client` text                  NOT NULL,
    `type`             varchar(200)          NOT NULL,
    `claimed`          datetime                       DEFAULT NULL,
    `claimed_client`    text                           DEFAULT NULL,
    `token`            varchar(200)          NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user`
--

CREATE TABLE `auth_user`
(
    `id`                  mediumint(8) UNSIGNED NOT NULL,
    `registered_by_id`      mediumint(8) UNSIGNED          DEFAULT NULL,
    `registered`          timestamp             NOT NULL DEFAULT current_timestamp(),
    `invited`             datetime                       DEFAULT NULL,
    `email`               varchar(200)          NOT NULL,
    `phone`               varchar(200)          NOT NULL,
    `first_name`           varchar(200)          NOT NULL,
    `last_name`            varchar(200)          NOT NULL,
    `language`            varchar(10)                    DEFAULT NULL,
    `active`              tinyint(3) UNSIGNED   NOT NULL,
    `last_successful_login` datetime                       DEFAULT NULL,
    `password_salt`        char(16)                       DEFAULT NULL,
    `password_hash`        varchar(255)                   DEFAULT NULL,
    `wrong_login_attempts`  tinyint(3) UNSIGNED   NOT NULL DEFAULT 0
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user_group`
--

CREATE TABLE `auth_user_group`
(
    `id`      mediumint(8) UNSIGNED NOT NULL,
    `user_id`  mediumint(8) UNSIGNED NOT NULL,
    `group_id` mediumint(8) UNSIGNED NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user_notification`
--

CREATE TABLE `auth_user_notification`
(
    `id`          mediumint(8) UNSIGNED NOT NULL,
    `auth_group_id` mediumint(8) UNSIGNED NOT NULL,
    `sent_by_id`    mediumint(8) UNSIGNED NOT NULL,
    `sent_date`    timestamp             NOT NULL DEFAULT current_timestamp(),
    `subject`     varchar(200)          NOT NULL,
    `message`     text                  NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user_notification_recipient`
--

CREATE TABLE `auth_user_notification_recipient`
(
    `id`             mediumint(8) UNSIGNED NOT NULL,
    `notification_id` mediumint(8) UNSIGNED NOT NULL,
    `auth_user_id`     mediumint(8) UNSIGNED NOT NULL,
    `sent_date`       timestamp             NOT NULL DEFAULT current_timestamp(),
    `email`          varchar(200)          NOT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `auth_api_key`
--
ALTER TABLE `auth_api_key`
    ADD PRIMARY KEY (`user_id`),
    ADD UNIQUE KEY `public_id` (`public_id`),
    ADD KEY `api_key` (`api_key`);

--
-- Indizes für die Tabelle `auth_group`
--
ALTER TABLE `auth_group`
    ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `auth_group_right`
--
ALTER TABLE `auth_group_right`
    ADD PRIMARY KEY (`id`),
    ADD KEY `group_id` (`group_id`),
    ADD KEY `right_name` (`right_name`);

--
-- Indizes für die Tabelle `auth_ip_whitelist`
--
ALTER TABLE `auth_ip_whitelist`
    ADD PRIMARY KEY (`id`),
    ADD KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `auth_login`
--
ALTER TABLE `auth_login`
    ADD PRIMARY KEY (`id`),
    ADD KEY `user_id` (`user_id`),
    ADD KEY `registered` (`registered`);

--
-- Indizes für die Tabelle `auth_right`
--
ALTER TABLE `auth_right`
    ADD PRIMARY KEY (`name`);

--
-- Indizes für die Tabelle `auth_session`
--
ALTER TABLE `auth_session`
    ADD PRIMARY KEY (`id`),
    ADD KEY `parent_id` (`parent_id`),
    ADD KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `auth_token`
--
ALTER TABLE `auth_token`
    ADD PRIMARY KEY (`id`),
    ADD KEY `token` (`token`),
    ADD KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `auth_user`
--
ALTER TABLE `auth_user`
    ADD PRIMARY KEY (`id`),
    ADD UNIQUE KEY `email` (`email`),
    ADD KEY `registered_by_id` (`registered_by_id`);

--
-- Indizes für die Tabelle `auth_user_group`
--
ALTER TABLE `auth_user_group`
    ADD PRIMARY KEY (`id`),
    ADD KEY `user_id` (`user_id`),
    ADD KEY `group_id` (`group_id`);

--
-- Indizes für die Tabelle `auth_user_notification`
--
ALTER TABLE `auth_user_notification`
    ADD PRIMARY KEY (`id`),
    ADD KEY `auth_group_id` (`auth_group_id`),
    ADD KEY `sent_by_id` (`sent_by_id`);

--
-- Indizes für die Tabelle `auth_user_notification_recipient`
--
ALTER TABLE `auth_user_notification_recipient`
    ADD PRIMARY KEY (`id`),
    ADD KEY `notification_id` (`notification_id`),
    ADD KEY `auth_user_id` (`auth_user_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `auth_group`
--
ALTER TABLE `auth_group`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_group_right`
--
ALTER TABLE `auth_group_right`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_ip_whitelist`
--
ALTER TABLE `auth_ip_whitelist`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_login`
--
ALTER TABLE `auth_login`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_session`
--
ALTER TABLE `auth_session`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_token`
--
ALTER TABLE `auth_token`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_user`
--
ALTER TABLE `auth_user`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_user_group`
--
ALTER TABLE `auth_user_group`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_user_notification`
--
ALTER TABLE `auth_user_notification`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_user_notification_recipient`
--
ALTER TABLE `auth_user_notification_recipient`
    MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `auth_api_key`
--
ALTER TABLE `auth_api_key`
    ADD CONSTRAINT `auth_api_key_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_group_right`
--
ALTER TABLE `auth_group_right`
    ADD CONSTRAINT `auth_group_right_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `auth_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `auth_group_right_ibfk_2` FOREIGN KEY (`right_name`) REFERENCES `auth_right` (`name`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_ip_whitelist`
--
ALTER TABLE `auth_ip_whitelist`
    ADD CONSTRAINT `auth_ip_whitelist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_login`
--
ALTER TABLE `auth_login`
    ADD CONSTRAINT `auth_login_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_session`
--
ALTER TABLE `auth_session`
    ADD CONSTRAINT `auth_session_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `auth_session` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `auth_session_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_token`
--
ALTER TABLE `auth_token`
    ADD CONSTRAINT `auth_token_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_user`
--
ALTER TABLE `auth_user`
    ADD CONSTRAINT `auth_user_ibfk_1` FOREIGN KEY (`registered_by_id`) REFERENCES `auth_user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_user_group`
--
ALTER TABLE `auth_user_group`
    ADD CONSTRAINT `auth_user_group_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `auth_user_group_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `auth_group` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_user_notification`
--
ALTER TABLE `auth_user_notification`
    ADD CONSTRAINT `auth_user_notification_ibfk_1` FOREIGN KEY (`auth_group_id`) REFERENCES `auth_group` (`id`),
    ADD CONSTRAINT `auth_user_notification_ibfk_2` FOREIGN KEY (`sent_by_id`) REFERENCES `auth_user` (`id`),
    ADD CONSTRAINT `auth_user_notification_ibfk_3` FOREIGN KEY (`auth_group_id`) REFERENCES `auth_group` (`id`),
    ADD CONSTRAINT `auth_user_notification_ibfk_4` FOREIGN KEY (`sent_by_id`) REFERENCES `auth_user` (`id`);

--
-- Constraints der Tabelle `auth_user_notification_recipient`
--
ALTER TABLE `auth_user_notification_recipient`
    ADD CONSTRAINT `auth_user_notification_recipient_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `auth_user_notification` (`id`),
    ADD CONSTRAINT `auth_user_notification_recipient_ibfk_2` FOREIGN KEY (`auth_user_id`) REFERENCES `auth_user` (`id`);
COMMIT;