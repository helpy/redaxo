<?php

/**
 * Version.
 *
 * @author jan@kristinus.de
 *
 * @package redaxo5
 */

$mypage = 'version';

rex_perm::register('version[live_version]', null, rex_perm::OPTIONS);

// ***** an EPs andocken
rex_extension::register('ART_INIT', function (rex_extension_point $ep) {
    $version = rex_request('rex_version', 'int');
    if ($version != rex_article_revision::WORK) {
        return;
    }

    rex_login::startSession();

    if (!rex_backend_login::hasSession()) {
        throw new rex_exception('No permission for the working version. You need to be logged into the REDAXO backend at the same time.');
    }

    $article = $ep->getParam('article');
    $article->setSliceRevision($version);
    if ($article instanceof rex_article_content) {
        $article->getContentAsQuery();
    }
    $article->setEval(true);
});

rex_extension::register('STRUCTURE_CONTENT_HEADER', function (rex_extension_point $ep) {
    $params = $ep->getParams();
    $return = '';

    $rex_version_article = rex::getProperty('login')->getSessionVar('rex_version_article');
    if (!is_array($rex_version_article)) {
        $rex_version_article = [];
    }

    $working_version_empty = true;
    $gw = rex_sql::factory();
    $gw->setQuery('select * from ' . rex::getTablePrefix() . 'article_slice where article_id=? and clang_id=? and revision=' . rex_article_revision::WORK . ' LIMIT 1', [$params['article_id'], $params['clang']]);
    if ($gw->getRows() > 0) {
        $working_version_empty = false;
    }

    // $revisions = [];
    // $revisions[0] = rex_i18n::msg('version_liveversion');
    // $revisions[1] = rex_i18n::msg('version_workingversion');

    $version_id = rex_request('rex_set_version', 'int', '-1');

    if ($version_id === rex_article_revision::LIVE) {
        $rex_version_article[$params['article_id']] = rex_article_revision::LIVE;
    } elseif ($version_id == rex_article_revision::WORK) {
        $rex_version_article[$params['article_id']] = rex_article_revision::WORK;
    } elseif (!isset($rex_version_article[$params['article_id']])) {
        $rex_version_article[$params['article_id']] = rex_article_revision::WORK;
    }

    $func = rex_request('rex_version_func', 'string');
    switch ($func) {
        case 'copy_work_to_live':
            if ($working_version_empty) {
                $return .= rex_view::error(rex_i18n::msg('version_warning_working_version_to_live'));
            } elseif (rex::getUser()->hasPerm('version[live_version]')) {
                rex_article_revision::copyContent($params['article_id'], $params['clang'], rex_article_revision::WORK, rex_article_revision::LIVE);
                $return .= rex_view::success(rex_i18n::msg('version_info_working_version_to_live'));
            }
        break;
        case 'copy_live_to_work':
            rex_article_revision::copyContent($params['article_id'], $params['clang'], rex_article_revision::LIVE, rex_article_revision::WORK);
            $return .= rex_view::success(rex_i18n::msg('version_info_live_version_to_working'));
        break;
    }

    if (!rex::getUser()->hasPerm('version[live_version]')) {
        $rex_version_article[$params['article_id']] = rex_article_revision::WORK;
        // unset($revisions[0]);
    }

    rex::getProperty('login')->setSessionVar('rex_version_article', $rex_version_article);

    $context = new rex_context([
        'page' => $params['page'],
        'article_id' => $params['article_id'],
        'clang' => $params['clang'],
        'ctype' => $params['ctype'],
    ]);

/*
    $items = [];
    $brand = '';
    foreach ($revisions as $version => $revision) {
        $item = [];
        $item['title'] = $revision;
        $item['href'] = $context->getUrl(['rex_set_version' => $version]);
        if ($rex_version_article[$params['article_id']] == $version) {
            $item['active'] = true;
            $brand = $revision;
        }
        $items[] = $item;
    }

    $toolbar = '';
*/

/* 
    $fragment = new rex_fragment();
    $fragment->setVar('button_prefix', rex_i18n::msg('version'));
    $fragment->setVar('items', $items, false);
    $fragment->setVar('toolbar', true);

    if (!rex::getUser()->hasPerm('version[live_version]')) {
        $fragment->setVar('disabled', true);
    }

    $toolbar .= '<li class="dropdown">' . $fragment->parse('core/dropdowns/dropdown.php') . '</li>';
*/
    $btn_icons = array(
        'draft_state'       => 'square-o',
        'live_state'        => 'check-square-o',
        'copy_work_to_live' => 'share-square-o',
        'copy_live_to_work' => 'pencil-square-o',
        'preview-work'      => 'eye-slash',
        'preview-live'      => 'eye',
    );

    $btn_toolbar = array(
        'vertical'    => false,
        'size'        => '',
        'btn_groups'  => [],
    );
    if (rex::getUser()->hasPerm('version[live_version]')) {
        if ($rex_version_article[$params['article_id']] > rex_article_revision::LIVE) {
            $btn_group = [];

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['draft_state'];
            $btn_item['text'] = rex_i18n::msg('version_workingversion');
            $btn_item['url']   = $context->getUrl(['rex_set_version' => rex_article_revision::WORK]);
            $btn_item['attributes']['class'][] = 'btn-warning';
            $btn_item['attributes']['title'] = '';
            $btn_group[] = $btn_item;

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['live_state'];
            $btn_item['text'] = '';
            $btn_item['url']   = $context->getUrl(['rex_set_version' => rex_article_revision::LIVE]);
            $btn_item['attributes']['class'][] = 'btn-default';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_switch_to_live');
            $btn_group[] = $btn_item;

            $btn_toolbar['btn_groups'][] = $btn_group;
        } else {
            $btn_group = [];

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['draft_state'];
            $btn_item['text'] = '';
            $btn_item['url']   = $context->getUrl(['rex_set_version' => rex_article_revision::WORK]);
            $btn_item['attributes']['class'][] = 'btn-default';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_switch_to_work');
            $btn_group[] = $btn_item;

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['live_state'];
            $btn_item['text'] = rex_i18n::msg('version_liveversion');
            $btn_item['url']   = $context->getUrl(['rex_set_version' => rex_article_revision::LIVE]);
            $btn_item['attributes']['class'][] = 'btn-success';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_switch_to_work');
            $btn_group[] = $btn_item;

            $btn_toolbar['btn_groups'][] = $btn_group;
        }
    }

    if (!rex::getUser()->hasPerm('version[live_version]')) {
        if ($rex_version_article[$params['article_id']] > rex_article_revision::LIVE) {
            $btn_group = [];

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['draft_state'];
            $btn_item['text'] = rex_i18n::msg('version_workingversion');
            $btn_item['url']   = '#';
            $btn_item['attributes']['class'][] = 'btn-warning';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_work_only');
            $btn_group[] = $btn_item;
            $btn_toolbar['btn_groups'][] = $btn_group;

            $btn_group = [];

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['copy_live_to_work'];
            $btn_item['text'] = rex_i18n::msg('version_copy_from_liveversion');
            $btn_item['url']   = $context->getUrl(['rex_version_func' => 'copy_live_to_work']);
            $btn_item['attributes']['class'][] = 'btn-default';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_copy_live_to_work');
            $btn_group[] = $btn_item;

            $btn_toolbar['btn_groups'][] = $btn_group;

            $btn_group = [];

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['preview-work'];
            $btn_item['text'] = '';
            $btn_item['url']   = rex_getUrl($params['article_id'], $params['clang'], ['rex_version' => rex_article_revision::WORK]);
            $btn_item['attributes']['class'][] = 'btn-default';
            $btn_item['attributes']['rel'] = 'noopener noreferrer';
            $btn_item['attributes']['target'] = '_blank';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_preview');
            $btn_group[] = $btn_item;

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['preview-live'];
            $btn_item['text'] = '';
            $btn_item['url']   = rex_getUrl($params['article_id'], $params['clang']);
            $btn_item['attributes']['class'][] = 'btn-default';
            $btn_item['attributes']['rel'] = 'noopener noreferrer';
            $btn_item['attributes']['target'] = '_blank';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_preview');
            $btn_group[] = $btn_item;

            $btn_toolbar['btn_groups'][] = $btn_group;

        }
    } else {
        if ($rex_version_article[$params['article_id']] > rex_article_revision::LIVE) {
            $btn_group = [];
            if (!$working_version_empty) {
                $btn_item = [];
                $btn_item['symbol'] = $btn_icons['copy_work_to_live'];
                $btn_item['text'] = rex_i18n::msg('version_working_to_live');
                $btn_item['url']   = $context->getUrl(['rex_version_func' => 'copy_work_to_live']);
                $btn_item['attributes']['class'][] = 'btn-default';
                $btn_item['attributes']['title'] = rex_i18n::msg('version_title_copy_work_to_live');
                $btn_group[] = $btn_item;
            }
            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['copy_live_to_work'];
            $btn_item['text'] = rex_i18n::msg('version_copy_from_liveversion');
            $btn_item['url']   = $context->getUrl(['rex_version_func' => 'copy_live_to_work']);
            $btn_item['attributes']['class'][] = 'btn-default';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_copy_live_to_work');
            $btn_group[] = $btn_item;

            $btn_toolbar['btn_groups'][] = $btn_group;

            $btn_group = [];

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['preview-work'];
            $btn_item['text'] = '';
            $btn_item['url']   = rex_getUrl($params['article_id'], $params['clang'], ['rex_version' => rex_article_revision::WORK]);
            $btn_item['attributes']['class'][] = 'btn-default';
            $btn_item['attributes']['rel'] = 'noopener noreferrer';
            $btn_item['attributes']['target'] = '_blank';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_preview');
            $btn_group[] = $btn_item;

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['preview-live'];
            $btn_item['text'] = '';
            $btn_item['url']   = rex_getUrl($params['article_id'], $params['clang']);
            $btn_item['attributes']['class'][] = 'btn-default';
            $btn_item['attributes']['rel'] = 'noopener noreferrer';
            $btn_item['attributes']['target'] = '_blank';
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_preview');
            $btn_group[] = $btn_item;

            $btn_toolbar['btn_groups'][] = $btn_group;
        } else {
            $btn_group = [];

            $btn_item = [];
            $btn_item['symbol'] = $btn_icons['copy_live_to_work'];
            $btn_item['text'] = rex_i18n::msg('version_copy_live_to_workingversion');
            $btn_item['url']   = $context->getUrl(['rex_version_func' => 'copy_live_to_work']);
            $btn_item['attributes']['class'][] = 'btn-default';
            $btn_item['attributes']['data-confirm'] = rex_i18n::msg('version_confirm_copy_live_to_workingversion');
            $btn_item['attributes']['title'] = rex_i18n::msg('version_title_copy_live_to_work');
            $btn_group[] = $btn_item;
            $btn_toolbar['btn_groups'][] = $btn_group;
        }
    }

    $fragment = new rex_fragment();
    $fragment->setVar('vertical', $btn_toolbar['vertical']);
    $fragment->setVar('size', $btn_toolbar['size']);

    $btn_group_parsed = '';
    //$visible_class = 'visible-lg-inline visible-md-inline visible-sm-inline';
    foreach($btn_toolbar['btn_groups'] as &$buttons) {
        foreach($buttons as &$button) {
            $button['label'] = 
                '<i class="fa fa-' . $button['symbol'] . '"></i>' . 
                '<span class="' . $visible_class . '"> ' . $button['text'] . '</span>';
        }
        $fragment->setVar('buttons', $buttons, false);
        $btn_group_parsed .= $fragment->parse('core/buttons/button_group.php');
        $visible_class = 'visible-lg-inline visible-md-inline visible-sm-inline';
        // $visible_class = 'visible-lg-inline';
    }
    $return .= '<div class="nav rex-page-nav"><div class="btn-toolbar" role="toolbar" aria-label="version toolbar">'.$btn_group_parsed.'</div></div>';

    $params['slice_revision'] = $rex_version_article[$params['article_id']];

    return $return;
});
