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

if ($conn->connect_error) {
    die("Database connection error: " . $conn->connect_error);
}

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// Function to check for student schedule conflicts
function checkStudentScheduleConflict($conn, $student_id, $exclude_id = null) {
    $sql = "SELECT cs1.*, cs2.* 
            FROM student_classes sc1
            JOIN class_schedules cs1 ON sc1.class_id = cs1.id
            JOIN student_classes sc2 ON sc1.student_id = sc2.student_id  
            JOIN class_schedules cs2 ON sc2.class_id = cs2.id
            WHERE sc1.student_id = ? 
            AND cs1.day = cs2.day 
            AND cs1.id != cs2.id
            AND ((cs1.start_time < cs2.end_time AND cs1.end_time > cs2.start_time))";
    
    $params = [$student_id];
    
    if ($exclude_id) {
        $sql .= " AND cs1.id != ? AND cs2.id != ?";
        $params[] = $exclude_id;
        $params[] = $exclude_id;
    }
    
    $sql .= " GROUP BY cs1.id, cs2.id";
    
    $stmt = $conn->prepare($sql);
    
    // Create type string based on number of parameters
    $types = str_repeat("i", count($params));
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Handle dismissing conflict notification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['dismiss_conflict_notification'])) {
    $_SESSION['conflict_notification_dismissed'] = true;
    $_SESSION['conflict_notification_dismissed_time'] = time();
    header("Location: student_dashboard.php");
    exit;
}

// Check if notification was dismissed (expires after 24 hours)
$show_conflict_notification = true;
if (isset($_SESSION['conflict_notification_dismissed']) && $_SESSION['conflict_notification_dismissed']) {
    $dismissed_time = $_SESSION['conflict_notification_dismissed_time'] ?? 0;
    $current_time = time();
    $hours_passed = ($current_time - $dismissed_time) / 3600;
    
    // Show notification again after 24 hours
    if ($hours_passed < 24) {
        $show_conflict_notification = false;
    } else {
        // Reset dismissal after 24 hours
        unset($_SESSION['conflict_notification_dismissed']);
        unset($_SESSION['conflict_notification_dismissed_time']);
    }
}

// Handle joining class with code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['join_class'])) {
    $class_code = trim($_POST['class_code']);
    
    if (!empty($class_code)) {
        // Find class by unique code
        $stmt = $conn->prepare("SELECT * FROM class_schedules WHERE unique_code = ?");
        $stmt->bind_param("s", $class_code);
        $stmt->execute();
        $class_result = $stmt->get_result();
        
        if ($class_result->num_rows > 0) {
            $class = $class_result->fetch_assoc();
            $class_id = $class['id'];
            
            // Check if student already joined this class
            $check_stmt = $conn->prepare("SELECT * FROM student_classes WHERE student_id = ? AND class_id = ?");
            $check_stmt->bind_param("ii", $student_id, $class_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows == 0) {
                // Check for conflicts before joining
                $temp_conflicts = checkStudentScheduleConflict($conn, $student_id);
                
                // Join the class
                $join_stmt = $conn->prepare("INSERT INTO student_classes (student_id, class_id, joined_at) VALUES (?, ?, NOW())");
                $join_stmt->bind_param("ii", $student_id, $class_id);
                
                if ($join_stmt->execute()) {
                    $success = "Successfully joined class: " . $class['subject_code'] . " - " . $class['section'];
                    
                    // Check for new conflicts after joining
                    $new_conflicts = checkStudentScheduleConflict($conn, $student_id);
                    if (count($new_conflicts) > count($temp_conflicts)) {
                        $warning = "Warning: This class conflicts with your existing schedule!";
                        // Reset dismissal when new conflicts are detected
                        unset($_SESSION['conflict_notification_dismissed']);
                        unset($_SESSION['conflict_notification_dismissed_time']);
                    }
                } else {
                    $error = "Error joining class: " . $conn->error;
                }
                $join_stmt->close();
            } else {
                $error = "You have already joined this class.";
            }
            $check_stmt->close();
        } else {
            $error = "Invalid class code. Please check and try again.";
        }
        $stmt->close();
    } else {
        $error = "Please enter a class code.";
    }
}

