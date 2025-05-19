<?php
session_start();
require_once __DIR__ . '/../app/Database.php';

$database = new Database();
$db = $database->getConnection();

$public_posts = [];

try {
    $stmt = $db->prepare("SELECT p.id, p.title, p.content, p.image_path, p.created_at, u.username 
                              FROM posts p 
                              JOIN users u ON p.user_id = u.id 
                              WHERE p.is_private = 0 
                              ORDER BY p.created_at DESC");
    $stmt->execute();
    $public_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Ошибка загрузки публичных постов: " . $e->getMessage();
    $public_posts = [];
}

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная страница блога</title>
    <link rel="stylesheet" href="assets/css/index.css">

</head>

<body>
    <div class="container">
        <div class="nav-links">

            <div class="logo">
                <img src="assets/img/Group187.png" alt="logotype" class="logo_img">
                <p class="logo_text">Review Hub</p>
            </div>


            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php">Личный кабинет</a>
                <a href="logout.php">Выйти</a>
            <?php else: ?>
                <a class="button login_btn" href="login.php">Вход</a>
                <a class="button " href="register.php">Регистрация</a>
            <?php endif; ?>
        </div>

        <div class="header-main">
            <h1>Ваш голос имеет значение:
                Оставляйте и читайте честные отзывы!</h1>
            <p>Помогите другим принимать взвешенные решения, читайте мнения о тысячах товаров, услуг и мероприятиях.</p>
        </div>

        <?php if (!empty($public_posts)): ?>
            <div class="posts-grid">
                <?php foreach ($public_posts as $post): ?>
                    <div class="post-card">
                        <?php if ($post['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>"
                                alt="<?php echo htmlspecialchars($post['title']); ?>" class="post-thumbnail">
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="post-meta">
                            Опубликовано: <span class="author"><?php echo htmlspecialchars($post['username']); ?></span> от
                            <?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?>
                        </p>
                        <p><?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 150))) . (strlen($post['content']) > 150 ? '...' : ''); ?>
                        </p>
                        <a href="post.php?id=<?php echo $post['id']; ?>" class="read-more">Читать далее</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-posts-found">Пока нет публичных постов.</p>
        <?php endif; ?>
    </div>
</body>

</html>