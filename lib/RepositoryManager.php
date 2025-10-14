<?php

namespace FriendsOfREDAXO\GitHubInstaller;

use rex_config;

/**
 * Repository-Manager
 */
class RepositoryManager
{
    private GitHubApi $github;
    private string $cacheDir;
    private int $cacheLifetime;
    
    public function __construct()
    {
        $this->github = new GitHubApi();
        $this->cacheDir = \rex_path::addonCache('github_installer');
        $this->cacheLifetime = \rex_config::get('github_installer', 'cache_lifetime', 3600);
    }
    
    /**
     * Alle Repositories abrufen
     */
    public function getRepositories(): array
    {
        return rex_config::get('github_installer', 'repositories', []);
    }
    
    /**
     * Repository hinzufügen
     */
    public function addRepository(string $owner, string $repo, string $displayName = '', string $branch = 'main'): bool
    {
        $repositories = $this->getRepositories();
        $key = $owner . '/' . $repo;
        
        // Prüfen ob bereits vorhanden
        if (isset($repositories[$key])) {
            throw new \Exception('Repository already exists');
        }
        
        // GitHub-Verbindung testen
        if (!$this->github->testRepository($owner, $repo, $branch)) {
            throw new \Exception('Repository not found on GitHub');
        }
        
        $repositories[$key] = [
            'owner' => $owner,
            'repo' => $repo,
            'display_name' => $displayName ?: $repo,
            'branch' => $branch,
            'added' => time(),
        ];
        
        return rex_config::set('github_installer', 'repositories', $repositories);
    }
    
    /**
     * Repository aktualisieren
     */
    public function updateRepository(string $key, string $displayName = '', string $branch = 'main'): bool
    {
        $repositories = $this->getRepositories();
        
        if (!isset($repositories[$key])) {
            throw new \Exception('Repository not found');
        }
        
        // GitHub-Verbindung testen
        [$owner, $repo] = explode('/', $key, 2);
        if (!$this->github->testRepository($owner, $repo, $branch)) {
            throw new \Exception('Repository not found on GitHub');
        }
        
        $repositories[$key]['display_name'] = $displayName ?: $repositories[$key]['repo'];
        $repositories[$key]['branch'] = $branch;
        
        return rex_config::set('github_installer', 'repositories', $repositories);
    }
    
    /**
     * Repository entfernen
     */
    public function removeRepository(string $key): bool
    {
        $repositories = $this->getRepositories();
        
        if (!isset($repositories[$key])) {
            return false;
        }
        
        // Cache für dieses Repository löschen
        [$owner, $repo] = explode('/', $key, 2);
        $this->github->clearRepositoryCache($owner, $repo);
        
        unset($repositories[$key]);
        return rex_config::set('github_installer', 'repositories', $repositories);
    }
    
    /**
     * Module aus Repository abrufen
     */
    public function getModules(string $key): array
    {
        $repositories = $this->getRepositories();
        
        if (!isset($repositories[$key])) {
            return [];
        }
        
        $repo = $repositories[$key];
        $modules = [];
        
        try {
            $contents = $this->github->getRepositoryContents($repo['owner'], $repo['repo'], 'modules', $repo['branch']);
            
            foreach ($contents as $item) {
                if ($item['type'] !== 'dir') continue;
                
                $moduleName = $item['name'];
                $moduleData = [
                    'name' => $moduleName,
                    'title' => $moduleName,
                    'description' => '',
                    'version' => '1.0.0',
                    'author' => '',
                    'key' => '',
                ];
                
                // Metadaten aus verschiedenen Quellen sammeln
                $moduleData = $this->extractModuleMetadata($repo, $moduleName);
                
                $modules[] = $moduleData;
            }
        } catch (\Exception $e) {
            // modules-Ordner nicht vorhanden
        }
        
        return $modules;
    }
    
