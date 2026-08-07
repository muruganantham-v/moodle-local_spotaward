<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$items = $DB->get_records('spotaward_nomination_items', ['studentid' => 27]);
if (empty($items)) {
    echo "User 27 is NOT in spotaward_nomination_items!\n";
} else {
    foreach ($items as $item) {
        $nom = $DB->get_record('spotaward_nominations', ['id' => $item->nominationid]);
        echo "Item ID: {$item->id}, Nomination ID: {$item->nominationid}, Status: {$nom->status}\n";
    }
}
