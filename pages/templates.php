<?php

use FriendsOfREDAXO\GitHubInstaller\RepositoryManager;
use FriendsOfREDAXO\GitHubInstaller\InstallManager;

$addon = rex_addon::get('github_installer');


$repoManager = new RepositoryManager();
$installManager = new InstallManager();

// Aktionen verarbeiten
$func = rex_request('func', 'string');
$repo = rex_request('repo', 'string');

if ($func === 'install' && $repo) {
    $template = rex_request('template', 'string');
    $key = rex_request('key', 'string');
    
    if ($template) {
        try {
            $installManager->installTemplate($repo, $template, $key);
            echo rex_view::success($addon->i18n('templates_installed_success'));
        } catch (Exception $e) {
            echo rex_view::error($addon->i18n('templates_install_error') . ': ' . $e->getMessage());
        }
    }
}

// Repository-Auswahl
$repositories = $repoManager->getRepositories();

if (empty($repositories)) {
    echo rex_view::warning($addon->i18n('templates_no_repos'));
    return;
}

// Repository-Auswahlformular
$content = '';
$formElements = [];

$n = [];
$n['label'] = '<label for="templates-repo-select">' . $addon->i18n('templates_select_repo') . '</label>';

$select = '<select name="repo" id="templates-repo-select" class="form-control">';
$select .= '<option value="">' . $addon->i18n('templates_choose_repo') . '</option>';

foreach ($repositories as $repoKey => $repoData) {
    $selected = ($repo === $repoKey) ? ' selected="selected"' : '';
    $select .= '<option value="' . rex_escape($repoKey) . '"' . $selected . '>' . rex_escape($repoData['display_name']) . ' (' . rex_escape($repoKey) . ')</option>';
}
$select .= '</select>';

$n['field'] = $select;
$formElements[] = $n;

// Submit Button
$n = [];
$n['field'] = '<button class="btn btn-primary" type="submit">' . $addon->i18n('templates_loading') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/container.php');

$fragment = new rex_fragment();
$fragment->setVar('elements', [$n], false);
$buttons = $fragment->parse('core/form/submit.php');

// Repository-Formular
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('templates_select_repo'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$repoForm = $fragment->parse('core/page/section.php');

echo '<form action="' . rex_url::currentBackendPage() . '" method="post">' . $repoForm . '</form>';

// Templates anzeigen wenn Repository ausgewählt
if ($repo && isset($repositories[$repo])) {
    try {
        $templates = $repoManager->getTemplates($repo);
        
        if (!empty($templates)) {
            $tableContent = '<div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>' . $addon->i18n('template_name') . '</th>
                            <th>' . $addon->i18n('template_title') . '</th>
                            <th>' . $addon->i18n('template_description') . '</th>
                            <th>' . $addon->i18n('template_version') . '</th>
                            <th>' . $addon->i18n('template_author') . '</th>
                            <th>' . $addon->i18n('template_assets') . '</th>
                            <th class="rex-table-action">' . $addon->i18n('template_actions') . '</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($templates as $template) {
                $installUrl = rex_url::currentBackendPage([
                    'repo' => $repo,
                    'func' => 'install',
                    'template' => $template['name'],
                    'key' => $template['key']
                ]);
                
                // Assets und README-Info
                $assetsInfo = '';
                if (!empty($template['has_assets'])) {
                    $assetsInfo .= '<span class="label label-success"><i class="rex-icon rex-icon-package"></i> Assets</span> ';
                }
                if (!empty($template['readme_url'])) {
                    $assetsInfo .= '<a href="' . rex_escape($template['readme_url']) . '" target="_blank" class="btn btn-xs btn-default" title="README auf GitHub öffnen"><i class="rex-icon rex-icon-open-in-new"></i> README</a>';
                }
                
                $tableContent .= '<tr>
                    <td><strong>' . rex_escape($template['name']) . '</strong></td>
                    <td>' . rex_escape($template['title']) . '</td>
                    <td>' . rex_escape($template['description'] ?: $addon->i18n('no_description')) . '</td>
                    <td>' . rex_escape($template['version']) . '</td>
                    <td>' . rex_escape($template['author'] ?: $addon->i18n('unknown')) . '</td>
                    <td>' . $assetsInfo . '</td>
                    <td class="rex-table-action">
                        <a href="' . $installUrl . '" 
                           class="btn btn-primary btn-xs"
                           onclick="return confirm(\'' . $addon->i18n('templates_install_confirm', rex_escape($template['name'])) . '\')">
                            <i class="rex-icon rex-icon-download"></i> ' . $addon->i18n('templates_install') . '
                        </a>
                    </td>
                </tr>';
                
                // Details-Bereich falls Key vorhanden
                if ($template['key']) {
                    $tableContent .= '<tr class="collapse" id="template-details-' . rex_escape($template['name']) . '">
                        <td colspan="6">
                            <div class="panel panel-default">
                                <div class="panel-body">
                                    <h5>' . $addon->i18n('template_info') . '</h5>
                                    <dl class="dl-horizontal">
                                        <dt>' . $addon->i18n('template_key') . ':</dt>
                                        <dd>' . rex_escape($template['key']) . '</dd>
                                        <dt>' . $addon->i18n('template_assets_target') . ':</dt>
                                        <dd>/assets/templates/' . rex_escape($template['key']) . '/</dd>
                                    </dl>
                                </div>
                            </div>
                        </td>
                    </tr>';
                }
            }
            
            $tableContent .= '</tbody></table></div>';
            
            $fragment = new rex_fragment();
            $fragment->setVar('title', $addon->i18n('templates_title') . ' (' . count($templates) . ')');
            $fragment->setVar('body', $tableContent, false);
            echo $fragment->parse('core/page/section.php');
        } else {
            echo rex_view::info($addon->i18n('templates_no_templates'));
        }
        
    } catch (Exception $e) {
        echo rex_view::error($addon->i18n('error_occurred') . ': ' . $e->getMessage());
    }
}
