CREATE TABLE IF NOT EXISTS `jobs_queue` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `type` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
    `channel` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT 'main',
    `batch_id` int(10) unsigned,
    `data` JSON NOT NULL,
    `available_at` datetime DEFAULT NULL,
    `reservation_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `reserved_at` datetime DEFAULT NULL,
    `attempts` smallint(6) DEFAULT '0',
    `process_id` int(10) unsigned DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `reservation_key` (`reservation_key`),
    KEY `type` (`type`),
    KEY `channel` (`channel`),
    KEY `batch_id` (`batch_id`),
    KEY `available_at` (`available_at`),
    KEY `reserved_at` (`reserved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