    /**
     * Templates aus Repository abrufen
     */
    public function getTemplates(string $key): array
    {
        $repositories = $this->getRepositories();
        
        if (!isset($repositories[$key])) {
            return [];
        }
        
        $repo = $repositories[$key];
        $templates = [];
        
        try {
            $contents = $this->github->getRepositoryContents($repo['owner'], $repo['repo'], 'templates', $repo['branch']);
            
            foreach ($contents as $item) {
                if ($item['type'] !== 'dir') continue;
                
                $templateName = $item['name'];
                
                // Metadaten aus verschiedenen Quellen sammeln
                $templateData = $this->extractTemplateMetadata($repo, $templateName);
                
                $templates[] = $templateData;
            }
        } catch (\Exception $e) {
            // templates-Ordner nicht vorhanden
        }
        
        return $templates;
    }
    
    /**
     * Einfacher YAML-Parser für Basis-Konfigurationen
     */
    public function parseSimpleYaml(string $content): array
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
                
                // Basis-Typen erkennen
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
     * Modul-Metadaten aus verschiedenen Quellen extrahieren
     */
    private function extractModuleMetadata(array $repo, string $moduleName): array
    {
        $moduleData = [
            'name' => $moduleName,
            'title' => $moduleName,
            'description' => '',
            'version' => '1.0.0',
            'author' => '',
            'key' => '',
        ];
        
        // 1. config.yml laden falls vorhanden (primär für stuffx-Repositories)
        if ($this->github->fileExists($repo['owner'], $repo['repo'], "modules/{$moduleName}/config.yml", $repo['branch'])) {
            try {
                $configContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "modules/{$moduleName}/config.yml", 
                    $repo['branch']
                );
                $metadata = $this->parseSimpleYaml($configContent);
                $moduleData = array_merge($moduleData, $metadata);
            } catch (\Exception $e) {
                // config.yml nicht lesbar
            }
        }
        
