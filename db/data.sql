-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Erstellungszeit: 25. Apr 2026 um 20:04
-- Server-Version: 11.8.6-MariaDB-ubu2404-log
-- PHP-Version: 8.3.26

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Datenbank: `db`
--

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_group`
--

TRUNCATE TABLE `auth_group`;
--
-- Daten für Tabelle `auth_group`
--

INSERT INTO `auth_group` (`ID`, `title`) VALUES
    (1, 'Administrator');

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_group_right`
--

TRUNCATE TABLE `auth_group_right`;
--
-- Daten für Tabelle `auth_group_right`
--

INSERT INTO `auth_group_right` (`ID`, `groupID`, `rightName`) VALUES
    (1, 1, 'manage_users');

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_ipWhitelist`
--

TRUNCATE TABLE `auth_ipWhitelist`;
--
-- TRUNCATE Tabelle vor dem Einfügen `auth_login`
--

TRUNCATE TABLE `auth_login`;
--
-- TRUNCATE Tabelle vor dem Einfügen `auth_right`
--

TRUNCATE TABLE `auth_right`;
--
-- Daten für Tabelle `auth_right`
--

INSERT INTO `auth_right` (`name`, `title`) VALUES
    ('manage_users', 'Benutzer verwalten');

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_session`
--

TRUNCATE TABLE `auth_session`;
--
-- TRUNCATE Tabelle vor dem Einfügen `auth_token`
--

TRUNCATE TABLE `auth_token`;
--
-- TRUNCATE Tabelle vor dem Einfügen `auth_user`
--

TRUNCATE TABLE `auth_user`;
--
-- Daten für Tabelle `auth_user`
--

INSERT INTO `auth_user` (`ID`, `registeredByID`, `registered`, `invited`, `email`, `phone`, `firstName`, `lastName`, `active`, `lastSuccessfulLogin`) VALUES
    (1, NULL, NOW(), null, 'admin@actra.ch', '', 'Admin', 'User', 1, NULL);

--
-- TRUNCATE Tabelle vor dem Einfügen `auth_user_group`
--

TRUNCATE TABLE `auth_user_group`;
--
-- Daten für Tabelle `auth_user_group`
--

INSERT INTO `auth_user_group` (`ID`, `userID`, `groupID`) VALUES
    (1, 1, 1);
SET FOREIGN_KEY_CHECKS=1;
COMMIT;