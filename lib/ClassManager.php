<?php

namespace FriendsOfREDAXO\GitHubInstaller;

use rex_path;
use rex_file;
use rex_dir;

/**
 * Manager für Klassen-Installation und -Upload
 */
class ClassManager
{
    private GitHubApi $github;
    private RepositoryManager $repoManager;
    private string $projectLibPath;
    
    public function __construct()
    {
        $this->github = new GitHubApi();
        $this->repoManager = new RepositoryManager();
        $this->projectLibPath = rex_path::addon('project') . 'lib/gitClasses/';
    }
    
    /**
     * Klasse installieren
     */
    public function installClass(string $repoKey, string $className): bool
    {
        $repositories = $this->repoManager->getRepositories();
        
        if (!isset($repositories[$repoKey])) {
            throw new \Exception('Repository not found');
        }
        
        $repo = $repositories[$repoKey];
        $classes = $this->repoManager->getClasses($repoKey);
        
        if (!isset($classes[$className])) {
            throw new \Exception('Class not found in repository');
        }
        
        $classData = $classes[$className];
        $targetDir = $this->projectLibPath . $className . '/';
        
        // Zielverzeichnis erstellen
        if (!rex_dir::create($targetDir)) {
            throw new \Exception('Cannot create target directory: ' . $targetDir);
        }
        
        // Hauptklassen-Datei installieren
        $filename = $classData['filename'] ?? $className . '.php';
        $targetFile = $targetDir . $filename;
        
        if (!rex_file::put($targetFile, $classData['content'])) {
            throw new \Exception('Cannot write class file: ' . $targetFile);
        }
        
        // Zusätzliche Dateien installieren (falls vorhanden)
        $this->installAdditionalFiles($repo, $className, $targetDir);
        
        // Config-Datei erstellen für Metadaten
        $this->createConfigFile($targetDir, $classData);
        
        return true;
    }
    
    /**
     * Klasse aktualisieren
     */
    public function updateClass(string $repoKey, string $className): bool
    {
        $targetDir = $this->projectLibPath . $className . '/';
        
        if (!is_dir($targetDir)) {
            throw new \Exception('Class not installed locally');
        }
        
        // Backup erstellen
        $backupDir = $targetDir . '.backup_' . date('Y-m-d_H-i-s') . '/';
        if (!rex_dir::copy($targetDir, $backupDir)) {
            throw new \Exception('Cannot create backup');
        }
        
        try {
            // Klasse neu installieren
            $result = $this->installClass($repoKey, $className);
            
            // Backup löschen bei Erfolg
            rex_dir::delete($backupDir);
            
            return $result;
            
        } catch (\Exception $e) {
            // Bei Fehler: Backup wiederherstellen
            rex_dir::delete($targetDir);
            rex_dir::copy($backupDir, $targetDir);
            rex_dir::delete($backupDir);
            
            throw $e;
        }
    }
    
    /**
     * Klasse zu GitHub hochladen
     */
    public function uploadClass(string $repoKey, string $className, string $commitMessage = ''): bool
    {
        $repositories = $this->repoManager->getRepositories();
        
        if (!isset($repositories[$repoKey])) {
            throw new \Exception('Repository not found');
        }
        
        $repo = $repositories[$repoKey];
        $classDir = $this->projectLibPath . $className . '/';
        
        if (!is_dir($classDir)) {
            throw new \Exception('Class directory not found: ' . $classDir);
        }
        
        // Repository-Struktur sicherstellen
        $this->ensureRepositoryStructure($repo['owner'], $repo['repo'], $repo['branch'] ?? 'main');
        
        // Alle Dateien im Klassen-Verzeichnis hochladen
        $files = $this->getClassFiles($classDir);
        
        if (empty($files)) {
            throw new \Exception('No files found in class directory');
        }
        
        $commitMessage = $commitMessage ?: "Update class {$className}";
        
        foreach ($files as $relativePath => $content) {
            $githubPath = "classes/{$className}/{$relativePath}";
            
            $this->github->uploadFile(
                $repo['owner'],
                $repo['repo'],
                $githubPath,
                $content,
                $commitMessage,
                $repo['branch'] ?? 'main'
            );
        }
        
        return true;
    }
    
