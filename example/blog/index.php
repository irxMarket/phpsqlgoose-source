<?php

require_once '../../class.phpsqlgoose.php';

use phpgoose as pg;

new pg\Connection(PG_LOCALHOST, "root", "root", "My Blog", 1012);

$blog_schema = new pg\Schema(array(
    "id" => pg\_id(),
    "time" => TYPE_TIME,
    "picture" => [
        "type" => TYPE_URL,
    ],
    "title" => [
        "type" => TYPE_STRING,
        "default" => "Статья без названия"
    ],
    "text" => TYPE_STRING,
    "likes" => TYPE_BIGINT,
    "reports" => TYPE_BIGINT,
    "comments" => [
        "type" => TYPE_STRING
    ]
));

$post = new pg\Model("posts", $blog_schema);

if ($_POST['text']) {
    $post([
        'id' => pg\Generator::id(),
        'picture' => $_POST['image_url'],
        'title' => $_POST['title'],
        'text' => $_POST['text'],
        'time' => 9,
        'likes' => 0,
        'reports' => 0,
        'comments' => ''
    ]);
}

if ($_GET['post_for_like']) {
    $post->add_one([
        'id' => (int) $_GET['post_for_like']
    ], [
        'likes' => 1
    ]);
}

if ($_GET['post_for_report']) {
    $post->add_one([
        'id' => (int) $_GET['post_for_report']
    ], [
        'reports' => 1
    ]);
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Bootstrap 101 Template</title>

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    
    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
    
    <!-- Latest compiled and minified JavaScript -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
	
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
  	<nav class="navbar navbar-default">
      <div class="container">
    	<div class="navbar-header">
          <a class="navbar-brand" href="#">phpsqlgoose blog</a>
        </div>
      </div>
    </nav>

  	<div class="container">
        <div class="row">
            <div class="col-sm-2">
            	<a href="#">Home</a>	
            </div>
            <div class="col-sm-10">
                <ol class="breadcrumb">
                  <li><a href="#">Home</a></li>
                  <li><a href="#">Blog</a></li>
                  <li class="active">Data</li>
                </ol>

            	<div class="row">
                  <div class="col-sm-12 col-md-12">
                  <form method="POST">
                  	 <input name="title" class="form-control" type="text" placeholder="Title">
                  	 
                  	 <br> <textarea name="text" class="form-control mb-4" rows="3" placeholder="Type new post"></textarea>
                  	 <br> <input name="image_url" class="form-control" type="text" placeholder="Image url">
                  	 
                  	 <br>
                  	 <button type="submit" class="btn btn-primary btn-sm">Send new post</button>                  	 
                  </form>
                  
                  <br> <br>	
                  
                  <? foreach ($post->find([]) as $post): ?>
                    <div class="thumbnail">
                      <img src="<?=$post->picture ?>" alt="...">
                      <div class="caption">
                        <h3><?=$post->title ?></h3>
                        <p><?=$post->text ?></p>
                        <p>
                        	<a href="?post_for_like=<?=$post->id ?>#" class="btn btn-primary" role="button">Like: <?=$post->likes?></a>
                        	<a href="?post_for_report=<?=$post->id ?>#" class="btn btn-default" role="button">Report: <?=$post->reports?></a>
                    	</p>
                      </div>
                    </div>
                  <? endforeach; ?>
                  </div>
                </div>
            </div>
        </div>
    </div>
    

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="js/bootstrap.min.js"></script>
  </body>
</html>