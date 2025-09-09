<?php

namespace FriendsOfREDAXO\GitHubInstaller;

/**
 * Vereinfachter UploadManager für das Hochladen von Modulen und Templates zu GitHub
 */
class UploadManager
{
    private GitHubApi $githubApi;
    
    public function __construct(GitHubApi $githubApi)
    {
        $this->githubApi = $githubApi;
    }
    
    /**
     * Laden des Moduls in GitHub-Repository hoch
     */
    public function uploadModuleToGitHub(string $owner, string $repo, string $branch, string $author, int $moduleId): array
    {
        // Modul-Daten laden
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT * FROM rex_module WHERE id = ?', [$moduleId]);
        
        if ($sql->getRows() === 0) {
            return ['success' => false, 'message' => 'Modul nicht gefunden'];
        }
        
        $moduleData = $sql->getRow();
        $moduleKey = $moduleData['key'] ?: 'module_' . $moduleId;
        
        try {
            // Hauptverzeichnis erstellen
            $this->githubApi->createOrUpdateFile(
                $owner,
                $repo,
                $branch,
                "modules/{$moduleKey}/README.md",
                "# Modul: {$moduleData['name']}\n\n{$moduleData['name']}"
            );
            
            // Input-Template
            if (!empty($moduleData['input'])) {
                $this->githubApi->createOrUpdateFile(
                    $owner,
                    $repo,
                    $branch,
                    "modules/{$moduleKey}/input.php",
                    $moduleData['input']
                );
            }
            
            // Output-Template
            if (!empty($moduleData['output'])) {
                $this->githubApi->createOrUpdateFile(
                    $owner,
                    $repo,
                    $branch,
                    "modules/{$moduleKey}/output.php",
                    $moduleData['output']
                );
            }
            
            // Config-Datei
            $config = $this->generateModuleConfig($moduleData, $author);
            $this->githubApi->createOrUpdateFile(
                $owner,
                $repo,
                $branch,
                "modules/{$moduleKey}/config.yml",
                $config
            );
            
            // Assets hochladen
            $this->uploadAssets($owner, $repo, $branch, 'modules', $moduleKey);
            
            return ['success' => true, 'message' => "Modul '{$moduleKey}' erfolgreich hochgeladen"];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Upload: ' . $e->getMessage()];
        }
    }
    
    /**
     * Template in GitHub-Repository hochladen
     */
    public function uploadTemplateToGitHub(string $owner, string $repo, string $branch, string $author, int $templateId): array
    {
        // Template-Daten laden
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT * FROM rex_template WHERE id = ?', [$templateId]);
        
        if ($sql->getRows() === 0) {
            return ['success' => false, 'message' => 'Template nicht gefunden'];
        }
        
        $templateData = $sql->getRow();
        $templateKey = $templateData['key'] ?: 'template_' . $templateId;
        
        try {
            // Hauptverzeichnis erstellen
            $this->githubApi->createOrUpdateFile(
                $owner,
                $repo,
                $branch,
                "templates/{$templateKey}/README.md",
                "# Template: {$templateData['name']}\n\n{$templateData['name']}"
            );
            
            // Template-Datei
            if (!empty($templateData['content'])) {
                $this->githubApi->createOrUpdateFile(
                    $owner,
                    $repo,
                    $branch,
                    "templates/{$templateKey}/template.php",
                    $templateData['content']
                );
            }
            
            // Config-Datei
            $config = $this->generateTemplateConfig($templateData, $author);
            $this->githubApi->createOrUpdateFile(
                $owner,
                $repo,
                $branch,
                "templates/{$templateKey}/config.yml",
                $config
            );
            
            // Assets hochladen
            $this->uploadAssets($owner, $repo, $branch, 'templates', $templateKey);
            
            return ['success' => true, 'message' => "Template '{$templateKey}' erfolgreich hochgeladen"];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Upload: ' . $e->getMessage()];
        }
    }
    
    /**
     * Assets hochladen (CSS/JS-Dateien)
     */
    private function uploadAssets(string $owner, string $repo, string $branch, string $type, string $key): void
    {
        $assetPath = \rex_path::assets("addons/github_installer/{$type}/{$key}/");
        
        if (!is_dir($assetPath)) {
            return; // Keine Assets vorhanden
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($assetPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($assetPath, '', $file->getPathname());
                $githubPath = "{$type}/{$key}/assets/" . str_replace('\\', '/', $relativePath);
                
                $content = file_get_contents($file->getPathname());
                $this->githubApi->createOrUpdateFile($owner, $repo, $branch, $githubPath, $content);
            }
        }
    }
    
    /**
     * Modul-Config generieren
     */
    private function generateModuleConfig(array $moduleData, string $author): string
    {
        $date = \rex_formatter::intlDateTime(time());
        
        return "name: '" . addslashes($moduleData['name']) . "'
description: 'Modul exportiert aus REDAXO'
version: '1.0.0'
author: '{$author}'
created: '{$date}'
type: module
key: '" . ($moduleData['key'] ?: 'module_' . $moduleData['id']) . "'
";
    }
    
    /**
     * Template-Config generieren
     */
    private function generateTemplateConfig(array $templateData, string $author): string
    {
        $date = \rex_formatter::intlDateTime(time());
        
        return "name: '" . addslashes($templateData['name']) . "'
description: 'Template exportiert aus REDAXO'
version: '1.0.0'
author: '{$author}'
created: '{$date}'
type: template
key: '" . ($templateData['key'] ?: 'template_' . $templateData['id']) . "'
";
    }
}
