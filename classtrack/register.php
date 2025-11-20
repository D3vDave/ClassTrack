<?php
session_start();
require __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($role) || empty($username) || empty($password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username already exists";
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hashed_password, $role);
            
            if ($stmt->execute()) {
                header("Location: login.php?registered=true");
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Register - ClassTrack</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>
<div class="login-container">
    <form method="POST">
        <h2>Register</h2>
        <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>

        <select name="role" required>
            <option value="">Select Role</option>
            <option value="student" <?= isset($_POST['role']) && $_POST['role'] === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="teacher" <?= isset($_POST['role']) && $_POST['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
        </select>

        <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

        <div class="password-container">
            <input type="password" name="password" id="regPassword" placeholder="Password" required>
            <span class="toggle-password" onclick="togglePassword('regPassword')">👁</span>
        </div>

        <div class="password-container">
            <input type="password" name="confirm_password" id="regConfirmPassword" placeholder="Confirm Password" required>
            <span class="toggle-password" onclick="togglePassword('regConfirmPassword')">👁</span>
        </div>

        <button type="submit">Register</button>
        <a href="login.php">Back to Login</a>
    </form>
</div>

<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    input.type = input.type === "password" ? "text" : "password";
}
</script>
</body>
</html>