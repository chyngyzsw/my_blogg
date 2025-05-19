<?php
session_start();
require_once __DIR__ . '/../app/Database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$errors = [];
$old_data = [
    'username' => '',
    'email' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $old_data['username'] = $username;
    $old_data['email'] = $email;

    if (empty($username)) {
        $errors['username'] = 'Имя пользователя обязательно.';
    }
    if (empty($email)) {
        $errors['email'] = 'Email обязателен.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат Email.';
    }
    if (empty($password)) {
        $errors['password'] = 'Пароль обязателен.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Пароль должен быть не менее 8 символов.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Пароль должен содержать хотя бы одну заглавную букву.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Пароль должен содержать хотя бы одну строчную букву.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Пароль должен содержать хотя бы одну цифру.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors['password'] = 'Пароль должен содержать хотя бы один специальный символ (например, !, @, #).';
    }
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Подтверждение пароля обязательно.';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Пароли не совпадают.';
    }

    if (empty($errors)) {

        try {

            $stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $errors['username'] = 'Пользователь с таким именем уже существует.';
            }


            if (!isset($errors['username'])) {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $errors['email'] = 'Пользователь с таким Email уже существует.';
                }
            }

            if (empty($errors)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);

                if ($stmt->execute()) {
                    $message = 'Регистрация прошла успешно! Теперь вы можете <a href="login.php">войти</a>.';

                    $old_data['username'] = '';
                    $old_data['email'] = '';
                } else {
                    $message = 'Произошла ошибка при регистрации. Пожалуйста, попробуйте еще раз.';
                }
            } else {
                $message = 'Пожалуйста, исправьте следующие ошибки:';
            }
        } catch (PDOException $e) {
            $message = "Ошибка базы данных: " . $e->getMessage();
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
    <title>Регистрация</title>
    <link rel="stylesheet" href="assets/css/register.css">
</head>

<body>
    <div class="container">
        <h2>Регистрация</h2>

        <?php if (!empty($message)): ?>
            <p class="message <?php echo empty($errors) ? 'success-message' : 'error-message'; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-group <?php echo isset($errors['username']) ? 'has-error' : ''; ?>">
                <label for="username">Имя пользователя:</label>
                <input type="text" id="username" name="username"
                    value="<?php echo htmlspecialchars($old_data['username']); ?>" required>
                <?php if (isset($errors['username'])): ?>
                    <p class="error-message"><?php echo $errors['username']; ?></p>
                <?php endif; ?>
            </div>
            <div class="form-group <?php echo isset($errors['email']) ? 'has-error' : ''; ?>">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old_data['email']); ?>"
                    required>
                <?php if (isset($errors['email'])): ?>
                    <p class="error-message"><?php echo $errors['email']; ?></p>
                <?php endif; ?>
            </div>
            <div class="form-group <?php echo isset($errors['password']) ? 'has-error' : ''; ?>">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
                <?php if (isset($errors['password'])): ?>
                    <p class="error-message"><?php echo $errors['password']; ?></p>
                <?php endif; ?>
            </div>
            <div class="form-group <?php echo isset($errors['confirm_password']) ? 'has-error' : ''; ?>">
                <label for="confirm_password">Подтвердите пароль:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <?php if (isset($errors['confirm_password'])): ?>
                    <p class="error-message"><?php echo $errors['confirm_password']; ?></p>
                <?php endif; ?>
            </div>
            <button type="submit">Зарегистрироваться</button>
            <p>Уже есть аккаунт? <a href="login.php">Войти</a></p>
        </form>
    </div>
</body>

</html>