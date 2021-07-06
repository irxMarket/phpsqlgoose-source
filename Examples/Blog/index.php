<?php require_once 'API.php';?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@200;300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/skeleton/2.0.4/skeleton.min.css"/>
    <title>SQL Blog</title>
</head>
<body>

    <?php if (!$_COOKIE['login']): ?>
    <div class="container center">
        <div class="row" style="display: flex; justify-content: center; margin-top: 128px;">

            <?php if ($_GET['form'] == 'register' or !isset($_GET['form'])): ?>
            <div class="four columns">
                <h3>Register in blog</h3>

                <form method="POST">
                    <div>
                        <label for="email">Your email</label>
                        <input class="u-full-width" type="email" placeholder="yourmail@mail.com" name="email">
                    </div>

                    <div>
                        <label for="login">Your login</label>
                        <input class="u-full-width" type="text" placeholder="nickname" name="login">
                    </div>

                    <div>
                        <label for="password">Your password</label>
                        <input class="u-full-width" type="password" placeholder="strong password" name="password">
                    </div>

                    <input class="button-primary" type="submit" value="Submit">
                    <a class="button" href="?form=login">Login</a>

                    <div>
                        <small>
                            <?php echo API::$register_error; ?>
                        </small>
                    </div>

                </form>
            </div>
            <?php endif; if ($_GET['form'] == 'login'): ?>
                <div class="four columns">
                    <h3>Login in blog</h3>

                    <form method="POST">

                        <div>
                            <label for="login">Your login or email</label>
                            <input class="u-full-width" type="text" placeholder="youremail@mail.com or nickname" name="login">
                        </div>

                        <div>
                            <label for="password">Your password</label>
                            <input class="u-full-width" type="password" placeholder="strong password" name="password">
                        </div>

                        <input class="button-primary" type="submit" value="Submit">
                        <a class="button" href="?">Register</a>

                        <div>
                            <small>
                                <?php echo API::$login_error; ?>
                            </small>
                        </div>

                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; if ($_COOKIE['login']): ?>

    <!-- .container is main centered wrapper -->
    <div class="container">
        <h3 style="margin: 64px 0px">SQL Blog</h3>

        <div class="row">
            <div class="three columns">
                <div>
                    <a href="?popular=1">Popular blogs</a>
                </div>
                <div>
                    <a href=".">My entries</a>
                </div>

                <?php foreach (API::authors() as $author): ?>

                    <div>
                        <a href="?author=<?= $author['login'] ?>"><?= $author['login'] ?></a>
                    </div>

                <?php endforeach; ?>

                <div style="margin-top: 24px">
                    <button class="button">New entry</button>
                </div>
            </div>

            <?php if ($_GET['author']): ?>

                <div class="nine columns">
                    <h5><?= $_GET['author']?> posts</h5>

                    <div>
                        <?php foreach (API::another_user_posts() as $post): ?>

                            <div style="margin-bottom: 24px">
                                <h5 style="margin: 0"><?= $post['title']; ?></h5>
                                <p style="margin: 0"><?= htmlspecialchars($post['text']); ?></p>
                                <div>
                                    <small><?= $post['likes']; ?> likes (<a href="?like_post=<?= $post['id']; ?>">Like</a>)</small>
                                    /
                                    <small><?= $post['views']; ?> views</small>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($_GET['popular']): ?>

                <h5>Popular posts</h5>

                <div>
                    <?php foreach (API::popular_posts() as $post): ?>

                        <div style="margin-bottom: 24px">
                            <h5 style="margin: 0"><?= $post['title']; ?></h5>
                            <p style="margin: 0"><?= htmlspecialchars($post['text']); ?></p>
                            <div>
                                <small><?= $post['likes']; ?> likes (<a href="?like_post=<?= $post['id']; ?>">Like</a>)</small>
                                /
                                <small><?= $post['views']; ?> views</small>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </div>

            <?php else: ?>

                <div class="nine columns">
                    <h6>New blog post</h6>

                    <form method="POST">
                        <input type="text" class="u-full-width" name="blog-title" placeholder="Title"/>

                        <textarea name="blog-post" id="" cols="30" rows="10" class="u-full-width"></textarea>
                        <div>
                            <button class="button u-full-width">New entry</button>
                        </div>
                    </form>

                    <div>
                        <small>
                            <?php echo API::$blog_error; ?>
                        </small>
                    </div>

                    <div>
                        <p>YOUR POSTS:</p>
                    </div>

                    <div>
                        <?php foreach (API::user_posts() as $post): ?>

                            <div style="margin-bottom: 24px">
                                <h5 style="margin: 0"><?= $post['title']; ?></h5>
                                <p style="margin: 0"><?= $post['text']; ?></p>
                                <div>
                                    <small><?= $post['likes']; ?> likes (<a href="?like_post=<?= $post['id']; ?>">Like</a>)</small>
                                    /
                                    <small><?= $post['views']; ?> views</small>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <?php endif; ?>

</body>
</html>
<?php
