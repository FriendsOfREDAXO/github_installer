<?php

/**
 * Uninstall script for GitHub Installer
 * Removes database table
 */

$sql = rex_sql::factory();
$sql->setQuery('DROP TABLE IF EXISTS `' . rex::getTable('github_installer_items') . '`');
