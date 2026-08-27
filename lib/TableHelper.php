<?php

namespace FriendsOfREDAXO\GitHubInstaller;

/**
 * Gemeinsame Hilfsfunktionen für RepositoryManager, NewInstallManager, UpdateManager
 * und ClassManager: rex_module/rex_template-Lookups ("erst über key, sonst über
 * name suchen") sowie die Pfadauflösung für installierte Klassen - beides war
 * bisher in mehreren Klassen identisch dupliziert.
 */
final class TableHelper
{
    /**
     * Prüft ob eine Tabelle ein `key` Feld hat.
     */
    public static function hasKeyField(string $table): bool
    {
        $sql = \rex_sql::factory();
        $sql->setQuery('SHOW COLUMNS FROM ' . \rex::getTable($table) . ' LIKE "key"');

        return $sql->getRows() > 0;
    }

    /**
     * Sucht eine Zeile zuerst über `key` (falls die Tabelle ein key-Feld hat und
     * $key gesetzt ist), sonst über `name`.
     *
     * @return array{row: array<string, mixed>, matched_by: 'key'|'name'}|null
     */
    public static function findExisting(string $table, string $key, string $name): ?array
    {
        if ($key && self::hasKeyField($table)) {
            $sql = \rex_sql::factory();
            $sql->setQuery('SELECT * FROM ' . \rex::getTable($table) . ' WHERE `key` = ?', [$key]);
            // getRows() > 0 heißt "mind. eine Zeile matched", aber getRow() erst hier
            // tatsächlich abrufen und auf 'id' prüfen, statt uns blind auf beides zu
            // verlassen - schützt gegen "Undefined array key", falls je eine Zeile ohne
            // die erwarteten Spalten zurückkommt.
            if ($sql->getRows() > 0) {
                $row = $sql->getRow();
                if (isset($row['id'])) {
                    return ['row' => $row, 'matched_by' => 'key'];
                }
            }
        }

        if ($name) {
            $sql = \rex_sql::factory();
            $sql->setQuery('SELECT * FROM ' . \rex::getTable($table) . ' WHERE name = ?', [$name]);
            if ($sql->getRows() > 0) {
                $row = $sql->getRow();
                if (isset($row['id'])) {
                    return ['row' => $row, 'matched_by' => 'name'];
                }
            }
        }

        return null;
    }

    /**
     * Basis-Verzeichnis für installierte Klassen. Das Ziel-Addon ist über
     * `class_install_addon` konfigurierbar (Default: 'project') statt wie bisher
     * hart auf ein Addon namens "project" verdrahtet zu sein - das war nur in
     * dieser einen Instanz zufällig passend, nicht generell für das Addon.
     */
    public static function classInstallBasePath(): string
    {
        $addonName = \rex_addon::get('github_installer')->getConfig('class_install_addon', 'project');
        $targetAddon = \rex_addon::get($addonName);

        if (!$targetAddon->isAvailable()) {
            throw new \Exception("Ziel-Addon '{$addonName}' für die Klassen-Installation ist nicht installiert/aktiviert. Bitte in den GitHub-Installer-Einstellungen ein vorhandenes Addon eintragen.");
        }

        return \rex_path::addon($addonName) . 'lib/gitClasses/';
    }
}
