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
