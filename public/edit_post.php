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
$post = null;
$message = '';
$errors = [];
$post_id = $_GET['id'] ?? null;

// Проверка наличия ID поста
if (!$post_id || !is_numeric($post_id)) {
    $message = 'Некорректный ID поста.';



} else {
    try {

        $stmt = $db->prepare("SELECT id, user_id, title, content, image_path, is_private FROM posts WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);
        $stmt->execute();
        $post = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$post) {
            $message = 'Пост не найден.';
        } elseif ($post['user_id'] != $user_id) {

            $message = 'У вас нет прав для редактирования этого поста.';

        }

    } catch (PDOException $e) {
        $message = "Ошибка базы данных при загрузке поста: " . $e->getMessage();
        error_log("Database error in edit_post.php (load): " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $post) {

    $new_title = trim($_POST['title'] ?? '');
    $new_content = trim($_POST['content'] ?? '');
    $new_is_private = isset($_POST['is_private']) ? 1 : 0;
    $current_image_path = $post['image_path'];

    if (empty($new_title)) {
        $errors['title'] = 'Заголовок поста обязателен.';
    } elseif (strlen($new_title) > 255) {
        $errors['title'] = 'Заголовок не должен превышать 255 символов.';
    }
    if (empty($new_content)) {
        $errors['content'] = 'Содержание поста обязательно.';
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . "/assets/images/uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $image_file_type = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $new_file_name = uniqid('post_img_', true) . '.' . $image_file_type;
        $target_file = $target_dir . $new_file_name;

        if (!in_array($image_file_type, $allowed_types)) {
            $errors['image'] = 'Разрешены только JPG, JPEG, PNG и GIF файлы.';
        }
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5 MB
            $errors['image'] = 'Размер изображения не должен превышать 5MB.';
        }

        if (!isset($errors['image'])) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {

                if ($current_image_path && file_exists(__DIR__ . '/' . $current_image_path)) {
                    unlink(__DIR__ . '/' . $current_image_path); // Удаляем старый файл
                }
                $image_path_to_save = 'assets/images/uploads/' . $new_file_name;
            } else {
                $errors['image'] = 'Ошибка при загрузке нового изображения.';
            }
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {

        if ($current_image_path && file_exists(__DIR__ . '/' . $current_image_path)) {
            unlink(__DIR__ . '/' . $current_image_path);
        }
        $image_path_to_save = null;
    } else {
        $image_path_to_save = $current_image_path;
    }
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE posts SET title = :title, content = :content, image_path = :image_path, is_private = :is_private WHERE id = :id AND user_id = :user_id");
            $stmt->bindParam(':title', $new_title);
            $stmt->bindParam(':content', $new_content);
            $stmt->bindParam(':image_path', $image_path_to_save); // Может быть null
            $stmt->bindParam(':is_private', $new_is_private, PDO::PARAM_BOOL);
            $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = 'Пост успешно обновлен! <a href="profile.php">Вернуться в личный кабинет</a>.';
                $post['title'] = $new_title;
                $post['content'] = $new_content;
                $post['image_path'] = $image_path_to_save;
                $post['is_private'] = $new_is_private;
            } else {
                $message = 'Произошла ошибка при обновлении поста. Пожалуйста, попробуйте еще раз.';
            }
        } catch (PDOException $e) {
            $message = "Ошибка базы данных: " . $e->getMessage();
            error_log("Database error in edit_post.php (update): " . $e->getMessage());
        }
    } else {
        $message = 'Пожалуйста, исправьте следующие ошибки:';
        $post['title'] = $new_title;
        $post['content'] = $new_content;
        $post['is_private'] = $new_is_private;
    }
}

if (!$post || $post['user_id'] != $user_id) {
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать пост</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container {
            max-width: 700px;
            display: flex;
            display: block;
            justify-content: center;
            align-items: center;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
            min-height: 150px;
            resize: vertical;
            transition: border-color 0.3s ease;
        }

        .form-group textarea:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        .form-group.checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 20px;
        }

        .form-group.checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            width: auto;
        }

        .form-group.checkbox-group label {
            margin-bottom: 0;
        }

        .back-link {
            display: block;
            margin-top: 25px;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
            text-align: center;
        }

        .back-link:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .current-image {
            margin-top: 10px;
            text-align: center;
        }

        .current-image img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }

        .current-image p {
            font-size: 0.9em;
            color: #777;
        }

        .remove-image-checkbox {
            margin-top: 10px;
            margin-bottom: 15px;
            text-align: left;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Редактировать пост</h2>

        <?php if (!empty($message)): ?>
            <p class="message <?php echo empty($errors) && $post ? 'success-message' : 'error-message'; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <?php if ($post && $post['user_id'] == $user_id): ?>
            <form action="edit_post.php?id=<?php echo htmlspecialchars($post_id); ?>" method="POST"
                enctype="multipart/form-data">
                <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id']); ?>">

                <div class="form-group <?php echo isset($errors['title']) ? 'has-error' : ''; ?>">
                    <label for="title">Заголовок поста:</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>"
                        required>
                    <?php if (isset($errors['title'])): ?>
                        <p class="error-message"><?php echo $errors['title']; ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group <?php echo isset($errors['content']) ? 'has-error' : ''; ?>">
                    <label for="content">Содержание поста:</label>
                    <textarea id="content" name="content"
                        required><?php echo htmlspecialchars($post['content']); ?></textarea>
                    <?php if (isset($errors['content'])): ?>
                        <p class="error-message"><?php echo $errors['content']; ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group <?php echo isset($errors['image']) ? 'has-error' : ''; ?>">
                    <label for="image">Изображение (оставьте пустым, чтобы не менять):</label>
                    <?php if ($post['image_path']): ?>
                        <div class="current-image">
                            <p>Текущее изображение:</p>
                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="Текущее изображение поста">
                            <div class="remove-image-checkbox form-group checkbox-group">
                                <input type="checkbox" id="remove_image" name="remove_image" value="1">
                                <label for="remove_image">Удалить текущее изображение</label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif">
                    <?php if (isset($errors['image'])): ?>
                        <p class="error-message"><?php echo $errors['image']; ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="is_private" name="is_private" value="1" <?php echo $post['is_private'] ? 'checked' : ''; ?>>
                    <label for="is_private">Сделать пост приватным (доступен только мне)</label>
                </div>

                <button type="submit">Сохранить изменения</button>
            </form>
        <?php endif; ?>
        <a href="profile.php" class="back-link">← Вернуться в личный кабинет</a>
    </div>
</body>

</html>