    /**
     * Repository-Struktur sicherstellen
     * Erstellt README-Dateien in den Hauptordnern, falls diese noch nicht existieren
     */
    private function ensureRepositoryStructure(string $owner, string $repo, string $branch): void
    {
        $folders = [
            'modules' => "# Module\n\nDieser Ordner enthält REDAXO-Module.\n\nJedes Modul hat einen eigenen Unterordner mit:\n- `input.php` - Eingabe-Template\n- `output.php` - Ausgabe-Template\n- `config.yml` - Modul-Konfiguration\n",
            'templates' => "# Templates\n\nDieser Ordner enthält REDAXO-Templates.\n\nJedes Template hat einen eigenen Unterordner mit:\n- `template.php` - Template-Code\n- `config.yml` - Template-Konfiguration\n",
            'classes' => "# Classes\n\nDieser Ordner enthält PHP-Klassen.\n\nKlassen werden automatisch von REDAXO geladen.\n",
        ];
        
        foreach ($folders as $folder => $readmeContent) {
            try {
                // Prüfen ob Ordner bereits existiert (indem wir versuchen, die README zu lesen)
                if (!$this->github->fileExists($owner, $repo, "{$folder}/README.md", $branch)) {
                    // README im Hauptordner erstellen
                    $this->github->createOrUpdateFile(
                        $owner,
                        $repo,
                        "{$folder}/README.md",
                        $readmeContent,
                        "Repository-Struktur: {$folder} Ordner erstellt",
                        $branch
                    );
                }
            } catch (\Exception $e) {
                // Fehler ignorieren - wenn es schon existiert ist das OK
            }
        }
    }
    
    /**
     * Alle lokalen Klassen abrufen
     */
    public function getLocalClasses(): array
    {
        $classes = [];
        
        if (!is_dir($this->projectLibPath)) {
            return $classes;
        }
        
        $directories = scandir($this->projectLibPath);
        
        foreach ($directories as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            
            $classDir = $this->projectLibPath . $dir . '/';
            
            if (!is_dir($classDir)) {
                continue;
            }
            
            $classData = $this->analyzeLocalClass($dir, $classDir);
            if ($classData) {
                $classes[$dir] = $classData;
            }
        }
        
        return $classes;
    }
    
    /**
     * Zusätzliche Dateien aus Repository installieren
     */
    private function installAdditionalFiles(array $repo, string $className, string $targetDir): void
    {
        try {
            $contents = $this->github->getRepositoryContents(
                $repo['owner'],
                $repo['repo'],
                "classes/{$className}",
                $repo['branch'] ?? 'main'
            );
            
            foreach ($contents as $item) {
                if ($item['type'] === 'file' && $item['name'] !== ($className . '.php')) {
                    try {
                        $content = $this->github->getFileContent(
                            $repo['owner'],
                            $repo['repo'],
                            "classes/{$className}/{$item['name']}",
                            $repo['branch'] ?? 'main'
                        );
                        
                        rex_file::put($targetDir . $item['name'], $content);
                        
                    } catch (\Exception $e) {
                        // Einzelne Datei konnte nicht geladen werden - weiter machen
                        error_log("Could not install additional file {$item['name']}: " . $e->getMessage());
                    }
                }
            }
            
        } catch (\Exception $e) {
            // Keine zusätzlichen Dateien vorhanden oder Fehler - kein Problem
        }
    }
    
