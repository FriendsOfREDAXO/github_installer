<?php

use FriendsOfREDAXO\GitHubInstaller\GitHubApi;

$func = rex_request('func', 'string');
$type = rex_request('type', 'string', 'module');
$addon = rex_addon::get('github_installer');

// Upload-Einstellungen prüfen
$uploadOwner = $addon->getConfig('upload_owner', '');
$uploadRepo = $addon->getConfig('upload_repo', '');
$uploadBranch = $addon->getConfig('upload_branch', 'main');
$uploadAuthor = $addon->getConfig('upload_author', '');

if (empty($uploadOwner) || empty($uploadRepo)) {
    echo rex_view::warning($this->i18n('upload_settings_missing') . ' <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/settings']) . '">' . $this->i18n('settings') . '</a>');
    return;
}

// Upload durchführen
if ($func === 'upload' && rex_post('upload', 'bool')) {
    $itemId = (int) rex_post('item_id', 'int');
    $description = rex_post('description', 'string', '');
    $version = rex_post('version', 'string', '1.0.0');
    
    if (!$itemId) {
        echo rex_view::error($this->i18n('upload_missing_data'));
    } else {
        try {
            $github = new GitHubApi();
            
            // Repository testen
            if (!$github->testRepository($uploadOwner, $uploadRepo, $uploadBranch)) {
                throw new Exception("Repository {$uploadOwner}/{$uploadRepo} nicht erreichbar");
            }
            
            if ($type === 'module') {
                $result = uploadModule($itemId, $github, $uploadOwner, $uploadRepo, $uploadBranch, $uploadAuthor, $description, $version);
            } else {
                $result = uploadTemplate($itemId, $github, $uploadOwner, $uploadRepo, $uploadBranch, $uploadAuthor, $description, $version);
            }
            
            echo rex_view::success($this->i18n('upload_success', $result['name'], $uploadOwner . '/' . $uploadRepo));
            
        } catch (Exception $e) {
            echo rex_view::error($this->i18n('upload_error') . ': ' . $e->getMessage());
        }
    }
}

// Upload-Formular anzeigen
if ($func === 'upload' && !rex_post('upload', 'bool')) {
    $itemId = (int) rex_request('item_id', 'int');
    
    if ($type === 'module') {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id AS id, name AS name, `key` AS `key` FROM rex_module WHERE id = ?', [$itemId]);
        $itemLabel = 'Modul';
    } else {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id AS id, name AS name, `key` AS `key` FROM rex_template WHERE id = ?', [$itemId]);
        $itemLabel = 'Template';
    }
    
    if ($sql->getRows() === 0) {
        echo rex_view::error($this->i18n('item_not_found'));
        return;
    }
    
    // Werte direkt über rex_sql Methoden abrufen
    $itemName = $sql->getValue('name');
    $itemKey = $sql->getValue('key');
    
    $content = '<fieldset>';
    $content .= '<legend>' . $itemLabel . ' hochladen: ' . rex_escape($itemName ?: 'Unbekannt') . '</legend>';
    
    $content .= '<div class="alert alert-info">';
    $content .= '<strong>Ziel-Repository:</strong> ' . rex_escape($uploadOwner . '/' . $uploadRepo) . ' (Branch: ' . rex_escape($uploadBranch) . ')';
    $content .= '</div>';
    
    $formElements = [];
    
    $n = [];
    $n['label'] = '<label for="description">Beschreibung</label>';
    $n['field'] = '<textarea class="form-control" id="description" name="description" rows="3" placeholder="Kurze Beschreibung des ' . $itemLabel . 's...">' . rex_escape($itemName ?: 'Unbekannt') . '</textarea>';
    $formElements[] = $n;
    
    $n = [];
    $n['label'] = '<label for="version">Version</label>';
    $n['field'] = '<input class="form-control" type="text" id="version" name="version" value="1.0.0" />';
    $formElements[] = $n;
    
    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/form.php');
    
    $content .= '</fieldset>';
    
    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-primary" type="submit" name="upload" value="1">' . $this->i18n('upload') . '</button>';
    $n['field'] .= ' <a class="btn btn-secondary" href="' . rex_url::currentBackendPage(['type' => $type]) . '">' . $this->i18n('cancel') . '</a>';
    $formElements[] = $n;
    
    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');
    
    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $itemLabel . ' hochladen', false);
    $fragment->setVar('body', $content, false);
    $fragment->setVar('buttons', $buttons, false);
    $output = $fragment->parse('core/page/section.php');
    
    echo '<form action="' . rex_url::currentBackendPage(['func' => 'upload', 'type' => $type]) . '" method="post">';
    echo '<input type="hidden" name="item_id" value="' . $itemId . '" />';
    echo $output;
    echo '</form>';
    
    return;
}

