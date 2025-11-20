<?php
session_start();

// Include database connection with correct path
$db_path = '../db.php';
if (!file_exists($db_path)) {
    die("Database configuration file not found: " . $db_path);
}

include $db_path;

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check your database configuration.");
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Create the student_deleted_announcements table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS student_deleted_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    announcement_id INT NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_deleted (student_id, announcement_id)
)";
$conn->query($create_table_sql);

// Handle announcement deletion (delete from student's view)
if (isset($_POST['delete_announcement'])) {
    $announcement_id = $_POST['announcement_id'] ?? '';
    
    if (!empty($announcement_id)) {
        // Check if the student has access to this announcement
        $check_sql = "SELECT a.id FROM announcements a 
                     JOIN class_schedules cs ON a.class_id = cs.id 
                     JOIN student_classes sc ON cs.id = sc.class_id 
                     WHERE a.id = ? AND sc.student_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $announcement_id, $student_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Delete the announcement for this student
            $delete_sql = "INSERT IGNORE INTO student_deleted_announcements (student_id, announcement_id) VALUES (?, ?)";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $student_id, $announcement_id);
            
            if ($delete_stmt->execute()) {
                $success = "Announcement deleted successfully!";
            } else {
                $error = "Failed to delete announcement: " . $conn->error;
            }
            $delete_stmt->close();
        } else {
            $error = "You don't have permission to delete this announcement.";
        }
        $check_stmt->close();
    }
}

// Handle bulk deletion
if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['selected_announcements'])) {
        $announcement_ids = $_POST['selected_announcements'];
        $success_count = 0;
        
        foreach ($announcement_ids as $announcement_id) {
            // Check if the student has access to this announcement
            $check_sql = "SELECT a.id FROM announcements a 
                         JOIN class_schedules cs ON a.class_id = cs.id 
                         JOIN student_classes sc ON cs.id = sc.class_id 
                         WHERE a.id = ? AND sc.student_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $announcement_id, $student_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Delete the announcement for this student
                $delete_sql = "INSERT IGNORE INTO student_deleted_announcements (student_id, announcement_id) VALUES (?, ?)";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("ii", $student_id, $announcement_id);
                
                if ($delete_stmt->execute()) {
                    $success_count++;
                }
                $delete_stmt->close();
            }
            $check_stmt->close();
        }
        
        if ($success_count > 0) {
            $success = $success_count . " announcement(s) deleted successfully!";
        } else {
            $error = "No announcements were deleted. Please try again.";
        }
    } else {
        $error = "No announcements selected for deletion.";
    }
}

// Fetch all announcements for student's classes, excluding deleted ones
$sql = "SELECT a.*, cs.subject_code, cs.section, u.username as teacher_name 
        FROM announcements a 
        JOIN class_schedules cs ON a.class_id = cs.id 
        JOIN users u ON a.teacher_id = u.id 
        WHERE cs.id IN (SELECT class_id FROM student_classes WHERE student_id = ?) 
        AND a.id NOT IN (SELECT announcement_id FROM student_deleted_announcements WHERE student_id = ?)
        ORDER BY a.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}

