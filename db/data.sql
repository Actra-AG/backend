-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Erstellungszeit: 25. Apr 2026 um 20:04
-- Server-Version: 11.8.6-MariaDB-ubu2404-log
-- PHP-Version: 8.3.26

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Datenbank: `db`
--

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_groups`
--

TRUNCATE TABLE `auth_groups`;
--
-- Daten für Tabelle `auth_groups`
--

INSERT INTO `auth_groups` (`id`, `title`)
VALUES (1, 'Administrator');

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_group_right`
--

TRUNCATE TABLE `auth_group_right`;
--
-- Daten für Tabelle `auth_group_right`
--

INSERT INTO `auth_group_right` (`id`, `group_id`, `right_name`)
VALUES (1, 1, 'backend_access'),
       (2, 1, 'manage_users');

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_ip_whitelists`
--

TRUNCATE TABLE `auth_ip_whitelists`;
--
-- TRUNCATE Tabelle vor dem Einfügen `auth_api_keys`
--

TRUNCATE TABLE `auth_api_keys`;
--
-- TRUNCATE Tabelle vor dem Einfügen `auth_logins`
--

TRUNCATE TABLE `auth_logins`;
--
-- TRUNCATE Tabelle vor dem Einfügen `auth_rights`
--

TRUNCATE TABLE `auth_rights`;
--
-- Daten für Tabelle `auth_rights`
--

INSERT INTO `auth_rights` (`name`, `title`)
VALUES ('backend_access', 'Zugriff ins Backend'),
       ('manage_users', 'Benutzer verwalten');

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_sessions`
--

TRUNCATE TABLE `auth_sessions`;
--
-- TRUNCATE Tabelle vor dem Einfügen `auth_tokens`
--

TRUNCATE TABLE `auth_tokens`;
--
-- TRUNCATE Tabelle vor dem Einfügen `auth_users`
--

TRUNCATE TABLE `auth_users`;
--
-- Daten für Tabelle `auth_users`
--

INSERT INTO `auth_users` (`id`, `created_by_user_id`, `created_at`, `invited_at`, `email`, `phone`, `first_name`,
                          `last_name`, `is_active`, `last_successful_login_at`)
VALUES (1, NULL, NOW(), null, 'admin@example.com', '', 'Admin', 'User', 1, NULL);

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_group_user`
--

TRUNCATE TABLE `auth_group_user`;
--
-- Daten für Tabelle `auth_group_user`
--

INSERT INTO `auth_group_user` (`id`, `user_id`, `group_id`)
VALUES (1, 1, 1);
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;