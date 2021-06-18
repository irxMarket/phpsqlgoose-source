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
    ],
    "secret" => [
        "type" => TYPE_STRING
    ]
));

$user = new pg\Model("users", $users_schema);
    
if ($_POST['firstname'] && $_POST['password']) {
    
    $account = $user->delete_one([
        'firstname' => $_POST['firstname'],
        'password' => $_POST['password']
    ]);
    
    if ($account) {
        echo "Account deleted successfully";
    } else {
        echo "The account was not deleted";
    }
    
}

?>

<form method='POST'>
	<input name="firstname" placeholder="First Name"></input>
	<input name="password" placeholder="Password"></input>
	<input name="submit" value="Submit" type="submit"></input>
</form>