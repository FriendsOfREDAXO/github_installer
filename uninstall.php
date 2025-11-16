<?php

/**
 * GitHub Installer - Deinstallation
 * 
 * Entfernt die Datenbank-Tabelle für GitHub-Cache
 */

$sql = rex_sql::factory();
$sql->setQuery('DROP TABLE IF EXISTS ' . rex::getTable('github_installer_items'));
