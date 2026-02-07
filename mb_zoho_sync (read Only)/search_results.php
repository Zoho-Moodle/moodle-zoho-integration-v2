<?php
require('../../config.php');
require_login();

$query = optional_param('query', '', PARAM_TEXT);

if ($query) {
    global $DB;
    $like = '%' . $DB->sql_like_escape($query) . '%';
    $params = [$like, $like, $like, $like];
    $sql = "SELECT id, username, firstname, lastname
            FROM {user}
            WHERE deleted = 0 AND (
                username LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?
            ) LIMIT 10";

    $users = $DB->get_records_sql($sql, $params);

    if ($users) {
        echo '<select onchange="selectUser(this.value)">';
        foreach ($users as $user) {
            echo '<option value="' . $user->id . '">' . fullname($user) . ' (' . $user->username . ')</option>';
        }
        echo '</select>';
    } else {
        echo '<p>No results found</p>';
    }
}
?>
