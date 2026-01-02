<?php

use FriendsOfREDAXO\GitHubInstaller\RepositoryManager;

$addon = rex_addon::get('github_installer');


$repoManager = new RepositoryManager();

// Aktionen verarbeiten
$func = rex_request('func', 'string');

if ($func === 'add') {
    $owner = rex_request('owner', 'string');
    $repo = rex_request('repo', 'string');
    $displayName = rex_request('display_name', 'string');
    $branch = rex_request('branch', 'string', 'main');
    
    if ($owner && $repo) {
        try {
            $repoManager->addRepository($owner, $repo, $displayName, $branch);
            echo rex_view::success($addon->i18n('github_installer_repo_added_success'));
        } catch (Exception $e) {
            echo rex_view::error($e->getMessage());
        }
    }
}

if ($func === 'edit') {
    $key = rex_request('key', 'string');
    $displayName = rex_request('display_name', 'string');
    $branch = rex_request('branch', 'string', 'main');
    
    if ($key) {
        try {
            $repoManager->updateRepository($key, $displayName, $branch);
            echo rex_view::success($addon->i18n('github_installer_repo_updated_success'));
        } catch (Exception $e) {
            echo rex_view::error($e->getMessage());
        }
    }
}

if ($func === 'delete') {
    $key = rex_request('key', 'string');
    
    if ($key) {
        if ($repoManager->removeRepository($key)) {
            echo rex_view::success($addon->i18n('github_installer_repo_removed_success'));
        } else {
            echo rex_view::error($addon->i18n('github_installer_error_occurred'));
        }
    }
}

// Repository hinzufügen Formular
$content = '';
$formElements = [];

// Owner
$n = [];
$n['label'] = '<label for="repo-owner">' . $addon->i18n('github_installer_repo_owner') . '</label>';
$n['field'] = '<input type="text" id="repo-owner" name="owner" class="form-control" required>';
$formElements[] = $n;

// Repository Name
$n = [];
$n['label'] = '<label for="repo-name">' . $addon->i18n('github_installer_repo_name') . '</label>';
$n['field'] = '<input type="text" id="repo-name" name="repo" class="form-control" required>';
$formElements[] = $n;

// Display Name
$n = [];
$n['label'] = '<label for="repo-display-name">' . $addon->i18n('github_installer_repo_display_name') . '</label>';
$n['field'] = '<input type="text" id="repo-display-name" name="display_name" class="form-control">';
$formElements[] = $n;

// Branch
$n = [];
$n['label'] = '<label for="repo-branch">' . $addon->i18n('github_installer_repo_branch') . '</label>';
$n['field'] = '<input type="text" id="repo-branch" name="branch" class="form-control" value="main">';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/container.php');

// Submit Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-primary" type="submit" name="func" value="add">' . $addon->i18n('github_installer_repo_add_button') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

// Formular zusammenbauen
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('github_installer_repo_add'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$addForm = $fragment->parse('core/page/section.php');

// Formular ausgeben
echo '<form action="' . rex_url::currentBackendPage() . '" method="post">' . $addForm . '</form>';

// Vorhandene Repositories anzeigen
$repositories = $repoManager->getRepositories();

if (!empty($repositories)) {
    $tableContent = '<div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>' . $addon->i18n('github_installer_repo_name') . '</th>
                    <th>' . $addon->i18n('github_installer_repo_display_name') . '</th>
                    <th>' . $addon->i18n('github_installer_repo_branch') . '</th>
                    <th>' . $addon->i18n('github_installer_repo_added') . '</th>
                    <th class="rex-table-action">' . $addon->i18n('github_installer_repo_actions') . '</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($repositories as $key => $repo) {
        $deleteUrl = rex_url::currentBackendPage(['func' => 'delete', 'key' => $key]);
        
        $tableContent .= '<tr>
            <td><strong>' . rex_escape($key) . '</strong></td>
            <td>' . rex_escape($repo['display_name']) . '</td>
            <td>' . rex_escape($repo['branch']) . '</td>
            <td>' . date('d.m.Y H:i', $repo['added']) . '</td>
            <td class="rex-table-action">
                <a href="' . $deleteUrl . '" 
                   class="btn btn-danger btn-xs"
                   onclick="return confirm(\'' . $addon->i18n('github_installer_repo_remove_confirm') . '\')">
                    <i class="rex-icon rex-icon-delete"></i> ' . $addon->i18n('github_installer_repo_remove') . '
                </a>
            </td>
        </tr>';
    }
    
    $tableContent .= '</tbody></table></div>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', $addon->i18n('github_installer_repositories') . ' (' . count($repositories) . ')');
    $fragment->setVar('body', $tableContent, false);
    echo $fragment->parse('core/page/section.php');
} else {
    echo rex_view::info($addon->i18n('github_installer_no_repositories'));
}
