<?php
session_start();
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../app/Database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$username = '';
$email = '';
$posts = [];

try {

    $stmt = $db->prepare("SELECT username, email FROM users WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $username = $user['username'];
        $email = $user['email'];
    } else {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }

    $stmt = $db->prepare("SELECT id, title, content, image_path, is_private, created_at FROM posts WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $_SESSION['message'] = "Ошибка базы данных: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    error_log("Database error in profile.php: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - <?php echo htmlspecialchars($username); ?></title>
    <link rel="stylesheet" href="assets/css/profile.css">

</head>

<body>
    <div class="container">
        <div class="profile-header">
            <h2>Добро пожаловать, <?php echo htmlspecialchars($username); ?>!</h2>
            <p>Ваш Email: <?php echo htmlspecialchars($email); ?></p>
            <p>У вас <?php echo count($posts); ?> постов.</p>
        </div>

        <?php if (!empty($message)): ?>
            <p class="message <?php echo htmlspecialchars($message_type); ?>-message">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <div class="profile-actions">

            <a href="add_post.php">Создать новый пост</a>
            <a href="logout.php">Выйти</a>
            <a href="index.php">Главная страница</a>
        </div>

        <h3>Ваши посты:</h3>
        <?php if (!empty($posts)): ?>
            <div class="posts-container">
                <?php foreach ($posts as $post): ?>
                    <div class="post-card">
                        <?php if ($post['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>"
                                alt="<?php echo htmlspecialchars($post['title']); ?>" class="post-thumbnail">
                        <?php endif; ?>
                        <h3>
                            <?php echo htmlspecialchars($post['title']); ?>
                            <?php if ($post['is_private']): ?>
                                <span class="private-badge">Приватный</span>
                            <?php endif; ?>
                        </h3>
                        <p class="post-meta">Опубликовано: <?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?></p>
                        <p><?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 100))) . (mb_strlen($post['content'], 'UTF-8') > 100 ? '...' : ''); ?>
                        </p>
                        <div class="post-actions">
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="view-btn">Просмотреть</a>
                            <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="edit-btn">Редактировать</a>
                            <a href="delete_post.php?id=<?php echo $post['id']; ?>" class="delete-btn"
                                onclick="return confirm('Вы уверены, что хотите удалить этот пост?');">Удалить</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-posts-found">У вас пока нет постов. <a href="add_post.php">Создайте первый!</a></p>
        <?php endif; ?>
    </div>
</body>

</html>