// Tab-Navigation
$tabs = [
    'module' => $this->i18n('modules'),
    'template' => $this->i18n('templates')
];

$tabContent = '';
foreach ($tabs as $tabType => $tabLabel) {
    $active = $type === $tabType ? ' class="active"' : '';
    $tabContent .= '<li' . $active . '><a href="' . rex_url::currentBackendPage(['type' => $tabType]) . '">' . $tabLabel . '</a></li>';
}

echo '<ul class="nav nav-tabs">' . $tabContent . '</ul>';

// Item-Liste anzeigen
$list = rex_list::factory("SELECT id, name, `key`, createdate, updatedate FROM rex_{$type} ORDER BY name");
$list->addTableAttribute('class', 'table-striped');

$list->setColumnLabel('name', $this->i18n('name'));
$list->setColumnLabel('key', 'Key');
$list->setColumnLabel('createdate', $this->i18n('created'));
$list->setColumnLabel('updatedate', $this->i18n('updated'));

$list->setColumnFormat('createdate', 'custom', function ($params) {
    $value = $params['list']->getValue('createdate');
    if (!$value || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    return rex_formatter::intlDateTime($value, [\IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT]);
});
$list->setColumnFormat('updatedate', 'custom', function ($params) {
    $value = $params['list']->getValue('updatedate');
    if (!$value || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    return rex_formatter::intlDateTime($value, [\IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT]);
});

// Upload-Button hinzufügen
$list->addColumn('upload', $this->i18n('upload'), -1, ['<th>###VALUE###</th>', '<td>###VALUE###</td>']);
$list->setColumnFormat('upload', 'custom', function ($params) use ($type) {
    $itemId = $params['list']->getValue('id');
    $url = rex_url::currentBackendPage(['func' => 'upload', 'item_id' => $itemId, 'type' => $type]);
    return '<a class="btn btn-primary btn-xs" href="' . $url . '">' . 
           rex_i18n::msg('upload') . '</a>';
});

echo $list->get();

// Helper Functions
function uploadModule($moduleId, $github, $owner, $repo, $branch, $author, $description, $version) {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT * FROM rex_module WHERE id = ?', [$moduleId]);
    
    if ($sql->getRows() === 0) {
        throw new Exception('Modul nicht gefunden');
    }
    
    // Werte direkt über rex_sql Methoden abrufen
    $moduleName = $sql->getValue('key') ?: 'module_' . $moduleId;
    $moduleNameTitle = $sql->getValue('name') ?: 'Unnamed Module';
    $moduleInput = $sql->getValue('input') ?: "<?php\n// Modul Input\n";
    $moduleOutput = $sql->getValue('output') ?: "<?php\n// Modul Output\n";
    $basePath = "modules/{$moduleName}";
    
    // Config.yml
    $config = generateModuleConfig($moduleNameTitle, $moduleName, $author, $description, $version);
    $github->createOrUpdateFile($owner, $repo, "{$basePath}/config.yml", $config, "Update module {$moduleName}", $branch);
    
    // input.php
    $github->createOrUpdateFile($owner, $repo, "{$basePath}/input.php", $moduleInput, "Update module {$moduleName} input", $branch);
    
    // output.php  
    $github->createOrUpdateFile($owner, $repo, "{$basePath}/output.php", $moduleOutput, "Update module {$moduleName} output", $branch);
    
    // README.md
    $readme = generateModuleReadme($moduleNameTitle, $description);
    try {
        $github->getFileContent($owner, $repo, "{$basePath}/README.md", $branch);
    } catch (Exception $e) {
        $github->createOrUpdateFile($owner, $repo, "{$basePath}/README.md", $readme, "Add module {$moduleName} README", $branch);
    }
    
    return ['name' => $moduleName];
}

function uploadTemplate($templateId, $github, $owner, $repo, $branch, $author, $description, $version) {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT * FROM rex_template WHERE id = ?', [$templateId]);
    
    if ($sql->getRows() === 0) {
        throw new Exception('Template nicht gefunden');
    }
    
    // Werte direkt über rex_sql Methoden abrufen
    $templateName = $sql->getValue('key') ?: 'template_' . $templateId;
    $templateNameTitle = $sql->getValue('name') ?: 'Unnamed Template';
    $templateContent = $sql->getValue('content') ?: "<?php\n// Template Content\n";
    $basePath = "templates/{$templateName}";
    
    // Config.yml
    $config = generateTemplateConfig($templateNameTitle, $templateName, $author, $description, $version);
    $github->createOrUpdateFile($owner, $repo, "{$basePath}/config.yml", $config, "Update template {$templateName}", $branch);
    
    // template.php
    $github->createOrUpdateFile($owner, $repo, "{$basePath}/template.php", $templateContent, "Update template {$templateName}", $branch);
    
    // README.md
    $readme = generateTemplateReadme($templateNameTitle, $description);
    try {
        $github->getFileContent($owner, $repo, "{$basePath}/README.md", $branch);
    } catch (Exception $e) {
        $github->createOrUpdateFile($owner, $repo, "{$basePath}/README.md", $readme, "Add template {$templateName} README", $branch);
    }
    
    return ['name' => $templateName];
}

function generateModuleConfig($moduleName, $moduleKey, $author, $description, $version) {
    $yaml = [];
    $yaml[] = 'title: "' . $moduleName . '"';
    $yaml[] = 'description: "' . ($description ?: 'Keine Beschreibung') . '"';
    $yaml[] = 'author: "' . $author . '"';
    $yaml[] = 'version: "' . $version . '"';
    $yaml[] = 'key: "' . $moduleKey . '"';
    $yaml[] = 'redaxo_version: "5.13+"';
    
    return implode("\n", $yaml);
}

function generateTemplateConfig($templateName, $templateKey, $author, $description, $version) {
    $yaml = [];
    $yaml[] = 'title: "' . $templateName . '"';
    $yaml[] = 'description: "' . ($description ?: 'Keine Beschreibung') . '"';
    $yaml[] = 'author: "' . $author . '"';
    $yaml[] = 'version: "' . $version . '"';
    $yaml[] = 'key: "' . $templateKey . '"';
    $yaml[] = 'redaxo_version: "5.13+"';
    
    return implode("\n", $yaml);
}

function generateModuleReadme($moduleName, $description) {
    $readme = [];
    $readme[] = "# " . $moduleName . " - REDAXO Modul";
    $readme[] = "";
    $readme[] = $description ?: 'Keine Beschreibung verfügbar';
    $readme[] = "";
    $readme[] = "## Installation";
    $readme[] = "";
    $readme[] = "1. Repository zum GitHub Installer hinzufügen";
    $readme[] = "2. Modul installieren";
    $readme[] = "";
    
    return implode("\n", $readme);
}

function generateTemplateReadme($templateName, $description) {
    $readme = [];
    $readme[] = "# " . $templateName . " - REDAXO Template";
    $readme[] = "";
    $readme[] = $description ?: 'Keine Beschreibung verfügbar';
    $readme[] = "";
    $readme[] = "## Installation";
    $readme[] = "";
    $readme[] = "1. Repository zum GitHub Installer hinzufügen";
    $readme[] = "2. Template installieren";
    $readme[] = "";
    
    return implode("\n", $readme);
}
