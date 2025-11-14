<?php

namespace FriendsOfREDAXO\GitHubInstaller;

use rex_sql;
use rex;

/**
 * Tracks installed items (modules, templates, classes) and their GitHub metadata
 */
class InstallationTracker
{
    private const TABLE = 'github_installer_items';
    
    /**
     * Save or update installation information
     * 
     * @param string $itemType Type of item: 'module', 'template', 'class'
     * @param string $itemKey Unique key for the item
     * @param string $itemName Display name of the item
     * @param string $repoOwner GitHub repository owner
     * @param string $repoName GitHub repository name
     * @param string $repoBranch GitHub branch
     * @param string $repoPath Path in repository (e.g., 'modules/gblock')
     * @param array|null $commitInfo Last commit information from GitHub
     */
    public function saveInstallation(
        string $itemType,
        string $itemKey,
        string $itemName,
        string $repoOwner,
        string $repoName,
        string $repoBranch,
        string $repoPath,
        ?array $commitInfo = null
    ): void {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable(self::TABLE));
        
        // Check if record already exists
        $existing = $this->getInstallation($itemType, $itemKey);
        
        if ($existing) {
            // Update existing record
            $sql->setWhere(['id' => $existing['id']]);
            $sql->setValue('item_name', $itemName);
            $sql->setValue('repo_owner', $repoOwner);
            $sql->setValue('repo_name', $repoName);
            $sql->setValue('repo_branch', $repoBranch);
            $sql->setValue('repo_path', $repoPath);
            
            if ($commitInfo) {
                $sql->setValue('github_last_update', $commitInfo['date']);
                $sql->setValue('github_last_commit_sha', $commitInfo['sha']);
                $sql->setValue('github_last_commit_message', $commitInfo['message']);
            }
            
            $sql->update();
        } else {
            // Insert new record
            $sql->setValue('item_type', $itemType);
            $sql->setValue('item_key', $itemKey);
            $sql->setValue('item_name', $itemName);
            $sql->setValue('repo_owner', $repoOwner);
            $sql->setValue('repo_name', $repoName);
            $sql->setValue('repo_branch', $repoBranch);
            $sql->setValue('repo_path', $repoPath);
            $sql->setValue('installed_at', date('Y-m-d H:i:s'));
            
            if ($commitInfo) {
                $sql->setValue('github_last_update', $commitInfo['date']);
                $sql->setValue('github_last_commit_sha', $commitInfo['sha']);
                $sql->setValue('github_last_commit_message', $commitInfo['message']);
            }
            
            $sql->insert();
        }
    }
    
    /**
     * Get installation information for an item
     * 
     * @param string $itemType Type of item: 'module', 'template', 'class'
     * @param string $itemKey Unique key for the item
     * @return array|null Installation data or null if not found
     */
    public function getInstallation(string $itemType, string $itemKey): ?array
    {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'SELECT * FROM ' . rex::getTable(self::TABLE) . ' WHERE item_type = ? AND item_key = ? LIMIT 1',
            [$itemType, $itemKey]
        );
        
        if ($sql->getRows() === 0) {
            return null;
        }
        
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
        ];
    }
    
    /**
     * Get all installations by type
     * 
     * @param string|null $itemType Filter by type, or null for all
     * @return array List of installations
     */
    public function getAllInstallations(?string $itemType = null): array
    {
        $sql = rex_sql::factory();
        
        if ($itemType) {
            $sql->setQuery(
                'SELECT * FROM ' . rex::getTable(self::TABLE) . ' WHERE item_type = ? ORDER BY installed_at DESC',
                [$itemType]
            );
        } else {
            $sql->setQuery('SELECT * FROM ' . rex::getTable(self::TABLE) . ' ORDER BY installed_at DESC');
        }
        
        $installations = [];
        while ($sql->hasNext()) {
            $installations[] = [
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
            ];
            $sql->next();
        }
        
        return $installations;
    }
    
    /**
     * Check if an update is available by comparing stored commit with current GitHub commit
     * 
     * @param string $itemType Type of item
     * @param string $itemKey Unique key for the item
     * @param GitHubApi $github GitHub API instance
     * @return array Status information ['available' => bool, 'current_commit' => array|null, 'new_commit' => array|null]
     */
    public function checkForUpdate(string $itemType, string $itemKey, GitHubApi $github): array
    {
        $installation = $this->getInstallation($itemType, $itemKey);
        
        if (!$installation) {
            return ['available' => false, 'current_commit' => null, 'new_commit' => null];
        }
        
        // Fetch latest commit from GitHub
        $newCommit = $github->getLastCommit(
            $installation['repo_owner'],
            $installation['repo_name'],
            $installation['repo_path'],
            $installation['repo_branch']
        );
        
        if (!$newCommit) {
            return ['available' => false, 'current_commit' => null, 'new_commit' => null];
        }
        
        $currentSha = $installation['github_last_commit_sha'] ?? '';
        $newSha = $newCommit['sha'] ?? '';
        
        return [
            'available' => $currentSha !== $newSha && !empty($currentSha),
            'current_commit' => [
                'sha' => $currentSha,
                'date' => $installation['github_last_update'],
                'message' => $installation['github_last_commit_message'],
            ],
            'new_commit' => $newCommit,
        ];
    }
    
    /**
     * Delete installation tracking record
     * 
     * @param string $itemType Type of item
     * @param string $itemKey Unique key for the item
     */
    public function deleteInstallation(string $itemType, string $itemKey): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable(self::TABLE));
        $sql->setWhere('item_type = :type AND item_key = :key', ['type' => $itemType, 'key' => $itemKey]);
        $sql->delete();
    }
}
