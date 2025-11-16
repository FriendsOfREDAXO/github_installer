<?php

namespace FriendsOfREDAXO\GitHubInstaller;

/**
 * GitHub Item Cache Manager
 * 
 * Verwaltet den Cache für GitHub-Items (Module, Templates, Actions)
 * um API-Anfragen zu minimieren
 */
class GitHubItemCache
{
    private const CACHE_LIFETIME = 3600; // 1 Stunde Standard
    
    /**
     * Speichert oder aktualisiert GitHub-Item-Informationen
     * 
     * @param string $itemType Type: 'module', 'template', 'action'
     * @param string $itemKey REDAXO key field
     * @param string $itemName Item name
     * @param string $repoOwner GitHub owner
     * @param string $repoName GitHub repository
     * @param string $repoBranch Branch name
     * @param string $repoPath Path in repository
     * @param string|null $githubLastUpdate Last commit date (Y-m-d H:i:s)
     * @param string|null $commitSha Commit SHA
     * @param string|null $commitMessage Commit message
     */
    public static function save(
        string $itemType,
        string $itemKey,
        string $itemName,
        string $repoOwner,
        string $repoName,
        string $repoBranch,
        string $repoPath,
        ?string $githubLastUpdate = null,
        ?string $commitSha = null,
        ?string $commitMessage = null
    ): void {
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('github_installer_items'));
        
        // Prüfen ob bereits vorhanden
        $existing = self::get($itemType, $itemKey);
        
        $sql->setValue('item_type', $itemType);
        $sql->setValue('item_key', $itemKey);
        $sql->setValue('item_name', $itemName);
        $sql->setValue('repo_owner', $repoOwner);
        $sql->setValue('repo_name', $repoName);
        $sql->setValue('repo_branch', $repoBranch);
        $sql->setValue('repo_path', $repoPath);
        
        if ($githubLastUpdate) {
            $sql->setValue('github_last_update', $githubLastUpdate);
        }
        if ($commitSha) {
            $sql->setValue('github_last_commit_sha', $commitSha);
        }
        if ($commitMessage) {
            $sql->setValue('github_last_commit_message', $commitMessage);
        }
        
        $sql->setValue('github_cache_updated_at', date('Y-m-d H:i:s'));
        