// Fetch student's classes
$sql = "SELECT cs.* FROM class_schedules cs 
        JOIN student_classes sc ON cs.id = sc.class_id 
        WHERE sc.student_id = ? 
        ORDER BY FIELD(cs.day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), cs.start_time";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

// Check for schedule conflicts
$schedule_conflicts = checkStudentScheduleConflict($conn, $student_id);

// Create a list of conflicting schedule IDs for easy checking
$conflicting_schedule_ids = [];
foreach ($schedule_conflicts as $conflict) {
    $conflicting_schedule_ids[$conflict['id']] = true;
    if (isset($conflict['id2'])) {
        $conflicting_schedule_ids[$conflict['id2']] = true;
    }
}

// Fetch announcements for student's classes
$announcements = [];
$announcements_sql = "SELECT a.*, cs.subject_code, cs.section, u.username as teacher_name 
                     FROM announcements a 
                     JOIN class_schedules cs ON a.class_id = cs.id 
                     JOIN users u ON a.teacher_id = u.id 
                     WHERE cs.id IN (SELECT class_id FROM student_classes WHERE student_id = ?) 
                     AND a.id NOT IN (SELECT announcement_id FROM student_deleted_announcements WHERE student_id = ?)
                     ORDER BY a.created_at DESC 
                     LIMIT 5";
$announcements_stmt = $conn->prepare($announcements_sql);
$announcements_stmt->bind_param("ii", $student_id, $student_id);
$announcements_stmt->execute();
$announcements_result = $announcements_stmt->get_result();

while ($row = $announcements_result->fetch_assoc()) {
    $announcements[] = $row;
}

