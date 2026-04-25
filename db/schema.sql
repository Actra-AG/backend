-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Erstellungszeit: 25. Apr 2026 um 20:04
-- Server-Version: 11.8.6-MariaDB-ubu2404-log
-- PHP-Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Datenbank: `db`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_group`
--

CREATE TABLE `auth_group` (
                              `ID` mediumint(8) UNSIGNED NOT NULL,
                              `title` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_group_right`
--

CREATE TABLE `auth_group_right` (
                                    `ID` mediumint(8) UNSIGNED NOT NULL,
                                    `groupID` mediumint(8) UNSIGNED NOT NULL,
                                    `rightName` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_ipWhitelist`
--

CREATE TABLE `auth_ipWhitelist` (
                                    `ID` mediumint(8) UNSIGNED NOT NULL,
                                    `userID` mediumint(8) UNSIGNED NOT NULL,
                                    `ipAddress` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_login`
--

CREATE TABLE `auth_login` (
                              `ID` mediumint(8) UNSIGNED NOT NULL,
                              `userID` mediumint(8) UNSIGNED DEFAULT NULL,
                              `registered` timestamp NOT NULL DEFAULT current_timestamp(),
                              `sessionId` varchar(200) NOT NULL,
                              `ipAddress` varchar(200) NOT NULL,
                              `email` varchar(200) NOT NULL,
                              `result` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_right`
--

CREATE TABLE `auth_right` (
                              `name` varchar(200) NOT NULL,
                              `title` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_session`
--

CREATE TABLE `auth_session` (
                                `ID` mediumint(8) UNSIGNED NOT NULL,
                                `parentID` mediumint(8) UNSIGNED DEFAULT NULL,
                                `userID` mediumint(8) UNSIGNED NOT NULL,
                                `lastAction` datetime DEFAULT NULL,
                                `sessionId` varchar(200) NOT NULL,
                                `ipAddress` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_token`
--

CREATE TABLE `auth_token` (
                              `ID` mediumint(8) UNSIGNED NOT NULL,
                              `userID` mediumint(8) UNSIGNED NOT NULL,
                              `registered` timestamp NOT NULL DEFAULT current_timestamp(),
                              `registeredClient` text NOT NULL,
                              `type` varchar(200) NOT NULL,
                              `claimed` datetime DEFAULT NULL,
                              `claimedClient` text DEFAULT NULL,
                              `token` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user`
--

CREATE TABLE `auth_user` (
                             `ID` mediumint(8) UNSIGNED NOT NULL,
                             `registeredByID` mediumint(8) UNSIGNED DEFAULT NULL,
                             `registered` timestamp NOT NULL DEFAULT current_timestamp(),
                             `invited` datetime DEFAULT NULL,
                             `email` varchar(200) NOT NULL,
                             `firstName` varchar(200) NOT NULL,
                             `lastName` varchar(200) NOT NULL,
                             `active` tinyint(3) UNSIGNED NOT NULL,
                             `lastSuccessfulLogin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user_group`
--

CREATE TABLE `auth_user_group` (
                                   `ID` mediumint(8) UNSIGNED NOT NULL,
                                   `userID` mediumint(8) UNSIGNED NOT NULL,
                                   `groupID` mediumint(8) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `auth_group`
--
ALTER TABLE `auth_group`
    ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `auth_group_right`
--
ALTER TABLE `auth_group_right`
    ADD PRIMARY KEY (`ID`),
    ADD KEY `groupID` (`groupID`),
    ADD KEY `rightName` (`rightName`);

--
-- Indizes für die Tabelle `auth_ipWhitelist`
--
ALTER TABLE `auth_ipWhitelist`
    ADD PRIMARY KEY (`ID`),
    ADD KEY `userID` (`userID`);

--
-- Indizes für die Tabelle `auth_login`
--
ALTER TABLE `auth_login`
    ADD PRIMARY KEY (`ID`),
    ADD KEY `userID` (`userID`),
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
    ADD PRIMARY KEY (`ID`),
    ADD KEY `parentID` (`parentID`),
    ADD KEY `userID` (`userID`);

--
-- Indizes für die Tabelle `auth_token`
--
ALTER TABLE `auth_token`
    ADD PRIMARY KEY (`ID`),
    ADD KEY `token` (`token`),
    ADD KEY `userID` (`userID`);

--
-- Indizes für die Tabelle `auth_user`
--
ALTER TABLE `auth_user`
    ADD PRIMARY KEY (`ID`),
    ADD UNIQUE KEY `email` (`email`),
    ADD KEY `registeredByID` (`registeredByID`);

--
-- Indizes für die Tabelle `auth_user_group`
--
ALTER TABLE `auth_user_group`
    ADD PRIMARY KEY (`ID`),
    ADD KEY `userID` (`userID`),
    ADD KEY `groupID` (`groupID`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `auth_group`
--
ALTER TABLE `auth_group`
    MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_group_right`
--
ALTER TABLE `auth_group_right`
    MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_ipWhitelist`
--
ALTER TABLE `auth_ipWhitelist`
    MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_login`
--
ALTER TABLE `auth_login`
    MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_session`
--
ALTER TABLE `auth_session`
    MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_token`
--
ALTER TABLE `auth_token`
    MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_user`
--
ALTER TABLE `auth_user`
    MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_user_group`
--
ALTER TABLE `auth_user_group`
    MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `auth_group_right`
--
ALTER TABLE `auth_group_right`
    ADD CONSTRAINT `auth_group_right_ibfk_1` FOREIGN KEY (`groupID`) REFERENCES `auth_group` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `auth_group_right_ibfk_2` FOREIGN KEY (`rightName`) REFERENCES `auth_right` (`name`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_login`
--
ALTER TABLE `auth_login`
    ADD CONSTRAINT `auth_login_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `auth_user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_session`
--
ALTER TABLE `auth_session`
    ADD CONSTRAINT `auth_session_ibfk_1` FOREIGN KEY (`parentID`) REFERENCES `auth_session` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `auth_session_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `auth_user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_token`
--
ALTER TABLE `auth_token`
    ADD CONSTRAINT `auth_token_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `auth_user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_user`
--
ALTER TABLE `auth_user`
    ADD CONSTRAINT `auth_user_ibfk_1` FOREIGN KEY (`registeredByID`) REFERENCES `auth_user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `auth_user_group`
--
ALTER TABLE `auth_user_group`
    ADD CONSTRAINT `auth_user_group_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `auth_user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD CONSTRAINT `auth_user_group_ibfk_2` FOREIGN KEY (`groupID`) REFERENCES `auth_group` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;