        if ($existing) {
            // Update
            $sql->setWhere('id = :id', ['id' => $existing['id']]);
            $sql->update();
        } else {
            // Insert
            $sql->setValue('installed_at', date('Y-m-d H:i:s'));
            $sql->insert();
        }
    }
    
    /**
     * Holt GitHub-Item-Informationen aus dem Cache
     * 
     * @param string $itemType Type: 'module', 'template', 'action'
     * @param string $itemKey REDAXO key field
     * @return array|null Item data oder null wenn nicht gefunden
     */
    public static function get(string $itemType, string $itemKey): ?array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . \rex::getTable('github_installer_items') . ' 
             WHERE item_type = :type AND item_key = :key',
            ['type' => $itemType, 'key' => $itemKey]
        );
        
        if ($sql->getRows() > 0) {
            return [
                'id' => $sql->getValue('id'),
                'item_type' => $sql->getValue('item_type'),
                'item_key' => $sql->getValue('item_key'),
                'item_name' => $sql->getValue('item_name'),
                'repo_owner' => $sql->getValue('repo_owner'),
                'repo_name' => $sql->getValue('repo_name'),
                'repo_branch' => $sql->getValue('repo_branch'),
                'repo_path' => $sql->getValue('repo_path'),
                'installed_at' => $sql->getValue('installed_at'),
                'github_last_update' => $sql->getValue('github_last_update'),
                'github_last_commit_sha' => $sql->getValue('github_last_commit_sha'),
                'github_last_commit_message' => $sql->getValue('github_last_commit_message'),
                'github_cache_updated_at' => $sql->getValue('github_cache_updated_at'),
            ];
        }
        
        return null;
    }
    
    /**
     * Prüft ob der Cache für ein Item noch gültig ist
     * 
     * @param string $itemType Type: 'module', 'template', 'action'
     * @param string $itemKey REDAXO key field
     * @param int $cacheLifetime Cache-Lebensdauer in Sekunden
     * @return bool True wenn Cache noch gültig
     */
    public static function isCacheValid(string $itemType, string $itemKey, int $cacheLifetime = self::CACHE_LIFETIME): bool
    {
        $item = self::get($itemType, $itemKey);
        
        if (!$item || !$item['github_cache_updated_at']) {
            return false;
        }
        
        $cacheTime = strtotime($item['github_cache_updated_at']);
        $now = time();
        
        return ($now - $cacheTime) < $cacheLifetime;
    }
    
    /**
     * Aktualisiert die GitHub-Informationen für ein Item
     * 
     * @param string $itemType Type: 'module', 'template', 'action'
     * @param string $itemKey REDAXO key field
     * @param GitHubApi $github GitHub API instance
     * @return bool True wenn erfolgreich aktualisiert
     */
    public static function refreshGitHubData(string $itemType, string $itemKey, GitHubApi $github): bool
    {
        $item = self::get($itemType, $itemKey);
        
        if (!$item) {
            return false;
        }
        
        try {
            // Letztes Commit-Datum holen
            $lastUpdate = $github->getLastCommitDate(
                $item['repo_owner'],
                $item['repo_name'],
                $item['repo_path'],
                $item['repo_branch']
            );
            
            if ($lastUpdate) {
                $sql = \rex_sql::factory();
                $sql->setTable(\rex::getTable('github_installer_items'));
                $sql->setValue('github_last_update', $lastUpdate);
                $sql->setValue('github_cache_updated_at', date('Y-m-d H:i:s'));
                $sql->setWhere('id = :id', ['id' => $item['id']]);
                $sql->update();
                
                return true;
            }
        } catch (\Exception $e) {
            error_log("GitHub Cache Refresh Error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Löscht einen Cache-Eintrag
     * 
     * @param string $itemType Type: 'module', 'template', 'action'
     * @param string $itemKey REDAXO key field
     */
    public static function delete(string $itemType, string $itemKey): void
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . \rex::getTable('github_installer_items') . ' 
             WHERE item_type = :type AND item_key = :key',
            ['type' => $itemType, 'key' => $itemKey]
        );
    }
    
    /**
     * Holt alle Items eines bestimmten Typs
     * 
     * @param string $itemType Type: 'module', 'template', 'action'
     * @return array Liste von Items
     */
    public static function getAllByType(string $itemType): array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . \rex::getTable('github_installer_items') . ' 
             WHERE item_type = :type',
            ['type' => $itemType]
        );
        
        $items = [];
        while ($sql->hasNext()) {
            $items[] = [
                'id' => $sql->getValue('id'),
                'item_type' => $sql->getValue('item_type'),
                'item_key' => $sql->getValue('item_key'),
                'item_name' => $sql->getValue('item_name'),
                'repo_owner' => $sql->getValue('repo_owner'),
                'repo_name' => $sql->getValue('repo_name'),
                'repo_branch' => $sql->getValue('repo_branch'),
                'repo_path' => $sql->getValue('repo_path'),
                'installed_at' => $sql->getValue('installed_at'),
                'github_last_update' => $sql->getValue('github_last_update'),
                'github_last_commit_sha' => $sql->getValue('github_last_commit_sha'),
                'github_last_commit_message' => $sql->getValue('github_last_commit_message'),
                'github_cache_updated_at' => $sql->getValue('github_cache_updated_at'),
            ];
            $sql->next();
        }
        
        return $items;
    }
    
    /**
     * Leert den gesamten Cache
     */
    public static function clearAll(): void
    {
        $sql = \rex_sql::factory();
        $sql->setQuery('TRUNCATE TABLE ' . \rex::getTable('github_installer_items'));
    }
}
