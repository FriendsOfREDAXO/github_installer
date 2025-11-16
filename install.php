<?php

/**
 * GitHub Installer - Installation
 * 
 * Erstellt die Datenbank-Tabelle für GitHub-Cache
 */

// Tabelle für GitHub-Item-Cache erstellen
$table = rex_sql_table::get(rex::getTable('github_installer_items'));

$table
    ->addColumn(new rex_sql_column('id', 'int(10) unsigned', false, null, 'auto_increment'))
    ->addColumn(new rex_sql_column('item_type', 'varchar(50)', false))
    ->addColumn(new rex_sql_column('item_key', 'varchar(255)', false))
    ->addColumn(new rex_sql_column('item_name', 'varchar(255)', false))
    ->addColumn(new rex_sql_column('repo_owner', 'varchar(255)', false))
    ->addColumn(new rex_sql_column('repo_name', 'varchar(255)', false))
    ->addColumn(new rex_sql_column('repo_branch', 'varchar(255)', false, 'main'))
    ->addColumn(new rex_sql_column('repo_path', 'varchar(500)', false))
    ->addColumn(new rex_sql_column('installed_at', 'datetime', false))
    ->addColumn(new rex_sql_column('github_last_update', 'datetime', true))
    ->addColumn(new rex_sql_column('github_last_commit_sha', 'varchar(255)', true))
    ->addColumn(new rex_sql_column('github_last_commit_message', 'text', true))
    ->addColumn(new rex_sql_column('github_cache_updated_at', 'datetime', true))
    ->setPrimaryKey('id')
    ->addIndex(new rex_sql_index('unique_item', ['item_type', 'item_key'], rex_sql_index::UNIQUE))
    ->addIndex(new rex_sql_index('item_type', ['item_type']))
    ->addIndex(new rex_sql_index('repo_lookup', ['repo_owner', 'repo_name']))
    ->ensure();
