<?php

/**
 * Uninstall script for GitHub Installer
 * Removes database table using rex_sql_table
 */

rex_sql_table::get(rex::getTable('github_installer_items'))->drop();
