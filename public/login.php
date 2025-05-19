<?php
session_start();
require_once __DIR__ . '/../app/Database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$old_username_email = '';

if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_email = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';

    $old_username_email = $username_email;

    if (empty($username_email) || empty($password)) {
        $message = 'Пожалуйста, введите логин (имя пользователя или Email) и пароль.';
    } else {
        try {

            if (filter_var($username_email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $db->prepare("SELECT id, username, email, password FROM users WHERE email = :username_email LIMIT 1");
            } else {

                $stmt = $db->prepare("SELECT id, username, email, password FROM users WHERE username = :username_email LIMIT 1");
            }

            $stmt->bindParam(':username_email', $username_email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];


                header('Location: profile.php');
                exit();
            } else {
                $message = 'Неверный логин или пароль.';
            }
        } catch (PDOException $e) {
            $message = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в аккаунт</title>
    <link rel="stylesheet" href="assets/css/register.css">
</head>

<body>
    <div class="container">
        <h2>Вход в аккаунт</h2>

        <?php if (!empty($message)): ?>
            <p class="message error-message">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username_email">Имя пользователя или Email:</label>
                <input type="text" id="username_email" name="username_email"
                    value="<?php echo htmlspecialchars($old_username_email); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Войти</button>
            <p>Ещё нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
        </form>
    </div>
</body>

</html>