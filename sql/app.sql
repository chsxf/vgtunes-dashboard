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
