<?php

/**
 * GitHub Installer - Overview
 *
 * @author Friends Of REDAXO
 */

$addon = rex_addon::get('github_installer');

// Overview Dashboard
$content = '
<div class="row">
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-github"></i> ' . $addon->i18n('overview_repositories') . '</h3>
            </div>
            <div class="panel-body">
                <p>' . $addon->i18n('overview_repositories_desc') . '</p>
                <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/repositories']) . '" class="btn btn-primary">' . $addon->i18n('overview_manage_repositories') . '</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-download"></i> ' . $addon->i18n('overview_install') . '</h3>
            </div>
            <div class="panel-body">
                <p>' . $addon->i18n('overview_install_desc') . '</p>
                <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/modules']) . '" class="btn btn-success btn-sm">' . $addon->i18n('modules') . '</a>
                <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/templates']) . '" class="btn btn-success btn-sm">' . $addon->i18n('templates') . '</a>
                <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/classes']) . '" class="btn btn-success btn-sm">' . $addon->i18n('classes_title') . '</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-upload"></i> ' . $addon->i18n('overview_upload') . '</h3>
            </div>
            <div class="panel-body">
                <p>' . $addon->i18n('overview_upload_desc') . '</p>
                <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/upload']) . '" class="btn btn-warning btn-sm">' . $addon->i18n('overview_modules_templates') . '</a>
                <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/upload', 'type' => 'class']) . '" class="btn btn-warning btn-sm">' . $addon->i18n('overview_classes') . '</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-info-circle"></i> ' . $addon->i18n('overview_class_management') . '</h3>
            </div>
            <div class="panel-body">
                <h4>' . $addon->i18n('overview_installation') . '</h4>
                <p>' . $addon->i18n('overview_class_install_desc') . '</p>
                
                <h4>' . $addon->i18n('overview_structure') . '</h4>
                <ul>
                    <li>' . $addon->i18n('overview_class_structure_1') . '</li>
                    <li>' . $addon->i18n('overview_class_structure_2') . '</li>
                    <li>' . $addon->i18n('overview_class_structure_3') . '</li>
                </ul>
                
                <h4>' . $addon->i18n('overview_upload') . '</h4>
                <p>' . $addon->i18n('overview_class_upload_desc') . '</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-cogs"></i> ' . $addon->i18n('overview_management') . '</h3>
            </div>
            <div class="panel-body">
                <h4>' . $addon->i18n('cache') . '</h4>
                <p>' . $addon->i18n('overview_cache_desc') . '</p>
                <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/cache']) . '" class="btn btn-default btn-sm">' . $addon->i18n('overview_manage_cache') . '</a>
                
                <h4>' . $addon->i18n('settings') . '</h4>
                <p>' . $addon->i18n('overview_settings_desc') . '</p>
                <a href="' . rex_url::currentBackendPage(['page' => 'github_installer/settings']) . '" class="btn btn-default btn-sm">' . $addon->i18n('settings') . '</a>
            </div>
        </div>
    </div>
</div>
';

echo $content;