        // 1b. package.yml als Fallback laden falls config.yml nicht vorhanden
        if (empty($moduleData['description']) && $this->github->fileExists($repo['owner'], $repo['repo'], "modules/{$moduleName}/package.yml", $repo['branch'])) {
            try {
                $packageContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "modules/{$moduleName}/package.yml", 
                    $repo['branch']
                );
                $metadata = $this->parseSimpleYaml($packageContent);
                $moduleData = array_merge($moduleData, $metadata);
            } catch (\Exception $e) {
                // package.yml nicht lesbar
            }
        }
        
        // 2. README.md für Beschreibung nutzen falls package.yml keine hat
        if (empty($moduleData['description']) && $this->github->fileExists($repo['owner'], $repo['repo'], "modules/{$moduleName}/README.md", $repo['branch'])) {
            try {
                $readmeContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "modules/{$moduleName}/README.md", 
                    $repo['branch']
                );
                $description = $this->extractDescriptionFromReadme($readmeContent);
                if ($description) {
                    $moduleData['description'] = $description;
                }
            } catch (\Exception $e) {
                // README.md nicht lesbar
            }
        }
        
        // 3. Input.php nach Kommentaren durchsuchen
        if (empty($moduleData['description'])) {
            try {
                $inputContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "modules/{$moduleName}/input.php", 
                    $repo['branch']
                );
                $description = $this->extractDescriptionFromPhp($inputContent);
                if ($description) {
                    $moduleData['description'] = $description;
                }
            } catch (\Exception $e) {
                // input.php nicht vorhanden
            }
        }
        
        // 4. Fallback: Hübscher Name aus Ordnername
        $moduleData['title'] = $this->beautifyName($moduleName);
        
        // 5. Assets-Information hinzufügen
        $moduleData['has_assets'] = $this->github->fileExists($repo['owner'], $repo['repo'], "modules/{$moduleName}/assets", $repo['branch']);
        
        // 6. README-Link hinzufügen falls README.md vorhanden
        if ($this->github->fileExists($repo['owner'], $repo['repo'], "modules/{$moduleName}/README.md", $repo['branch'])) {
            $moduleData['readme_url'] = "https://github.com/{$repo['owner']}/{$repo['repo']}/blob/{$repo['branch']}/modules/{$moduleName}/README.md";
        }
        
        return $moduleData;
    }
    
    /**
     * Template-Metadaten aus verschiedenen Quellen extrahieren
     */
    private function extractTemplateMetadata(array $repo, string $templateName): array
    {
        $templateData = [
            'name' => $templateName,
            'title' => $templateName,
            'description' => '',
            'version' => '1.0.0',
            'author' => '',
            'key' => '',
        ];
        
        // 1. config.yml laden falls vorhanden (primär für stuffx-Repositories)
        if ($this->github->fileExists($repo['owner'], $repo['repo'], "templates/{$templateName}/config.yml", $repo['branch'])) {
            try {
                $configContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "templates/{$templateName}/config.yml", 
                    $repo['branch']
                );
                $metadata = $this->parseSimpleYaml($configContent);
                $templateData = array_merge($templateData, $metadata);
            } catch (\Exception $e) {
                // config.yml nicht lesbar
            }
        }
        
        // 1b. package.yml als Fallback laden falls config.yml nicht vorhanden
        if (empty($templateData['description']) && $this->github->fileExists($repo['owner'], $repo['repo'], "templates/{$templateName}/package.yml", $repo['branch'])) {
            try {
                $packageContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "templates/{$templateName}/package.yml", 
                    $repo['branch']
                );
                $metadata = $this->parseSimpleYaml($packageContent);
                $templateData = array_merge($templateData, $metadata);
            } catch (\Exception $e) {
                // package.yml nicht lesbar
            }
        }
        
        // 2. README.md für Beschreibung nutzen
        if (empty($templateData['description']) && $this->github->fileExists($repo['owner'], $repo['repo'], "templates/{$templateName}/README.md", $repo['branch'])) {
            try {
                $readmeContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "templates/{$templateName}/README.md", 
                    $repo['branch']
                );
                $description = $this->extractDescriptionFromReadme($readmeContent);
                if ($description) {
                    $templateData['description'] = $description;
                }
            } catch (\Exception $e) {
                // README.md nicht lesbar
            }
        }
        
        // 3. template.php nach Kommentaren durchsuchen
        if (empty($templateData['description'])) {
            try {
                $templateContent = $this->github->getFileContent(
                    $repo['owner'], 
                    $repo['repo'], 
                    "templates/{$templateName}/template.php", 
                    $repo['branch']
                );
                $description = $this->extractDescriptionFromPhp($templateContent);
                if ($description) {
                    $templateData['description'] = $description;
                }
            } catch (\Exception $e) {
                // template.php nicht vorhanden
            }
        }
        
        // 4. Fallback: Hübscher Name aus Ordnername
        $templateData['title'] = $this->beautifyName($templateName);
        
        // 5. Assets-Information hinzufügen
        $templateData['has_assets'] = $this->github->fileExists($repo['owner'], $repo['repo'], "templates/{$templateName}/assets", $repo['branch']);
        
        // 6. README-Link hinzufügen falls README.md vorhanden
        if ($this->github->fileExists($repo['owner'], $repo['repo'], "templates/{$templateName}/README.md", $repo['branch'])) {
            $templateData['readme_url'] = "https://github.com/{$repo['owner']}/{$repo['repo']}/blob/{$repo['branch']}/templates/{$templateName}/README.md";
        }
        
        return $templateData;
    }
    
    /**
     * Beschreibung aus README.md extrahieren
     */
    private function extractDescriptionFromReadme(string $content): string
    {
        $lines = explode("\n", $content);
        
        // Erste nicht-leere Zeile nach dem Titel suchen
        $foundTitle = false;
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Titel überspringen (beginnt mit #)
            if (strpos($line, '#') === 0) {
                $foundTitle = true;
                continue;
            }
            
            // Erste inhaltliche Zeile nach dem Titel
            if ($foundTitle && !empty($line) && strpos($line, '#') !== 0) {
                return substr($line, 0, 200); // Maximal 200 Zeichen
            }
        }
        
        return '';
    }
    
    /**
     * Beschreibung aus PHP-Kommentaren extrahieren
     */
    private function extractDescriptionFromPhp(string $content): string
    {
        // Nach Dokumentationskommentaren suchen
        if (preg_match('/\/\*\*(.*?)\*\//s', $content, $matches)) {
            $comment = $matches[1];
            $lines = explode("\n", $comment);
            
            foreach ($lines as $line) {
                $line = trim($line, " \t\n\r\0\x0B*");
                if (!empty($line) && strpos($line, '@') !== 0) {
                    return substr($line, 0, 200);
                }
            }
        }
        
        // Nach einfachen Kommentaren am Anfang suchen
        if (preg_match('/^<\?php\s*\/\/\s*(.+)/m', $content, $matches)) {
            return substr(trim($matches[1]), 0, 200);
        }
        
        return '';
    }
    
    /**
     * Module mit Status-Informationen abrufen
     */
    public function getModulesWithStatus(string $repoKey): array
    {
        $modules = $this->getModules($repoKey);
        
        foreach ($modules as &$module) {
            $status = $this->getModuleInstallStatus($module['key'] ?? '', $module['title'] ?? $module['name']);
            $module['status'] = $status['status']; // 'new', 'installed', 'updatable'
            $module['existing_data'] = $status['existing_data'];
        }
        
        return $modules;
    }
    
    /**
     * Templates mit Status-Informationen abrufen
     */
    public function getTemplatesWithStatus(string $repoKey): array
    {
        $templates = $this->getTemplates($repoKey);
        
        foreach ($templates as &$template) {
            $status = $this->getTemplateInstallStatus($template['key'] ?? '', $template['title'] ?? $template['name']);
            $template['status'] = $status['status'];
            $template['existing_data'] = $status['existing_data'];
        }
        
        return $templates;
    }
    
    /**
     * Prüft den Installationsstatus eines Moduls
     */
    private function getModuleInstallStatus(string $moduleKey, string $moduleName): array
    {
        $status = [
            'status' => 'new',
            'existing_data' => null
        ];

        // 1. Priorität: Prüfung über `key` Feld (wenn vorhanden)
        if ($moduleKey && $this->hasKeyField('module')) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . \rex::getTable('module') . ' WHERE `key` = ?', [$moduleKey]);
            
            if ($checkSql->getRows() > 0) {
                $status['status'] = 'installed';
                $status['existing_data'] = [
                    'id' => $checkSql->getValue('id'),
                    'name' => $checkSql->getValue('name'),
                    'key' => $checkSql->getValue('key')
                ];
                return $status;
            }
        }

        // 2. Fallback: Prüfung über Name
        if ($moduleName) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . \rex::getTable('module') . ' WHERE name = ?', [$moduleName]);
            
            if ($checkSql->getRows() > 0) {
                $status['status'] = 'installed';
                $status['existing_data'] = [
                    'id' => $checkSql->getValue('id'),
                    'name' => $checkSql->getValue('name'),
                    'key' => $checkSql->getValue('key')
                ];
            }
        }

        return $status;
    }
    
    /**
     * Prüft den Installationsstatus eines Templates
     */
    private function getTemplateInstallStatus(string $templateKey, string $templateName): array
    {
        $status = [
            'status' => 'new',
            'existing_data' => null
        ];

        // 1. Priorität: Prüfung über `key` Feld (wenn vorhanden)
        if ($templateKey && $this->hasKeyField('template')) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . \rex::getTable('template') . ' WHERE `key` = ?', [$templateKey]);
            
            if ($checkSql->getRows() > 0) {
                $status['status'] = 'installed';
                $status['existing_data'] = [
                    'id' => $checkSql->getValue('id'),
                    'name' => $checkSql->getValue('name'),
                    'key' => $checkSql->getValue('key')
                ];
                return $status;
            }
        }

        // 2. Fallback: Prüfung über Name
        if ($templateName) {
            $checkSql = \rex_sql::factory();
            $checkSql->setQuery('SELECT * FROM ' . \rex::getTable('template') . ' WHERE name = ?', [$templateName]);
            
            if ($checkSql->getRows() > 0) {
                $status['status'] = 'installed';
                $status['existing_data'] = [
                    'id' => $checkSql->getValue('id'),
                    'name' => $checkSql->getValue('name'),
                    'key' => $checkSql->getValue('key')
                ];
            }
        }

        return $status;
    }
    
    /**
     * Prüft ob eine Tabelle ein `key` Feld hat
     */
    private function hasKeyField(string $table = 'module'): bool
    {
        $sql = \rex_sql::factory();
        $sql->setQuery('SHOW COLUMNS FROM ' . \rex::getTable($table) . ' LIKE "key"');
        return $sql->getRows() > 0;
    }

    /**
     * Klassen aus Repository laden mit Installationsstatus
     */
    public function getClassesWithStatus(string $repoKey): array
    {
        $classes = $this->getClasses($repoKey);
        $gitClassesPath = \rex_path::addon('project') . 'lib/gitClasses/';
        
        foreach ($classes as $className => &$classData) {
            $filename = $classData['filename'] ?? $className . '.php';
            
            // Jede Klasse bekommt ihren eigenen Ordner im gitClasses Verzeichnis
            $targetFile = $gitClassesPath . $className . '/' . $filename;
            
            $classData['status'] = [
                'installed' => file_exists($targetFile),
                'target_path' => $targetFile
            ];
        }
        
        return $classes;
    }
    
    /**
     * Klassen aus Repository laden
     */
    public function getClasses(string $repoKey): array
    {
        $cacheKey = "classes_{$repoKey}";
        $cached = $this->getCacheData($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $repoData = $this->getRepositoryData($repoKey);
        if (!$repoData) {
            return [];
        }
        
        try {
            $classes = [];
            
            // Classes-Verzeichnis prüfen
            try {
                $contents = $this->github->getRepositoryContents(
                    $repoData['owner'],
                    $repoData['repo'],
                    'classes',
                    $repoData['branch'] ?? 'main'
                );
                
                foreach ($contents as $item) {
                    if ($item['type'] === 'dir') {
                        // Verzeichnis mit Klasse
                        $className = $item['name'];
                        $classData = $this->parseClassDirectory($repoData, $className);
                        if ($classData) {
                            $classes[$className] = $classData;
                        }
                    } elseif ($item['type'] === 'file' && pathinfo($item['name'], PATHINFO_EXTENSION) === 'php') {
                        // Direkte PHP-Datei
                        $className = pathinfo($item['name'], PATHINFO_FILENAME);
                        $classData = $this->parseClassFile($repoData, $item['name']);
                        if ($classData) {
                            $classes[$className] = $classData;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Classes-Verzeichnis existiert nicht
            }
            
            $this->setCacheData($cacheKey, $classes);
            return $classes;
            
        } catch (\Exception $e) {
            \rex_logger::logException($e);
            return [];
        }
    }
    
    /**
     * Klassen-Verzeichnis parsen (mit config.yml)
     */
    private function parseClassDirectory(array $repoData, string $className): ?array
    {
        try {
            // config.yml laden
            $configContent = $this->github->getFileContent(
                $repoData['owner'],
                $repoData['repo'],
                "classes/{$className}/config.yml",
                $repoData['branch'] ?? 'main'
            );
            
            $config = \rex_string::yamlDecode($configContent);
            if (!$config) {
                return null;
            }
            
            // PHP-Datei prüfen
            $phpFile = $config['filename'] ?? $className . '.php';
            $phpContent = $this->github->getFileContent(
                $repoData['owner'],
                $repoData['repo'],
                "classes/{$className}/{$phpFile}",
                $repoData['branch'] ?? 'main'
            );
            
            // README-URL generieren
            $readmeUrl = "https://github.com/{$repoData['owner']}/{$repoData['repo']}/blob/{$repoData['branch']}/classes/{$className}/README.md";
            
            return [
                'title' => $config['title'] ?? $className,
                'description' => $config['description'] ?? '',
                'version' => $config['version'] ?? '1.0.0',
                'author' => $config['author'] ?? '',
                'filename' => $phpFile,
                'target_directory' => $config['target_directory'] ?? 'lib',
                'namespace' => $config['namespace'] ?? '',
                'content' => $phpContent,
                'config' => $config,
                'path' => "classes/{$className}",
                'readme_url' => $readmeUrl
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Einzelne PHP-Datei als Klasse parsen
     */
    private function parseClassFile(array $repoData, string $filename): ?array
    {
        try {
            $content = $this->github->getFileContent(
                $repoData['owner'],
                $repoData['repo'],
                "classes/{$filename}",
                $repoData['branch'] ?? 'main'
            );
            
            $className = pathinfo($filename, PATHINFO_FILENAME);
            
            // Basis-Informationen aus PHP-Kommentaren extrahieren
            $title = $this->extractClassTitle($content) ?: $className;
            $description = $this->extractClassDescription($content);
            $version = $this->extractClassVersion($content) ?: '1.0.0';
            $author = $this->extractClassAuthor($content);
            
            // README-URL generieren
            $readmeUrl = "https://github.com/{$repoData['owner']}/{$repoData['repo']}/blob/{$repoData['branch']}/classes/README.md";
            
            return [
                'title' => $title,
                'description' => $description,
                'version' => $version,
                'author' => $author,
                'filename' => $filename,
                'target_directory' => 'lib',
                'namespace' => '',
                'content' => $content,
                'path' => "classes/{$filename}",
                'readme_url' => $readmeUrl
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Klassen-Titel aus PHP-Kommentaren extrahieren  
     */
    private function extractClassTitle(string $content): string
    {
        if (preg_match('/@title\s+(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $this->beautifyName($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Klassen-Beschreibung aus PHP-Kommentaren extrahieren
     */  
    private function extractClassDescription(string $content): string
    {
        if (preg_match('/@description\s+(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        // Ersten Kommentarblock nach <?php suchen
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
     * Klassen-Version aus PHP-Kommentaren extrahieren
     */
    private function extractClassVersion(string $content): string
    {
        if (preg_match('/@version\s+(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Klassen-Autor aus PHP-Kommentaren extrahieren
     */
    private function extractClassAuthor(string $content): string
    {
        if (preg_match('/@author\s+(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }

    /**
     * Repository-Daten aus der Konfiguration laden
     */
    private function getRepositoryData(string $repoKey): ?array
    {
        $repositories = $this->getRepositories();
        return $repositories[$repoKey] ?? null;
    }
    
    /**
     * Cache-Daten laden
     */
    private function getCacheData(string $cacheKey): ?array
    {
        $cacheFile = $this->cacheDir . $cacheKey . '.json';
        
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheLifetime) {
            $content = \rex_file::get($cacheFile);
            if ($content) {
                return json_decode($content, true) ?: [];
            }
        }
        
        return null;
    }
    
    /**
     * Cache-Daten speichern
     */
    private function setCacheData(string $cacheKey, array $data): void
    {
        $cacheFile = $this->cacheDir . $cacheKey . '.json';
        \rex_file::put($cacheFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Namen verschönern (text-simple -> Text Simple)
     */
    private function beautifyName(string $name): string
    {
        // Bindestriche und Unterstriche durch Leerzeichen ersetzen
        $name = str_replace(['-', '_'], ' ', $name);
        
        // Jeden Wortanfang groß schreiben
        return ucwords($name);
    }
}
