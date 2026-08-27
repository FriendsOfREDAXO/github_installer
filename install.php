<?php

/**
 * GitHub Installer - Installation
 * 
 * Erstellt die Datenbank-Tabelle für GitHub-Cache
 */

// Tabelle für GitHub-Item-Cache erstellen
// update.php führt diese Datei bei jedem Update erneut aus (siehe update.php), rex_sql_table::get()
// liefert dann die BEREITS existierende Tabelle inkl. ihrer aktuellen Spalten zurück. addColumn()/
// addIndex() werfen in dem Fall eine RuntimeException ("... already exists"), weil sie strikt nur
// für neue Spalten/Indizes gedacht sind - ensureColumn()/ensureIndex() sind das idempotente
// Äquivalent (legen an, falls fehlend, sonst kein Fehler) und funktionieren sowohl bei der
// Erstinstallation als auch bei jedem erneuten Lauf über eine bereits bestehende Tabelle.
$table = rex_sql_table::get(rex::getTable('github_installer_items'));

$table
    ->ensureColumn(new rex_sql_column('id', 'int(10) unsigned', false, null, 'auto_increment'))
    ->ensureColumn(new rex_sql_column('item_type', 'varchar(50)', false))
    ->ensureColumn(new rex_sql_column('item_key', 'varchar(255)', false))
    ->ensureColumn(new rex_sql_column('item_name', 'varchar(255)', false))
    ->ensureColumn(new rex_sql_column('repo_owner', 'varchar(255)', false))
    ->ensureColumn(new rex_sql_column('repo_name', 'varchar(255)', false))
    ->ensureColumn(new rex_sql_column('repo_branch', 'varchar(255)', false, 'main'))
    ->ensureColumn(new rex_sql_column('repo_path', 'varchar(500)', false))
    ->ensureColumn(new rex_sql_column('installed_at', 'datetime', false))
    ->ensureColumn(new rex_sql_column('github_last_update', 'datetime', true))
    ->ensureColumn(new rex_sql_column('github_last_commit_sha', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('github_last_commit_message', 'text', true))
    ->ensureColumn(new rex_sql_column('github_cache_updated_at', 'datetime', true))
    ->setPrimaryKey('id')
    ->ensureIndex(new rex_sql_index('unique_item', ['item_type', 'item_key'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('item_type', ['item_type']))
    ->ensureIndex(new rex_sql_index('repo_lookup', ['repo_owner', 'repo_name']))
    ->ensure();
