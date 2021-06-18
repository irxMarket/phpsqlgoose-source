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

$user->on("pre_save", function ($data = []) {
    [$key, $value] = $data;

    if ($key === 'secret') {
        return "secret::$value";
    }
});

if ($_POST['firstname'] && $_POST['lastname'] && $_POST['password']) {
    
    if ($user([
        'id' => pg\Generator::id(),
        'firstname' => $_POST['firstname'],
        'lastname' => $_POST['lastname'],
        'password' => $_POST['password'],
        'secret' => pg\Generator::secret('super puper solt')
    ])) {
        echo "Account created successfully";
    } else {
        echo "The account was not created";
    }
    
}

?> 

<form method='POST'>
	<input name="firstname" placeholder="First Name"></input>
	<input name="lastname" placeholder="Last Name"></input>
	<input name="password" placeholder="Password"></input>
	<input name="submit" value="Submit" type="submit"></input>
</form>