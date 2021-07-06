<?php


use phpgoose\Connection;
use phpgoose\Model;
use phpgoose\Schema;
use phpgoose\Wrapper\File;
use phpgoose\Wrapper\Point;
use phpgoose\Wrapper\Time;
use phpsqlgoose\Builder\Fluid;
use function phpgoose\Types\Blob;
use function phpgoose\Types\DateTime;
use function phpgoose\Types\Email;
use function phpgoose\Types\ID;
use function phpgoose\Types\LineString;
use function phpgoose\Types\Nickname;
use function phpgoose\Types\Number;
use function phpgoose\Types\PhoneNumber;
use function phpgoose\Types\Point;

require 'Class/class.phpsqlgoose.php';

// MySQL login data
$host = '127.0.0.1:1012';
$username = 'root';
$password = 'root';
$db_name = "CRM";

new Connection($host, $username, $password, $db_name);

$model = new Model('Clients', new Schema([
    'id' => ID(),
    'created_at' => DateTime(),
    'email' => Email(),
    'login' => Nickname(),
    'geo_home' => Point(),
    'avatar' => Blob(),
    'phone' => PhoneNumber(),
    'orders' => Number(),
    'cf' =>
        (new Fluid())
            ->type(TYPE_STRING)
            ->size(20)
            ->max(10)
            ->to_array()

]));

//for ($i = 1; $i <= 50; $i++) {
//    $model->insert_one([
//        'created_at' => new Time(),
//        'email' => \phpgoose\Helpers\Generator::secret('Hello world') . '@gmail.com',
//        'login' => \phpgoose\Helpers\Generator::secret('Hello world'),
//        'geo_home' => new Point(rand(1000, 9000), rand(1000, 9000)),
//        'avatar' => new File('index.php'),
//        'phone' => rand(10000000000, 70000000000),
//        'orders' => rand(0, 50)
//    ]);
//}

$data = $model
    ->select([
        'email',
    ])
    ->find_one([
        'created_at' => [
            '$btw' => [
                new Time(time() - 60 * 60 * 60 * 5),
                new Time()
            ]
        ],
        'orders' => [
            '$btw' => [5, 20]
        ],
        'email' => [
            '$like' => '%1@gmail.com'
        ]
    ]);


print $model->get_sql_query();