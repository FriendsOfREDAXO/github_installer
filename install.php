<?php

/**
 * Installation script for GitHub Installer
 * Creates database table for tracking installations
 */

$sql = rex_sql::factory();

// Create table for tracking installations
$sql->setQuery('
    CREATE TABLE IF NOT EXISTS `' . rex::getTable('github_installer_items') . '` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `item_type` varchar(50) NOT NULL,
        `item_key` varchar(255) NOT NULL,
        `item_name` varchar(255) NOT NULL,
        `repo_owner` varchar(255) NOT NULL,
        `repo_name` varchar(255) NOT NULL,
        `repo_branch` varchar(255) NOT NULL DEFAULT "main",
        `repo_path` varchar(500) NOT NULL,
        `installed_at` datetime NOT NULL,
        `github_last_update` datetime DEFAULT NULL,
        `github_last_commit_sha` varchar(255) DEFAULT NULL,
        `github_last_commit_message` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_item` (`item_type`, `item_key`),
        KEY `item_type` (`item_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');
