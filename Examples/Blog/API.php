<?php

require_once '../../Class/class.phpsqlgoose.php';

use \phpgoose\Connection;
use \phpgoose\Model;
use \phpgoose\Schema;
use \Helpers\Generator;
use \Wrapper\Time;
use function Types\DateTime;
use function Types\Email;
use function Types\ID;
use function Types\Nickname;
use function Types\Password;

// MySQL login data
$host = '127.0.0.1';
$username = 'root';
$password = 'root';
$db_name = "SQL Blog";
$port = 1012;

new Connection($host, $username, $password, $db_name, $port);

global $post, $user;

$user = new Model('user', new Schema([
    'id' => ID(),
    'created_at' => DateTime(),
    'password' => Password(),
    'email' => Email(),
    'login' => Nickname()
]));

$post = new Model('post', new Schema([
    'id' => ID(),
    'created_at' => DateTime(),
    'author' => [
        'comment' => 'Nickname',
        'not_null' => true,
        'type' => TYPE_STRING,
        'regex' => REGEX_NICKNAME,
        'unique' => false,
        'min' => 4,
        'max' => 32
    ],
    'text' => [
        'type' => TYPE_TEXT,
    ],
    'title' => [
        'type' => TYPE_STRING,
    ],
    'likes' => [
        'type' => TYPE_BIGINT,
        'default' => 0,
        'min' => 0,
        'max'=> 1000
    ],
    'views' => [
        'type' => TYPE_BIGINT,
        'default' => 0,
        'min' => 0,
        'max'=> 1000
    ]
]));


// If data is sent from the form, register the user
if ($_POST['email'] and $_POST['login'] and $_POST['password']) {
    try {
        $result = $user->insert_one([
            'created_at' => Generator::current_time(),
            'email' => $_POST['email'],
            'password' => $_POST['password'],
            'login' => $_POST['login']
        ]);

        if ($result == true) {
            setcookie('login', $_POST['login']);
        } else {
            API::$register_error = 'Account not created';
        }
    } catch (ErrorException $e) {
        API::$register_error = $e->getMessage();
    } catch (\phpgoose\RegexException $e) {
        API::$register_error = $e->getMessage();
    } catch (\phpgoose\SQLException $e) {
        API::$register_error = $e->getMessage();
    }
}


// If data is sent from the form, login the user
if ($_POST['login'] and $_POST['password']) {
    try {
        $result = $user->find_one([
            '$or' => [
                'email' => $_POST['login'],
                'login' => $_POST['login'],
            ],
            'password' => $_POST['password'],
        ]);
    } catch (ErrorException $e) {
        API::$login_error = $e->getMessage();
    } catch (\phpgoose\RegexException $e) {
        API::$login_error = $e->getMessage();
    } catch (\phpgoose\SQLException $e) {
        API::$login_error = $e->getMessage();
    }

    if ($result == true) {
        setcookie('login', $_POST['login']);
    } else {
        API::$login_error = 'Account not found';
    }
}


// If data is sent from the form, publish post
if ($_POST['blog-post'] and $_POST['blog-title']) {
    try {
        $result = $post->insert_one([
            'created_at' => Generator::current_time(),
            'author' => $_COOKIE['login'],
            'text' => str_replace(['\n', '\r\n', "\n", "\r\n"], '<br>', $_POST['blog-post']),
            'title' => $_POST['blog-title'],
        ]);

    } catch (ErrorException $e) {
        API::$blog_error = $e->getMessage();
    } catch (\phpgoose\RegexException $e) {
        API::$blog_error = $e->getMessage();
    } catch (\phpgoose\SQLException $e) {
        API::$blog_error = $e->getMessage();
    }

}


// If data is sent from the form, like post
if ($_COOKIE['login'] and $_GET['like_post']) {

    $post->update_one([
        'id' => intval($_GET['like_post']),
        'author' => [
            '$not' => $_COOKIE['login']
        ]
    ], [
        'likes' => [
            '$inc' => 1
        ]
    ]);

    header('Location: .');
}


abstract class API {
    static $login_error = false;
    static $register_error = false;
    static $blog_error = false;

    static function another_user_posts() {
        global $post;

        try {
            $post
                ->update_many([
                    'author' => $_GET['author']
                ], [
                    'views' => [
                        '$inc' => 1
                    ]
                ]);

            return $post
                ->reverse(true)
                ->limit(900)
                ->find([
                    'author' => $_GET['author']
                ]);
        } catch (ErrorException $e) {
            return [];
        } catch (\phpgoose\RegexException $e) {
            return [];
        } catch (\phpgoose\SQLException $e) {
            return [];
        }

    }

    /**
     * Retrieve all entries made by the user
     */
    static function user_posts() {
        global $post;

        try {
            return $post
                ->reverse(true)
                ->limit(900)
                ->find([
                    'author' => $_COOKIE['login']
                ]);
        } catch (ErrorException $e) {
            return [];
        } catch (\phpgoose\RegexException $e) {
            return [];
        } catch (\phpgoose\SQLException $e) {
            return [];
        }
    }

    /**
     * Retrieve all popular posts
     */
    static function popular_posts() {
        global $post;

        try {
            return $post
                ->reverse(true)
                ->limit(900)
                ->find([
                    'views' => [
                        '$gte' => 8
                    ],
                    'likes' => [
                        '$gte' => 5,
                        '$lte' => 500
                    ],
                    'created_at' => [
                        '$btw' => [
                            new Time(time() - 100000),
                            new Time(time()),
                        ]
                    ]
                ]);

        } catch (ErrorException $e) {
            return [];
        } catch (\phpgoose\RegexException $e) {
            return [];
        } catch (\phpgoose\SQLException $e) {
            return [];
        }
    }


    /**
     * Authors
     */
    static function authors() {
        global $user;

        try {
            return $user
                ->select('login')
                ->reverse(true)
                ->find([
                    'login' => [
                        '$not' => $_COOKIE['login']
                    ]
                ]);
        } catch (ErrorException $e) {
            return [];
        } catch (\phpgoose\RegexException $e) {
            return [];
        } catch (\phpgoose\SQLException $e) {
            return [];
        }
    }
}