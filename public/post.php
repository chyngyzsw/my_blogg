<?php
session_start();
require_once __DIR__ . '/../app/Database.php';

$database = new Database();
$db = $database->getConnection();

$post = null;
$message = '';
$comment_message = '';
$comment_errors = [];

$post_id = $_GET['id'] ?? null;

if (!$post_id || !is_numeric($post_id)) {
    $message = 'Некорректный ID поста.';
} else {
    try {

        $stmt = $db->prepare("SELECT p.id, p.title, p.content, p.image_path, p.is_private, p.created_at, p.user_id, u.username 
                              FROM posts p 
                              JOIN users u ON p.user_id = u.id 
                              WHERE p.id = :id 
                              LIMIT 1");
        $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);
        $stmt->execute();
        $post = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$post) {
            $message = 'Пост не найден.';
        } else {

            if ($post['is_private'] == 1) {
                if (!isset($_SESSION['user_id'])) {
                    $message = 'Этот пост приватный. Пожалуйста, войдите в систему, чтобы просмотреть его.';
                    $post = null;
                } elseif ($_SESSION['user_id'] != $post['user_id']) {
                    $message = 'У вас нет доступа к этому приватному посту.';
                    $post = null;
                }
            }
        }

    } catch (PDOException $e) {
        $message = "Ошибка базы данных при загрузке поста: " . $e->getMessage();
        error_log("Database error in post.php: " . $e->getMessage());
        $post = null;
    }
}

if ($post && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    $response = ['success' => false, 'message' => '', 'errors' => []];

    if (!isset($_SESSION['user_id'])) {
        $response['errors']['auth'] = 'Пожалуйста, войдите, чтобы оставить комментарий.';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        } else {
            $_SESSION['message'] = $response['errors']['auth'];
            $_SESSION['message_type'] = 'error';
            header('Location: login.php');
            exit();
        }
    } else {
        $user_id = $_SESSION['user_id'];
        $comment_content = trim($_POST['comment_content'] ?? '');
        if (empty($comment_content)) {
            $response['errors']['content'] = 'Комментарий не может быть пустым.';
        } elseif (mb_strlen($comment_content, 'UTF-8') > 1000) { // Используем mb_strlen для корректного подсчета символов UTF-8
            $response['errors']['content'] = 'Комментарий слишком длинный (макс. 1000 символов).';
        }

        if (empty($response['errors'])) {
            try {
                $stmt_user = $db->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
                $stmt_user->bindParam(':id', $user_id, PDO::PARAM_INT);
                $stmt_user->execute();
                $current_username = $stmt_user->fetchColumn();

                $stmt = $db->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (:post_id, :user_id, :content)");
                $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':content', $comment_content);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Отзыв успешно добавлен!';
                    $response['comment'] = [
                        'id' => $db->lastInsertId(),
                        'content' => $comment_content,
                        'created_at' => date('Y-m-d H:i:s'),
                        'username' => $current_username
                    ];
                } else {
                    $response['message'] = 'Ошибка при добавлении отзыва.';
                }
            } catch (PDOException $e) {
                $response['message'] = "Ошибка базы данных при добавлении отзыва: " . $e->getMessage();
                error_log("Database error in post.php (add comment via AJAX): " . $e->getMessage());
            }
        }
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        $_SESSION['message'] = $response['message'];
        $_SESSION['message_type'] = $response['success'] ? 'success' : 'error';
        if (!empty($response['errors'])) {
            $_SESSION['form_errors'] = $response['errors'];
        }
        header('Location: post.php?id=' . $post_id . '#comments-section');
        exit();
    }
}

