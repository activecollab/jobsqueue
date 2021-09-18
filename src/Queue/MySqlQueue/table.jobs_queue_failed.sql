CREATE TABLE IF NOT EXISTS `jobs_queue_failed` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `type` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
    `channel` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT 'main',
    `batch_id` int(10) unsigned,
    `data` JSON NOT NULL,
    `failed_at` datetime DEFAULT NULL,
    `reason` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `type` (`type`),
    KEY `channel` (`channel`),
    KEY `batch_id` (`batch_id`),
    KEY `failed_at` (`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
