<?php

/**
 * Verwaltung der Content Sprachen
 * @package redaxo5
 */

// -------------- Defaults
$func       = rex_request('func', 'string');

$error = '';
$success = '';

$logFile = rex_path::cache('system.log');
if ($func == 'delLog') {
    // close logger, to free remaining file-handles to syslog
    // so we can safely delete the file
    rex_logger::close();

    if (rex_log_file::delete($logFile)) {
        $success = rex_i18n::msg('syslog_deleted');
    } else {
        $error = rex_i18n::msg('syslog_delete_error');
    }

}

$content = '';

if ($success != '') {
    $content .= rex_view::success($success);
}

if ($error != '') {
    $content .= rex_view::error($error);
}

$content .= '
            <table id="rex-table-log" class="rex-table rex-table-responsive">
                <thead>
                    <tr>
                        <th>' . rex_i18n::msg('syslog_timestamp') . '</th>
                        <th>' . rex_i18n::msg('syslog_type') . '</th>
                        <th>' . rex_i18n::msg('syslog_message') . '</th>
                        <th>' . rex_i18n::msg('syslog_file') . '</th>
                        <th>' . rex_i18n::msg('syslog_line') . '</th>
                    </tr>
                </thead>
                <tbody>';

if ($file = new rex_log_file(rex_path::cache('system.log'))) {
    foreach (new LimitIterator($file, 0, 30) as $entry) {
        /* @var rex_log_entry $entry */
        $data = $entry->getData();

        $class = strtolower($data[0]);
        $class = ($class == 'notice' || $class == 'warning') ? $class : 'error';


        $content .= '
                    <tr class="rex-log-' . $class . '">
                        <td data-title="' . rex_i18n::msg('syslog_timestamp') . '">' . $entry->getTimestamp('%d.%m.%Y %H:%M:%S') . '</td>
                        <td data-title="' . rex_i18n::msg('syslog_type') . '">' . $data[0] . '</td>
                        <td data-title="' . rex_i18n::msg('syslog_message') . '">' . $data[1] . '</td>
                        <td data-title="' . rex_i18n::msg('syslog_file') . '"><span class="rex-truncate-left">' . (isset($data[2]) ? $data[2] : '') . '</span></td>
                        <td data-title="' . rex_i18n::msg('syslog_line') . '">' . (isset($data[3]) ? $data[3] : '') . '</td>
                    </tr>';
    }
}

$content .= '
                </tbody>
            </table>';

$content .= '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        <input type="hidden" name="func" value="delLog" />';


$formElements = [];

$n = [];
$n['field'] = '<button class="rex-button rex-button-primary" type="submit" name="del_btn" data-confirm="' . rex_i18n::msg('delete') . '?">' . rex_i18n::msg('syslog_delete') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/submit.php');


$content .= '
    </form>';


$fragment = new rex_fragment();
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
