-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Erstellungszeit: 03. Mai 2026 um 15:47
-- Server-Version: 10.11.16-MariaDB-ubu2204-log
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
                             `phone` varchar(200) NOT NULL,
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

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user_notification`
--

CREATE TABLE `auth_user_notification` (
                                          `ID` mediumint(8) UNSIGNED NOT NULL,
                                          `authGroupID` mediumint(8) UNSIGNED NOT NULL,
                                          `sentByID` mediumint(8) UNSIGNED NOT NULL,
                                          `sentDate` timestamp NOT NULL DEFAULT current_timestamp(),
                                          `subject` varchar(200) NOT NULL,
                                          `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth_user_notification_recipient`
--

CREATE TABLE `auth_user_notification_recipient` (
                                                    `ID` mediumint(8) UNSIGNED NOT NULL,
                                                    `notificationID` mediumint(8) UNSIGNED NOT NULL,
                                                    `authUserID` mediumint(8) UNSIGNED NOT NULL,
                                                    `sentDate` timestamp NOT NULL DEFAULT current_timestamp(),
                                                    `email` varchar(200) NOT NULL
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
-- Indizes für die Tabelle `auth_user_notification`
--
ALTER TABLE `auth_user_notification`
    ADD PRIMARY KEY (`ID`),
    ADD KEY `authGroupID` (`authGroupID`),
    ADD KEY `sentByID` (`sentByID`);

--
-- Indizes für die Tabelle `auth_user_notification_recipient`
--
ALTER TABLE `auth_user_notification_recipient`
    ADD PRIMARY KEY (`ID`),
    ADD KEY `notificationID` (`notificationID`),
    ADD KEY `authUserID` (`authUserID`);

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
-- AUTO_INCREMENT für Tabelle `auth_user_notification`
--
ALTER TABLE `auth_user_notification`
    MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `auth_user_notification_recipient`
--
ALTER TABLE `auth_user_notification_recipient`
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

--
-- Constraints der Tabelle `auth_user_notification`
--
ALTER TABLE `auth_user_notification`
    ADD CONSTRAINT `auth_user_notification_ibfk_1` FOREIGN KEY (`authGroupID`) REFERENCES `auth_group` (`ID`),
    ADD CONSTRAINT `auth_user_notification_ibfk_2` FOREIGN KEY (`sentByID`) REFERENCES `auth_user` (`ID`),
    ADD CONSTRAINT `auth_user_notification_ibfk_3` FOREIGN KEY (`authGroupID`) REFERENCES `auth_group` (`ID`),
    ADD CONSTRAINT `auth_user_notification_ibfk_4` FOREIGN KEY (`sentByID`) REFERENCES `auth_user` (`ID`);

--
-- Constraints der Tabelle `auth_user_notification_recipient`
--
ALTER TABLE `auth_user_notification_recipient`
    ADD CONSTRAINT `auth_user_notification_recipient_ibfk_1` FOREIGN KEY (`notificationID`) REFERENCES `auth_user_notification` (`ID`),
    ADD CONSTRAINT `auth_user_notification_recipient_ibfk_2` FOREIGN KEY (`authUserID`) REFERENCES `auth_user` (`ID`);
COMMIT;