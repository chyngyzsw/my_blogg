<?php
session_start();
require_once __DIR__ . '/../app/Database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$post_id = $_GET['id'] ?? null;

if (!$post_id || !is_numeric($post_id)) {
    $_SESSION['message'] = 'Некорректный ID поста для удаления.';
    $_SESSION['message_type'] = 'error';
    header('Location: profile.php');
    exit();
}

try {

    $stmt = $db->prepare("SELECT image_path FROM posts WHERE id = :id AND user_id = :user_id LIMIT 1");
    $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $_SESSION['message'] = 'Пост не найден или у вас нет прав для его удаления.';
        $_SESSION['message_type'] = 'error';
        header('Location: profile.php');
        exit();
    }


    if ($post['image_path'] && file_exists(__DIR__ . '/' . $post['image_path'])) {
        unlink(__DIR__ . '/' . $post['image_path']); // Удаляем файл изображения с сервера
    }

    $stmt = $db->prepare("DELETE FROM posts WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $_SESSION['message'] = 'Пост успешно удален!';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Произошла ошибка при удалении поста из базы данных.';
        $_SESSION['message_type'] = 'error';
    }

} catch (PDOException $e) {
    $_SESSION['message'] = "Ошибка базы данных при удалении поста: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    error_log("Database error in delete_post.php: " . $e->getMessage()); // Логируем ошибку
}

header('Location: profile.php');
exit();
?>