    /**
     * Config-Datei für Metadaten erstellen
     */
    private function createConfigFile(string $targetDir, array $classData): void
    {
        $config = [
            'title' => $classData['title'] ?? '',
            'description' => $classData['description'] ?? '',
            'version' => $classData['version'] ?? '1.0.0',
            'author' => $classData['author'] ?? '',
            'filename' => $classData['filename'] ?? '',
            'namespace' => $classData['namespace'] ?? '',
            'installed_at' => date('Y-m-d H:i:s'),
            'source_path' => $classData['path'] ?? ''
        ];
        
        $yamlContent = "# Class Configuration\n";
        foreach ($config as $key => $value) {
            if ($value !== '' && $value !== null) {
                $yamlContent .= "{$key}: " . (is_string($value) ? '"' . addslashes($value) . '"' : $value) . "\n";
            }
        }
        
        rex_file::put($targetDir . 'config.yml', $yamlContent);
    }
    
    /**
     * Alle Dateien in einem Klassen-Verzeichnis abrufen
     */
    private function getClassFiles(string $classDir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($classDir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($classDir, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath); // Windows compatibility
                
                $content = rex_file::get($file->getPathname());
                if ($content !== false) {
                    $files[$relativePath] = $content;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Lokale Klasse analysieren
     */
    private function analyzeLocalClass(string $className, string $classDir): ?array
    {
        // Config-Datei suchen
        $configFile = $classDir . 'config.yml';
        $config = [];
        
        if (file_exists($configFile)) {
            $configContent = rex_file::get($configFile);
            if ($configContent) {
                $config = $this->parseSimpleYaml($configContent);
            }
        }
        
        // Hauptklassen-Datei finden
        $phpFiles = glob($classDir . '*.php');
        $mainFile = null;
        
        if (!empty($config['filename'])) {
            $mainFile = $classDir . $config['filename'];
        } elseif (file_exists($classDir . $className . '.php')) {
            $mainFile = $classDir . $className . '.php';
        } elseif (!empty($phpFiles)) {
            $mainFile = $phpFiles[0];
        }
        
        if (!$mainFile || !file_exists($mainFile)) {
            return null;
        }
        
        $content = rex_file::get($mainFile);
        if (!$content) {
            return null;
        }
        
        return [
            'title' => $config['title'] ?? $className,
            'description' => $config['description'] ?? $this->extractClassDescription($content),
            'version' => $config['version'] ?? $this->extractClassVersion($content) ?? '1.0.0',
            'author' => $config['author'] ?? $this->extractClassAuthor($content),
            'filename' => basename($mainFile),
            'namespace' => $config['namespace'] ?? $this->extractNamespace($content),
            'installed_at' => $config['installed_at'] ?? (file_exists($configFile) ? date('Y-m-d H:i:s', filemtime($configFile)) : ''),
            'source_path' => $config['source_path'] ?? '',
            'local_path' => $classDir,
            'has_config' => file_exists($configFile),
            'file_count' => count(glob($classDir . '*'))
        ];
    }
    
    /**
     * Einfacher YAML-Parser
     */
    private function parseSimpleYaml(string $content): array
    {
        $result = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value, ' "\'');
                
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif (is_numeric($value)) {
                    $value = is_float($value) ? (float)$value : (int)$value;
                }
                
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Namespace aus PHP-Code extrahieren
     */
    private function extractNamespace(string $content): string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Beschreibung aus PHP-Kommentaren extrahieren
     */
    private function extractClassDescription(string $content): string
    {
        if (preg_match('/@description\s+(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/\/\*\*(.*?)\*\//s', $content, $matches)) {
            $comment = $matches[1];
            $lines = explode("\n", $comment);
            
            foreach ($lines as $line) {
                $line = trim($line, " \t*");
                if (!empty($line) && !preg_match('/@\w+/', $line)) {
                    return substr($line, 0, 200);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Version aus PHP-Kommentaren extrahieren
     */
    private function extractClassVersion(string $content): string
    {
        if (preg_match('/@version\s+(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Autor aus PHP-Kommentaren extrahieren
     */
    private function extractClassAuthor(string $content): string
    {
        if (preg_match('/@author\s+(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
}