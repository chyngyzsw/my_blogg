<?php
session_start();
require_once __DIR__ . '/../app/Database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$errors = [];
$old_data = [
    'title' => '',
    'content' => '',
    'is_private' => false
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_private = isset($_POST['is_private']) ? 1 : 0;

    $old_data['title'] = $title;
    $old_data['content'] = $content;
    $old_data['is_private'] = ($is_private == 1);

    if (empty($title)) {
        $errors['title'] = 'Заголовок поста обязателен.';
    } elseif (strlen($title) > 255) {
        $errors['title'] = 'Заголовок не должен превышать 255 символов.';
    }

    if (empty($content)) {
        $errors['content'] = 'Содержание поста обязательно.';
    }

    $image_path = null;


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
                // Сохраняем путь относительно public/
                $image_path = 'assets/images/uploads/' . $new_file_name;
            } else {
                $errors['image'] = 'Ошибка при загрузке изображения.';
            }
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {

        $errors['image'] = 'Ошибка загрузки файла: ' . $_FILES['image']['error'];
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO posts (user_id, title, content, image_path, is_private) VALUES (:user_id, :title, :content, :image_path, :is_private)");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':image_path', $image_path);
            $stmt->bindParam(':is_private', $is_private, PDO::PARAM_BOOL);

            if ($stmt->execute()) {
                $message = 'Пост успешно создан! <a href="profile.php">Вернуться в личный кабинет</a> или <a href="add_post.php">создать еще один</a>.';

                $old_data['title'] = '';
                $old_data['content'] = '';
                $old_data['is_private'] = false;
            } else {
                $message = 'Произошла ошибка при сохранении поста. Пожалуйста, попробуйте еще раз.';
            }
        } catch (PDOException $e) {
            $message = "Ошибка базы данных: " . $e->getMessage();
            error_log("Database error in add_post.php: " . $e->getMessage());
        }
    } else {
        $message = 'Пожалуйста, исправьте следующие ошибки:';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать новый пост</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container {
            max-width: 700px;

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
    </style>
</head>

<body>
    <div class="container">
        <h2>Создать новый пост</h2>

        <?php if (!empty($message)): ?>
            <p class="message <?php echo empty($errors) ? 'success-message' : 'error-message'; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <form action="add_post.php" method="POST" enctype="multipart/form-data">
            <div class="form-group <?php echo isset($errors['title']) ? 'has-error' : ''; ?>">
                <label for="title">Заголовок поста:</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($old_data['title']); ?>"
                    required>
                <?php if (isset($errors['title'])): ?>
                    <p class="error-message"><?php echo $errors['title']; ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo isset($errors['content']) ? 'has-error' : ''; ?>">
                <label for="content">Содержание поста:</label>
                <textarea id="content" name="content"
                    required><?php echo htmlspecialchars($old_data['content']); ?></textarea>
                <?php if (isset($errors['content'])): ?>
                    <p class="error-message"><?php echo $errors['content']; ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo isset($errors['image']) ? 'has-error' : ''; ?>">
                <label for="image">Изображение (опционально):</label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif">
                <?php if (isset($errors['image'])): ?>
                    <p class="error-message"><?php echo $errors['image']; ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" id="is_private" name="is_private" value="1" <?php echo $old_data['is_private'] ? 'checked' : ''; ?>>
                <label for="is_private">Сделать пост приватным (доступен только мне)</label>
            </div>

            <button type="submit">Создать пост</button>
        </form>
        <a href="profile.php" class="back-link">← Вернуться в личный кабинет</a>
    </div>
</body>

</html>