// Check announcement status (active, scheduled, expired)
$now = date('Y-m-d H:i:s');
foreach ($announcements as &$announcement) {
    $start_datetime = $announcement['start_date'] . ' ' . $announcement['start_time'];
    $end_datetime = $announcement['end_date'] ? $announcement['end_date'] . ' ' . $announcement['end_time'] : null;
    
    if ($start_datetime > $now) {
        $announcement['status'] = 'scheduled';
    } elseif ($end_datetime && $end_datetime < $now) {
        $announcement['status'] = 'expired';
    } else {
        $announcement['status'] = 'active';
    }
}
unset($announcement);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Announcements - Student</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="dashboard">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="profile-simple">
      <h3>Welcome, <?php echo $_SESSION['username']; ?></h3>
      <span class="role">Student</span>
    </div>
    <ul class="menu">
      <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="student_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
      <li class="active"><a href="student_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
      <li><a href="student_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
      <li class="logout-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="content">
    <header>
      <h2>Announcements</h2>
      <?php if ($announcements): ?>
        <button class="btn-delete-all" onclick="openModal('deleteAllModal')">
          <i class="fas fa-trash-alt"></i> Delete All
        </button>
      <?php endif; ?>
    </header>

    <?php if (isset($success)): ?>
      <div class="alert success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
      <div class="alert error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <!-- Bulk Actions -->
    <?php if ($announcements): ?>
    <div class="bulk-actions" id="bulkActions" style="display: none;">
        <div class="bulk-actions-content">
            <div class="bulk-actions-info">
                <i class="fas fa-check-circle"></i>
                <span id="selectedCount">0 announcements selected</span>
                <span class="selection-counter" id="selectionCounter">0</span>
            </div>
            <div class="bulk-actions-buttons">
                <button class="btn-bulk-delete" onclick="openModal('deleteSelectedModal')">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <button class="btn-bulk-cancel" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Options -->
    <div class="filter-options" style="margin-bottom: 20px;">
      <label>Filter by status:</label>
      <select id="statusFilter" onchange="filterAnnouncements()">
        <option value="all">All Announcements</option>
        <option value="active">Active</option>
        <option value="scheduled">Scheduled</option>
        <option value="expired">Expired</option>
      </select>
    </div>

    <!-- Announcements List -->
    <section class="announcements-section">
      <?php if ($announcements): ?>
        <?php foreach ($announcements as $a): ?>
          <div class="announcement-item <?= $a['is_important'] ? 'important' : '' ?> <?= $a['status'] ?>" 
               data-status="<?= $a['status'] ?>" data-announcement-id="<?= $a['id'] ?>">
            <div class="announcement-checkbox">
              <input type="checkbox" name="selected_announcements[]" value="<?= $a['id'] ?>" onchange="updateBulkActions()">
            </div>
            <div class="announcement-content-wrapper">
              <div class="announcement-header">
                <div class="announcement-title">
                  <?= htmlspecialchars($a['title']) ?>
                  <?php if ($a['is_important']): ?><span class="important-badge">Important</span><?php endif; ?>
                  <span class="status-badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
                </div>
                <div class="announcement-actions">
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this announcement? It will be removed from your view but remain visible to others.');">
                    <input type="hidden" name="announcement_id" value="<?= $a['id'] ?>">
                    <button type="submit" name="delete_announcement" class="btn-delete" title="Delete announcement">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </form>
                </div>
              </div>
              <div class="announcement-class">
                <?= $a['subject_code'] ?> - <?= $a['section'] ?> (by <?= $a['teacher_name'] ?>)
              </div>
              <div class="announcement-content"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
              <div class="announcement-time-range">
                Active: <?= date('M d, Y h:i A', strtotime($a['start_date'] . ' ' . $a['start_time'])) ?> 
                — <?= $a['end_date'] ? date('M d, Y h:i A', strtotime($a['end_date'] . ' ' . $a['end_time'])) : 'No End' ?>
              </div>
              <div class="announcement-date">Posted: <?= date('M d, Y', strtotime($a['created_at'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-announcements">
          <p>No announcements found.</p>
          <p>Join classes to receive announcements from your teachers.</p>
        </div>
      <?php endif; ?>
    </section>
  </main>
</div>

<!-- Delete Selected Modal -->
<div id="deleteSelectedModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Announcements</h3>
            <span class="close" onclick="closeModal('deleteSelectedModal')">&times;</span>
        </div>
        <div class="modal-delete-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        <div class="modal-delete-content">
            <h4>Confirm Deletion</h4>
            <p>Are you sure you want to delete <strong id="deleteCount">0</strong> selected announcement(s)? This will remove them from your view but they will remain visible to others.</p>
        </div>
        <form method="POST" id="deleteSelectedForm">
            <div id="selectedAnnouncementsInputs"></div>
            <div class="form-actions">
                <button type="button" onclick="closeModal('deleteSelectedModal')">Cancel</button>
                <button type="submit" name="delete_selected" class="btn-delete">Delete Selected</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete All Modal -->
<div id="deleteAllModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete All Announcements</h3>
            <span class="close" onclick="closeModal('deleteAllModal')">&times;</span>
        </div>
        <div class="modal-delete-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="modal-delete-content">
            <h4>Delete Everything?</h4>
            <p>This will permanently delete all <strong><?php echo count($announcements); ?></strong> announcements from your view. They will remain visible to teachers and other students.</p>
        </div>
        <form method="POST" id="deleteAllForm">
            <?php foreach ($announcements as $a): ?>
                <input type="hidden" name="selected_announcements[]" value="<?= $a['id'] ?>">
            <?php endforeach; ?>
            <div class="form-actions">
                <button type="button" onclick="closeModal('deleteAllModal')">Cancel</button>
                <button type="submit" name="delete_selected" class="btn-delete">Delete All</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterAnnouncements() {
  const filter = document.getElementById('statusFilter').value;
  const announcements = document.querySelectorAll('.announcement-item');
  
  announcements.forEach(announcement => {
    if (filter === 'all' || announcement.getAttribute('data-status') === filter) {
      announcement.style.display = 'flex';
    } else {
      announcement.style.display = 'none';
    }
  });
  updateBulkActions(); // Update bulk actions after filtering
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('input[name="selected_announcements[]"]:checked');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    const selectionCounter = document.getElementById('selectionCounter');
    const deleteCount = document.getElementById('deleteCount');
    
    // Only count visible checkboxes
    const visibleCheckboxes = Array.from(checkboxes).filter(cb => 
        cb.closest('.announcement-item').style.display !== 'none'
    );
    
    const count = visibleCheckboxes.length;
    if (count > 0) {
        selectedCount.textContent = count + ' announcement' + (count > 1 ? 's' : '') + ' selected';
        selectionCounter.textContent = count;
        bulkActions.style.display = 'block';
        
        // Update delete modal count
        if (deleteCount) {
            deleteCount.textContent = count;
        }
        
        // Add selected class to announcement items
        visibleCheckboxes.forEach(checkbox => {
            checkbox.closest('.announcement-item').classList.add('selected');
        });
        
        // Remove selected class from unchecked items
        document.querySelectorAll('input[name="selected_announcements[]"]:not(:checked)').forEach(checkbox => {
            checkbox.closest('.announcement-item').classList.remove('selected');
        });
    } else {
        bulkActions.style.display = 'none';
        // Remove all selected classes
        document.querySelectorAll('.announcement-item').forEach(item => {
            item.classList.remove('selected');
        });
    }
}

function clearSelection() {
    document.querySelectorAll('input[name="selected_announcements[]"]').forEach(cb => {
        cb.checked = false;
        cb.closest('.announcement-item').classList.remove('selected');
    });
    updateBulkActions();
}

function openModal(id) {
  if (id === 'deleteSelectedModal') {
    const checkboxes = document.querySelectorAll('input[name="selected_announcements[]"]:checked');
    const container = document.getElementById('selectedAnnouncementsInputs');
    container.innerHTML = '';
    
    // Only include visible checkboxes
    const visibleCheckboxes = Array.from(checkboxes).filter(cb => 
        cb.closest('.announcement-item').style.display !== 'none'
    );
    
    visibleCheckboxes.forEach(checkbox => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'selected_announcements[]';
      input.value = checkbox.value;
      container.appendChild(input);
    });
  }
  document.getElementById(id).style.display = 'flex';
}

function closeModal(id) {
  document.getElementById(id).style.display = 'none';
}

// Initialize bulk actions on page load
document.addEventListener('DOMContentLoaded', function() {
  updateBulkActions();
});

window.onclick = function(e) {
  document.querySelectorAll('.modal').forEach(m => {
    if (e.target === m) m.style.display = 'none';
  });
}
</script>
</body>
</html>