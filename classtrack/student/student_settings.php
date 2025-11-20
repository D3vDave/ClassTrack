<?php
session_start();
include '../db.php';

// Ensure student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];
$toast = "";

/* HANDLE Change Username */
if (isset($_POST['change_username'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $password = $_POST['password_confirm_username'] ?? '';

    if ($new_username === '') {
        $toast = "Please enter a username.";
    } else {
        $u_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $u_stmt->bind_param("i", $student_id);
        $u_stmt->execute();
        $u_stmt->bind_result($hashed_pw);
        $u_stmt->fetch();
        $u_stmt->close();

        if (!$hashed_pw || !password_verify($password, $hashed_pw)) {
            $toast = "Password incorrect. Username not changed.";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->bind_param("si", $new_username, $student_id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $toast = "Username already taken. Choose another.";
                $check->close();
            } else {
                $check->close();
                $upd = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                $upd->bind_param("si", $new_username, $student_id);
                if ($upd->execute()) {
                    $_SESSION['username'] = $new_username;
                    $current_username = $new_username;
                    $toast = "Username updated successfully!";
                } else {
                    $toast = "Failed to update username: " . $upd->error;
                }
                $upd->close();
            }
        }
    }
}

/* HANDLE Change Password */
if (isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password === '' || $confirm_password === '' || $old_password === '') {
        $toast = "Please fill all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $toast = "New password and confirmation do not match.";
    } else {
        $p_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $p_stmt->bind_param("i", $student_id);
        $p_stmt->execute();
        $p_stmt->bind_result($current_hashed);
        $p_stmt->fetch();
        $p_stmt->close();

        if (!$current_hashed || !password_verify($old_password, $current_hashed)) {
            $toast = "Old password is incorrect.";
        } else {
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $u_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $u_stmt->bind_param("si", $new_hashed, $student_id);
            if ($u_stmt->execute()) {
                $toast = "Password changed successfully!";
            } else {
                $toast = "Failed to update password: " . $u_stmt->error;
            }
            $u_stmt->close();
        }
    }
}

/* HANDLE Delete Account */
if (isset($_POST['confirm_delete_account'])) {
    $confirm_username = trim($_POST['confirm_username'] ?? '');
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($confirm_username !== $current_username) {
        $toast = "Entered username does not match your account.";
    } else {
        $d_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $d_stmt->bind_param("i", $student_id);
        $d_stmt->execute();
        $d_stmt->bind_result($pw_hash_for_delete);
        $d_stmt->fetch();
        $d_stmt->close();

        if (!$pw_hash_for_delete || !password_verify($confirm_password, $pw_hash_for_delete)) {
            $toast = "Password incorrect. Account not deleted.";
        } else {
            $conn->begin_transaction();
            try {
                // Delete student's class enrollments
                $del_enroll = $conn->prepare("DELETE FROM student_classes WHERE student_id = ?");
                $del_enroll->bind_param("i", $student_id);
                $del_enroll->execute();
                $del_enroll->close();

                // Delete student's hidden announcements
                $del_hidden = $conn->prepare("DELETE FROM student_hidden_announcements WHERE student_id = ?");
                $del_hidden->bind_param("i", $student_id);
                $del_hidden->execute();
                $del_hidden->close();

                // Delete user account
                $del_user = $conn->prepare("DELETE FROM users WHERE id = ?");
                $del_user->bind_param("i", $student_id);
                $del_user->execute();
                $del_user->close();

                $conn->commit();

                session_unset();
                session_destroy();
                header("Location: ../login.php?msg=account_deleted");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $toast = "Failed to delete account: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Student Settings | ClassTrack</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="dashboard">
  <!-- Sidebar -->
  <div class="sidebar">
  <div class="profile-simple">
    <h3>Welcome, <?php echo $_SESSION['username']; ?></h3>
    <span class="role">Student</span>
  </div>

    <ul class="menu">
    <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
    <li><a href="student_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
    <li><a href="student_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
    <li class="active"><a href="student_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    <li class="logout-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
    </ul>
  </div>

  <!-- Content -->
  <div class="content">
    <header>
      <h2>Settings</h2>
    </header>

    <div class="cards">
      <!-- Change Username -->
      <div class="card">
        <h3>Change Username</h3>
        <form method="POST" class="settings-form">
          <div class="form-group">
            <label>Current Username</label>
            <input type="text" value="<?= htmlspecialchars($current_username) ?>" disabled>
          </div>

          <div class="form-group">
            <label>New Username</label>
            <input type="text" name="new_username" required>
          </div>

          <div class="form-group">
            <label>Confirm with Password</label>
            <input type="password" name="password_confirm_username" required placeholder="Enter current password">
          </div>

          <div class="form-actions">
            <button type="submit" name="change_username">Save Username</button>
          </div>
        </form>
      </div>

      <!-- Change Password -->
      <div class="card">
        <h3>Change Password</h3>
        <form method="POST" class="settings-form">
          <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="old_password" required>
          </div>

          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required>
          </div>

          <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>
          </div>

          <div class="form-actions">
            <button type="submit" name="change_password">Save Password</button>
          </div>
        </form>
      </div>

      <!-- Delete Account -->
      <div class="card">
        <h3 style="color:#d32f2f;">Delete Account</h3>
        <p>This will permanently remove your account and class enrollments.</p>
        <div class="form-actions">
          <button class="btn-delete" onclick="openModal('deleteModal')">Delete Account</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 style="color:#d32f2f;">Confirm Account Deletion</h3>
      <span class="close" onclick="closeModal('deleteModal')">&times;</span>
    </div>

    <form method="POST" style="padding:10px 0;">
      <p><strong>To permanently delete your account, enter your username and password below.</strong></p>
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="confirm_username" placeholder="Enter your username" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="confirm_password" placeholder="Enter your password" required>
      </div>

      <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
        <button type="button" onclick="closeModal('deleteModal')">Cancel</button>
        <button type="submit" name="confirm_delete_account" class="btn-delete">Yes, delete my account</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast -->
<div id="toast"></div>

<script>
function openModal(id){document.getElementById(id).style.display='flex';}
function closeModal(id){document.getElementById(id).style.display='none';}

function showToast(msg){
  const t=document.getElementById('toast');
  t.textContent=msg;
  t.className='show';
  setTimeout(()=>t.className=t.className.replace('show',''),3000);
}

<?php if ($toast): ?>
showToast("<?= htmlspecialchars($toast) ?>");
<?php endif; ?>

window.onclick = function(e) {
  document.querySelectorAll('.modal').forEach(m => {
    if (e.target === m) m.style.display = 'none';
  });
}
</script>

</body>
</html>