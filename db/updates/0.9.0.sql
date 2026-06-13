-- Upgrade Actra Backend to 0.9.0
-- Adds API key management support.

CREATE TABLE `auth_api_key`
(
    `userID`     mediumint(8) UNSIGNED NOT NULL,
    `publicID`   char(6)               NOT NULL,
    `apiKey`     varchar(200)          NOT NULL,
    `salt`       char(16)              NOT NULL,
    `registered` timestamp             NOT NULL DEFAULT current_timestamp()
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

ALTER TABLE `auth_api_key`
    ADD PRIMARY KEY (`userID`),
    ADD UNIQUE KEY `publicID` (`publicID`),
    ADD KEY `apiKey` (`apiKey`);

ALTER TABLE `auth_api_key`
    ADD CONSTRAINT `auth_api_key_ibfk_1`
        FOREIGN KEY (`userID`) REFERENCES `auth_user` (`ID`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;