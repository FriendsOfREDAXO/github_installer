<?php

use FriendsOfREDAXO\GitHubInstaller\GitHubApi;

$addon = rex_addon::get('github_installer');

$github = new GitHubApi();

// Cache löschen Action
$func = rex_request('func', 'string');

if ($func === 'clear') {
    try {
        $github->clearAllCache();
        echo rex_view::success($addon->i18n('github_installer_cache_cleared_success'));
    } catch (Exception $e) {
        echo rex_view::error($addon->i18n('github_installer_cache_clear_error') . ': ' . $e->getMessage());
    }
}

// Cache-Statistiken abrufen
$stats = $github->getCacheStats();

// Cache-Informationen anzeigen
$content = '<div class="row">
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <h3>' . $stats['file_count'] . '</h3>
                <p>' . $addon->i18n('github_installer_cache_files') . '</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <h3>' . $stats['formatted_size'] . '</h3>
                <p>' . $addon->i18n('github_installer_cache_size') . '</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <h3>' . rex_config::get('github_installer', 'cache_lifetime', 3600) . 's</h3>
                <p>' . $addon->i18n('github_installer_cache_lifetime') . '</p>
            </div>
        </div>
    </div>
</div>';

if ($stats['file_count'] > 0) {
    $content .= '<div class="text-center" style="margin-top: 20px;">
        <a href="' . rex_url::currentBackendPage(['func' => 'clear']) . '" 
           class="btn btn-warning"
           onclick="return confirm(\'' . $addon->i18n('github_installer_cache_clear_confirm') . '\')">
            <i class="rex-icon rex-icon-delete"></i> ' . $addon->i18n('github_installer_cache_clear') . '
        </a>
    </div>';
} else {
    $content .= '<div class="alert alert-info text-center">' . $addon->i18n('github_installer_cache_no_cache') . '</div>';
}

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('github_installer_cache_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
