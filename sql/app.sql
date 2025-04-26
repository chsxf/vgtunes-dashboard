-- [VERSION: 1]

CREATE TABLE `albums` (
    `id` int UNSIGNED NOT NULL,
    `slug` char(8) COLLATE utf8mb4_general_ci NOT NULL,
    `name` tinytext COLLATE utf8mb4_general_ci NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `albums`
    ADD PRIMARY KEY (`id`),
    ADD UNIQUE KEY `slug` (`slug`(4));

ALTER TABLE `albums`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

-- [VERSION: 2]

CREATE TABLE `album_instances` (
    `album_id` INT UNSIGNED NOT NULL,
    `platform` ENUM('apple_music','deezer','spotify') NOT NULL,
    `platform_id` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    PRIMARY KEY (`album_id`, `platform`)
) ENGINE=InnoDB;

ALTER TABLE `album_instances`
    ADD FOREIGN KEY (`album_id`) REFERENCES `albums`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- [VERSION: 3]

CREATE TABLE `artists` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB;

ALTER TABLE `albums`
    ADD `artist_id` INT UNSIGNED NULL AFTER `name`;

ALTER TABLE `albums` 
    ADD FOREIGN KEY (`artist_id`) REFERENCES `artists`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- [VERSION: 4]

ALTER TABLE `albums`
    DROP FOREIGN KEY `albums_ibfk_1`;
ALTER TABLE `albums`
    ADD CONSTRAINT `albums_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- [VERSION: 5]

CREATE TABLE `spotify_access_tokens` (
    `user_id` INT UNSIGNED NOT NULL,
    `access_token` TINYTEXT NOT NULL,
    `expires_at` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`)
) ENGINE = InnoDB; 

ALTER TABLE `spotify_access_tokens`
    ADD FOREIGN KEY (`user_id`) REFERENCES `mfx_users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- [VERSION: 6]

ALTER TABLE `albums`
    CHANGE `name` `title` TINYTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- [VERSION: 7]

ALTER TABLE `album_instances`
    CHANGE `platform_id` `platform_id` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    CHANGE `platform` `platform` ENUM('apple_music','deezer','spotify','bandcamp') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- [VERSION: 8]

ALTER TABLE `artists`
    ADD `slug` CHAR(8) NULL DEFAULT NULL AFTER `name`;

UPDATE `artists`
    SET `slug` = SUBSTR(SHA1(CONCAT('random-slug-from-id', `id`)), 1, 8);

ALTER TABLE `artists`
    CHANGE `slug` `slug` CHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    ADD UNIQUE (`slug`(4)) USING BTREE;

-- [VERSION: 9]

CREATE TABLE `featured_albums` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `album_id` INT UNSIGNED NOT NULL,
    `featured_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX (`album_id`)
) ENGINE = InnoDB;

ALTER TABLE `featured_albums`
    ADD CONSTRAINT `featured_albums_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `albums`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- [VERSION: 10]

CREATE TABLE `steam_products` (
    `app_id` bigint unsigned NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
    `type` enum('game','dlc','other') COLLATE utf8mb4_general_ci NOT NULL,
    `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`app_id`)
) ENGINE=InnoDB;

-- [VERSION: 11]

ALTER TABLE `album_instances`
    CHANGE `platform` `platform` ENUM('apple_music','deezer','spotify','bandcamp','steam_game','steam_soundtrack') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
   
-- [VERSION: 12]

ALTER TABLE `steam_products`
    CHANGE `app_id` `app_id` MEDIUMINT UNSIGNED NOT NULL; 

-- [VERSION: 13]

CREATE TABLE `album_artists` (
    `album_id` INT UNSIGNED NOT NULL,
    `artist_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`album_id`, `artist_id`)
) ENGINE = InnoDB;

ALTER TABLE `album_artists`
    ADD CONSTRAINT `album_artists_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `albums`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `album_artists_ibfk_2` FOREIGN KEY (`artist_id`) REFERENCES `artists`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

INSERT INTO `album_artists`
    SELECT `id`, `artist_id` FROM `albums`;

ALTER TABLE `albums`
    DROP FOREIGN KEY `albums_ibfk_1`,
    DROP `artist_id`;

-- [VERSION: 14]

ALTER TABLE `albums`
    ADD `feature_flags` SET('bandcamp','steam','multi_artists') NOT NULL DEFAULT 'bandcamp,steam' AFTER `title`;

-- [VERSION: 15]

ALTER TABLE `album_artists`
    ADD `artist_order` TINYINT UNSIGNED NOT NULL DEFAULT '0' AFTER `artist_id`;
