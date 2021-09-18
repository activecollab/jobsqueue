CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(191) NOT NULL DEFAULT '',
    `jobs_count` int(10) unsigned NOT NULL DEFAULT '0',
    `created_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
