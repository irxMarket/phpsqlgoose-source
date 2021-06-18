<?php

require_once '../class.phpsqlgoose.php';

use phpgoose as pg;

new pg\Connection(PG_LOCALHOST, "root", "root", "Example Database", 1012);

$users_schema = new pg\Schema(array(
    "id" => pg\_id(),
    "firstname" => [
        "type" => TYPE_STRING
    ],
    "lastname" => [
        "type" => TYPE_STRING
    ],
    "password" => [
        "type" => TYPE_PASSWORD
    ]
));

$user = new pg\Model("users", $users_schema);

echo "Users: " . $user->count([
    'firstname' => [
        '$or' => [1,12]
    ]
]);
