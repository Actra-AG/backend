-- Upgrade Actra Backend to 1.1.0
-- Adds the language of a backend user (NULL = language of the main backend route).

ALTER TABLE `auth_user`
    ADD `language` varchar(10) DEFAULT NULL AFTER `lastName`;