$today = date('l');
$todays_classes = array_filter($schedules, function($c) use ($today) { 
    return $c['day'] === $today; 
});
$upcoming_classes = array_filter($schedules, function($c) use ($today) { 
    return $c['day'] !== $today; 
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard</title>
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
    <li class="active"><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
    <li><a href="student_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
    <li><a href="student_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
    <li><a href="student_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    <li class="logout-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="content">
    <header>
      <h2>Student Dashboard</h2>
      <button class="btn-add" id="openJoinModal"><i class="fas fa-plus"></i> Join Class</button>
    </header>

    <?php if (isset($success)): ?>
      <div class="alert success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($warning)): ?>
      <div class="alert error">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $warning; ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
      <div class="alert error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <!-- Schedule Conflict Warning -->
    <?php if (!empty($schedule_conflicts) && $show_conflict_notification): ?>
      <div class="conflict-warning dismissible">
        <div class="conflict-warning-content">
          <i class="fas fa-exclamation-triangle"></i>
          <div class="conflict-warning-text">
            <strong>Schedule Conflict Detected!</strong> You have overlapping classes in your schedule.
            <a href="student_schedule.php" style="margin-left: 10px; color: #d63031; text-decoration: underline;">
              View Schedule
            </a>
          </div>
        </div>
        <form method="POST" action="" class="dismiss-form">
          <input type="hidden" name="dismiss_conflict_notification" value="1">
          <button type="submit" class="dismiss-btn" title="Dismiss for 24 hours">
            <i class="fas fa-times"></i>
          </button>
        </form>
      </div>
    <?php endif; ?>

    <section class="cards">
      <div class="card">
        <h3><i class="fas fa-calendar-day"></i> Today's Classes</h3>
        <?php if ($todays_classes): ?>
          <?php foreach ($todays_classes as $c): ?>
            <?php $hasConflict = isset($conflicting_schedule_ids[$c['id']]); ?>
            <div class="class-box clickable-class <?= $hasConflict ? 'has-conflict' : '' ?>" 
                 style="background-color: <?= $c['color'] ?? '#f0f6ff' ?>; border-left-color: <?= $hasConflict ? '#e74c3c' : ($c['color'] ?? '#1a73e8') ?>;"
                 data-schedule-id="<?= $c['id'] ?>">
              <h4><?= $c['subject_code']; ?>
                <?php if ($hasConflict): ?>
                  <span class="conflict-badge" title="Schedule conflict">
                    <i class="fas fa-exclamation-triangle"></i>
                  </span>
                <?php endif; ?>
              </h4>
              <p><?= $c['section']; ?></p>
              <p><?= date("H:i", strtotime($c['start_time'])) . " - " . date("H:i", strtotime($c['end_time'])); ?> | <?= $c['room']; ?></p>
              <span class="tag"><?= $c['type']; ?></span>
              <?php if (isset($c['building'])): ?><p><small>Building: <?= $c['building']; ?></small></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No classes today</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3><i class="fas fa-calendar-week"></i> Upcoming Classes</h3>
        <?php if ($upcoming_classes): ?>
          <?php foreach ($upcoming_classes as $c): ?>
            <?php $hasConflict = isset($conflicting_schedule_ids[$c['id']]); ?>
            <div class="class-box clickable-class <?= $hasConflict ? 'has-conflict' : '' ?>" 
                 style="background-color: <?= $c['color'] ?? '#f0f6ff' ?>; border-left-color: <?= $hasConflict ? '#e74c3c' : ($c['color'] ?? '#1a73e8') ?>;"
                 data-schedule-id="<?= $c['id'] ?>">
              <h4><?= $c['subject_code']; ?>
                <?php if ($hasConflict): ?>
                  <span class="conflict-badge" title="Schedule conflict">
                    <i class="fas fa-exclamation-triangle"></i>
                  </span>
                <?php endif; ?>
              </h4>
              <p><?= $c['section']; ?></p>
              <p><?= date("H:i", strtotime($c['start_time'])) . " - " . date("H:i", strtotime($c['end_time'])); ?></p>
              <span class="day"><?= $c['day']; ?></span>
              <?php if (isset($c['building'])): ?><p><small>Building: <?= $c['building']; ?></small></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No upcoming classes</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- Recent Announcements -->
    <section class="card">
      <h3><i class="fas fa-bullhorn"></i> Recent Announcements</h3>
      <?php if ($announcements): ?>
        <div class="announcements-list">
          <?php foreach ($announcements as $a): ?>
            <div class="announcement-item <?= $a['is_important'] ? 'important' : '' ?>">
              <div class="announcement-header">
                <div class="announcement-title">
                  <?= htmlspecialchars($a['title']) ?>
                  <?php if ($a['is_important']): ?><span class="important-badge">Important</span><?php endif; ?>
                </div>
                <div class="announcement-date"><?= date('M d, Y', strtotime($a['created_at'])) ?></div>
              </div>
              <div class="announcement-class"><?= $a['subject_code'] ?> - <?= $a['section'] ?> (by <?= $a['teacher_name'] ?>)</div>
              <div class="announcement-content"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
              <div class="announcement-time-range">
                Active: <?= date('M d, Y h:i A', strtotime($a['start_date'] . ' ' . $a['start_time'])) ?> 
                — <?= $a['end_date'] ? date('M d, Y h:i A', strtotime($a['end_date'] . ' ' . $a['end_time'])) : 'No End' ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p>No announcements yet.</p>
      <?php endif; ?>
    </section>
  </main>
</div>

<!-- Join Class Modal -->
<div class="modal" id="joinModal" style="display: none;">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-plus"></i> Join Class</h3>
      <span class="close" id="closeJoinModal">&times;</span>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="join_class" value="1">
      <div class="form-group">
        <label for="class_code">Class Code</label>
        <input type="text" id="class_code" name="class_code" placeholder="Enter class code (e.g., IT3A-WEBSYS)" required>
        <small>Get the class code from your teacher</small>
      </div>
      <div class="form-actions">
        <button type="button" id="closeJoinModalBtn">Cancel</button>
        <button type="submit" class="btn-primary">Join Class</button>
      </div>
    </form>
  </div>
</div>

<!-- Class Details Modal -->
<div class="schedule-details-modal" id="scheduleDetailsModal" style="display: none;">
  <div class="schedule-details-content">
    <div class="modal-header">
      <h3 id="detailsTitle"><i class="fas fa-calendar-alt"></i> Class Details</h3>
      <span class="close" id="closeDetailsModal">&times;</span>
    </div>
    <div id="scheduleDetails" class="centered-schedule-details">
      <!-- Details will be populated by JavaScript -->
    </div>
    <div class="centered-schedule-actions">
      <button class="btn-close" id="closeDetailsBtn"><i class="fas fa-times"></i> Close</button>
    </div>
  </div>
</div>

<script>
// Pass PHP data to JavaScript
window.schedules = <?php echo json_encode($schedules); ?>;
window.scheduleConflicts = <?php echo json_encode($schedule_conflicts); ?>;
window.conflictingScheduleIds = <?php echo json_encode(array_keys($conflicting_schedule_ids)); ?>;

document.addEventListener('DOMContentLoaded', function() {
  const joinModal = document.getElementById('joinModal');
  const openJoinModalBtn = document.getElementById('openJoinModal');
  const closeJoinModalBtn = document.getElementById('closeJoinModal');
  const closeJoinModalBtn2 = document.getElementById('closeJoinModalBtn');

  openJoinModalBtn.addEventListener('click', function() {
    joinModal.style.display = 'flex';
  });

  closeJoinModalBtn.addEventListener('click', function() {
    joinModal.style.display = 'none';
  });

  closeJoinModalBtn2.addEventListener('click', function() {
    joinModal.style.display = 'none';
  });

  window.addEventListener('click', function(event) {
    if (event.target === joinModal) {
      joinModal.style.display = 'none';
    }
  });

  // Class details functionality
  const detailsModal = document.getElementById('scheduleDetailsModal');
  const closeDetailsBtn = document.getElementById('closeDetailsModal');
  const closeDetailsBtn2 = document.getElementById('closeDetailsBtn');
  
  // Close modal when clicking close button
  closeDetailsBtn.addEventListener('click', function() {
    detailsModal.style.display = 'none';
  });
  
  closeDetailsBtn2.addEventListener('click', function() {
    detailsModal.style.display = 'none';
  });
  
  // Close modal when clicking outside
  window.addEventListener('click', function(event) {
    if (event.target === detailsModal) {
      detailsModal.style.display = 'none';
    }
  });
  
  // Add click handlers to class boxes
  const classBoxes = document.querySelectorAll('.clickable-class');
  classBoxes.forEach(box => {
    box.style.cursor = 'pointer';
    box.addEventListener('click', function() {
      const scheduleId = this.getAttribute('data-schedule-id');
      const schedule = window.schedules.find(s => s.id == scheduleId);
      
      if (schedule) {
        openClassDetails(schedule);
      }
    });
  });

  // Function to open class details
  function openClassDetails(schedule) {
    const detailsContainer = document.getElementById('scheduleDetails');
    const hasConflict = window.conflictingScheduleIds.includes(parseInt(schedule.id));
    
    // Format the details HTML
    detailsContainer.innerHTML = `
      <h3>${schedule.subject_code}</h3>
      ${hasConflict ? `
        <div class="overlap-warning-details">
          <i class="fas fa-exclamation-triangle"></i>
          <span>This class conflicts with your schedule</span>
        </div>
      ` : ''}
      <table class="details-table">
        <tr>
          <th>Section/Year</th>
          <td>${schedule.section}</td>
        </tr>
        <tr>
          <th>Day</th>
          <td>${schedule.day}</td>
        </tr>
        <tr>
          <th>Time</th>
          <td>${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}</td>
        </tr>
        <tr>
          <th>Type</th>
          <td>${schedule.type}</td>
        </tr>
        <tr>
          <th>Room</th>
          <td>${schedule.room}</td>
        </tr>
        ${schedule.building ? `<tr><th>Building</th><td>${schedule.building}</td></tr>` : ''}
        ${schedule.campus ? `<tr><th>Campus</th><td>${schedule.campus}</td></tr>` : ''}
      </table>
    `;
    
    detailsModal.style.display = 'flex';
  }

  // Helper function to format time
  function formatTime(timeString) {
    const time = new Date('1970-01-01T' + timeString + 'Z');
    return time.toLocaleTimeString('en-US', { 
      hour: 'numeric', 
      minute: '2-digit',
      hour12: true 
    });
  }

  // Show conflict details if there are conflicts
  if (window.scheduleConflicts && window.scheduleConflicts.length > 0) {
    console.log('Schedule conflicts detected:', window.scheduleConflicts);
  }

  // Add animation for dismiss button
  const dismissButtons = document.querySelectorAll('.dismiss-btn');
  dismissButtons.forEach(btn => {
    btn.addEventListener('mouseenter', function() {
      this.style.transform = 'scale(1.1)';
    });
    btn.addEventListener('mouseleave', function() {
      this.style.transform = 'scale(1)';
    });
  });
});
</script>
</body>
</html>