<?php
session_start();
require __DIR__ . '/db.php';

$error = '';
$success = '';

if (isset($_GET['registered'])) {
    $success = "Registration successful! Please login.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');

    // Validate CAPTCHA
    if (empty($_SESSION['captcha_code']) || empty($captcha)) {
        $error = "CAPTCHA validation failed. Please refresh the page and try again.";
        unset($_SESSION['captcha_code']);
        unset($_SESSION['captcha_time']);
    } elseif (strtolower($captcha) !== strtolower($_SESSION['captcha_code'])) {
        $error = "Invalid CAPTCHA. Please try again.";
        unset($_SESSION['captcha_code']);
        unset($_SESSION['captcha_time']);
    } else {
        // Use the existing MySQLi connection from db.php
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
                if ($user && password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_id'] = $user['id'];
            unset($_SESSION['captcha_code']);
            unset($_SESSION['captcha_time']);
            
            if ($user['role'] === 'teacher') {
                header("Location: teacher/dashboard.php");
            } else {
                header("Location: student/student_dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid login credentials";
            unset($_SESSION['captcha_code']);
            unset($_SESSION['captcha_time']);
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login - ClassTrack</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
<div class="login-container">
    <form method="POST" id="loginForm">
        <h2>ClassTrack Login</h2>
        <?php if (!empty($success)) echo "<div class='success'>$success</div>"; ?>
        <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>

        <select name="role" required>
            <option value="">Select Role</option>
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
        </select>

        <input type="text" name="username" placeholder="Username" required>

        <div class="password-container">
            <input type="password" name="password" id="loginPassword" placeholder="Password" required>
            <span class="toggle-password" onclick="togglePassword('loginPassword')">👁</span>
        </div>

        <div class="captcha-wrapper">
            <div class="captcha-container">
                <img src="captcha.php" alt="captcha" id="captchaImage">
                <button type="button" id="refreshCaptcha" class="captcha-refresh">↻</button>
            </div>
        </div>
        <input type="text" name="captcha" placeholder="Enter CAPTCHA" required class="captcha-input">

        <button type="submit">Login</button>
        <a href="register.php">Don't have an account? Register</a>
    </form>
</div>

<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    input.type = input.type === "password" ? "text" : "password";
}

document.getElementById("refreshCaptcha").addEventListener("click", function() {
    const captchaImage = document.getElementById("captchaImage");
    captchaImage.src = "captcha.php?refresh=true&t=" + new Date().getTime();
    
    this.classList.add("refreshing");
    setTimeout(() => {
        this.classList.remove("refreshing");
    }, 500);
});

<?php if (!empty($error)): ?>
window.addEventListener('load', function() {
    document.getElementById("refreshCaptcha").click();
});
<?php endif; ?>

document.getElementById("loginForm").addEventListener("submit", function() {
    <?php if (!empty($error)): ?>
    sessionStorage.setItem('refreshCaptcha', 'true');
    <?php endif; ?>
});

window.addEventListener('load', function() {
    if (sessionStorage.getItem('refreshCaptcha') === 'true') {
        document.getElementById("refreshCaptcha").click();
        sessionStorage.removeItem('refreshCaptcha');
    }
});
</script>
</body>
</html>