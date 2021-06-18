<?php

header('Content-Type: application/json');

require_once '../../../Class/class.phpsqlgoose.php';

use phpgoose\Connection;
use phpgoose\Model;
use phpgoose\Schema;
use function Types\DateTime;
use function Types\ID;
use function Types\Password;

$conn = new Connection(Connection::LOCALHOST, "root", "root", "welcome to losantos", 1012);

$schema = new Schema(array (
    'id' => ID(),
    'password' => Password(),
    'created_at' => DateTime(),
    'views' => [
        'type' => TYPE_INT,
        'default' => 0
    ]
));


$model = new Model('Users', $schema);

$_POST = (object) $_POST;
$_GET = (object) $_GET;

if ($_POST) {
    switch ($_GET->type) {
        case 'register':

            $result = $model->insert_one(
                [
                    'id' => \Helpers\Generator::id(),
                    'password' => \Helpers\Generator::password(12),
                    'created_at' => \Helpers\Generator::current_time()
                ]
            );

            print json_encode(array (
                'data' => $result
            ));

            break;

        case 'login':
            $model->update_one([
                'id' => 25377094
            ], [
                'password' => 345345
            ]);
            break;

        default:
            print json_encode(array (
                'data' => "The data type is not supported"
            ));
    }
}