$comments = [];
if ($post) {
    try {
        $stmt = $db->prepare("SELECT c.id, c.content, c.created_at, u.username 
                              FROM comments c 
                              JOIN users u ON c.user_id = u.id 
                              WHERE c.post_id = :post_id 
                              ORDER BY c.created_at ASC");
        $stmt->bindParam(':post_id', $post['id'], PDO::PARAM_INT);
        $stmt->execute();
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {

        error_log("Database error in post.php (load comments): " . $e->getMessage());
    }
}


?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $post ? htmlspecialchars($post['title']) : 'Пост не найден'; ?></title>
    <link rel="stylesheet" href="assets/css/post.css">

</head>

<body>
    <div class="container post-detail">
        <?php if (!empty($message)): ?>
            <div class="message-container">
                <p class="message error-message">
                    <?php echo $message; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($post): ?>
            <h2><?php echo htmlspecialchars($post['title']); ?></h2>
            <p class="post-meta-detail">
                Опубликовано: <span class="author"><?php echo htmlspecialchars($post['username']); ?></span> от
                <?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?>
                <?php if ($post['is_private']): ?>
                    <span class="private-badge-detail">Приватный</span>
                <?php endif; ?>
            </p>

            <?php if ($post['image_path']): ?>
                <img src="<?php echo htmlspecialchars($post['image_path']); ?>"
                    alt="<?php echo htmlspecialchars($post['title']); ?>" class="post-image">
            <?php endif; ?>

            <div class="post-content">
                <?php echo nl2br(htmlspecialchars($post['content'])); ?>
            </div>

            <div class="post-actions-detail">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
                    <a href="edit_post.php?id=<?php echo $post['id']; ?>">Редактировать пост</a>
                    <a href="delete_post.php?id=<?php echo $post['id']; ?>" class="delete-btn"
                        onclick="return confirm('Вы уверены, что хотите удалить этот пост?');">Удалить пост</a>
                <?php endif; ?>
                <a href="profile.php">Вернуться в личный кабинет</a>
                <a href="index.php">На главную</a>
            </div>

            <hr style="margin: 40px 0;">
            <div id="comments-section" class="comments-section">
                <h3>Отзывы (<?php echo count($comments); ?>)</h3>

                <div class="message-container" id="comment-form-messages"></div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <form action="post.php?id=<?php echo htmlspecialchars($post['id']); ?>" method="POST" class="comment-form">
                        <input type="hidden" name="add_comment" value="1">
                        <div class="form-group">
                            <label for="comment_content">Оставить отзыв:</label>
                            <textarea id="comment_content" name="comment_content" rows="4" placeholder="Напишите ваш отзыв..."
                                required><?php echo htmlspecialchars($_POST['comment_content'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="submit-comment-btn">Отправить отзыв</button>
                    </form>
                <?php else: ?>
                    <p class="login-to-comment">
                        <a href="login.php">Войдите</a> или <a href="register.php">зарегистрируйтесь</a>, чтобы оставить
                        отзыв.
                    </p>
                <?php endif; ?>

                <div class="comments-list">
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-card">
                                <p class="comment-meta">
                                    <span class="comment-author"><?php echo htmlspecialchars($comment['username']); ?></span> от
                                    <span
                                        class="comment-date"><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></span>
                                </p>
                                <div class="comment-content-text">
                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-comments">Пока нет отзывов к этому посту.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <a href="profile.php" class="back-link">Вернуться в личный кабинет</a>
            <a href="index.php" class="back-link" style="margin-top: 10px;">На главную</a>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const commentForm = document.querySelector('.comment-form');
            const commentsList = document.querySelector('.comments-list');
            const commentContentTextarea = document.getElementById('comment_content');
            const commentCountElement = document.querySelector('.comments-section h3');
            const commentFormMessagesContainer = document.getElementById('comment-form-messages');

            function displayAjaxMessage(msg, type) {
                if (msg) {
                    commentFormMessagesContainer.innerHTML = `<p class="message ${type}-message">${msg}</p>`;

                    setTimeout(() => {
                        commentFormMessagesContainer.innerHTML = '';
                    }, 5000);
                }
            }


            function formatDate(isoString) {
                const date = new Date(isoString);
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${day}.${month}.${year} ${hours}:${minutes}`;
            }


            function nl2br(str) {
                return str.replace(/(?:\r\n|\r|\n)/g, '<br>');
            }

            if (commentForm) {
                commentForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    commentFormMessagesContainer.innerHTML = '';
                    document.querySelectorAll('.comment-form .error-message').forEach(el => el.remove());
                    document.querySelectorAll('.comment-form .has-error').forEach(el => el.classList.remove('has-error'));

                    const formData = new FormData(commentForm);
                    const postId = <?php echo json_encode($post_id); ?>;
                    const url = `post.php?id=${postId}`;

                    fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(response => {
                            if (!response.ok) {

                                return response.text().then(text => { throw new Error(text) });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {

                                commentContentTextarea.value = '';
                                displayAjaxMessage(data.message, 'success');
                                const newCommentHtml = `
                        <div class="comment-card">
                            <p class="comment-meta">
                                <span class="comment-author">${data.comment.username}</span> от
                                <span class="comment-date">${formatDate(data.comment.created_at)}</span>
                            </p>
                            <div class="comment-content-text">
                                ${nl2br(data.comment.content)}
                            </div>
                        </div>
                    `;

                                commentsList.insertAdjacentHTML('beforeend', newCommentHtml);


                                let currentCommentCount = commentsList.querySelectorAll('.comment-card').length;
                                commentCountElement.innerHTML = `отзыв (${currentCommentCount})`;


                                const noCommentsMessage = commentsList.querySelector('.no-comments');
                                if (noCommentsMessage) {
                                    noCommentsMessage.remove();
                                }

                            } else {

                                displayAjaxMessage(data.message, 'error');


                                if (data.errors && data.errors.content) {
                                    const contentField = document.getElementById('comment_content');
                                    const formGroup = contentField.closest('.form-group');
                                    if (formGroup) {
                                        formGroup.classList.add('has-error');
                                        const errorP = document.createElement('p');
                                        errorP.classList.add('error-message');
                                        errorP.textContent = data.errors.content;
                                        formGroup.appendChild(errorP);
                                    }
                                }
                                if (data.errors && data.errors.auth) {
                                    displayAjaxMessage(data.errors.auth, 'error');

                                }
                            }
                        })
                        .catch(error => {
                            console.error('Ошибка AJAX:', error);
                            displayAjaxMessage('Произошла ошибка при отправке комментария. Пожалуйста, попробуйте еще раз. ' + error.message, 'error');
                        });
                });
            }
        });
    </script>