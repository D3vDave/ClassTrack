<?php
session_start();

// Include database connection with error handling
$db_path = '../db.php';
if (!file_exists($db_path)) {
    die("Database configuration file not found: " . $db_path);
}

include $db_path;

// Check if connection was established
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check your database configuration.");
}

if ($conn->connect_error) {
    die("Database connection error: " . $conn->connect_error);
}

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

// Function to generate unique code
function generateUniqueCode($section, $subject_code, $conn) {
    // Create base code from section and subject (e.g., IT3A-WEBSYS)
    $base_code = strtoupper(str_replace(' ', '', $section) . '-' . str_replace(' ', '', $subject_code));
    
    // Check if this code already exists, if so, add random numbers
    $code = $base_code;
    $counter = 1;
    
    while (true) {
        $stmt = $conn->prepare("SELECT id FROM class_schedules WHERE unique_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            break; // Code is unique
        }
        
        // If code exists, add random numbers
        $code = $base_code . '-' . rand(100, 999);
        $counter++;
        
        // Safety check to prevent infinite loop
        if ($counter > 10) {
            $code = $base_code . '-' . uniqid();
            break;
        }
    }
    
    return $code;
}

// Handle form submission for creating schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['announcement'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $schedule_id = $_POST['schedule_id'];
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $end_date = $_POST['end_date'];
    $end_time = $_POST['end_time'];

    $table_check = $conn->query("SHOW TABLES LIKE 'announcements'");
    if ($table_check->num_rows > 0) {
        $sql = "INSERT INTO announcements (teacher_id, class_id, title, content, is_important, start_date, start_time, end_date, end_time, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iississss", $teacher_id, $schedule_id, $title, $content, $is_important, $start_date, $start_time, $end_date, $end_time);
        
        if ($stmt->execute()) {
            $success = "Announcement created successfully!";
                $success = "Announcement created successfully!";
            } else {
                $error = "Error creating announcement: " . $conn->error;
            }
        } else {
            $error = "Announcements feature is not available. Please contact administrator.";
        }
    } else if (isset($_POST['edit_schedule'])) {
        // Handle schedule editing
        $schedule_id = $_POST['schedule_id'];
        $section = $_POST['section'];
        $subject_code = $_POST['subject_code'];
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $type = $_POST['type'];
        $room = $_POST['room'];
        $color = $_POST['color'] ?? '#e3e9ff';

        // Check if building and campus columns exist
        $check_building = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'building'");
        $has_building = ($check_building->num_rows > 0);
        
        $check_campus = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'campus'");
        $has_campus = ($check_campus->num_rows > 0);

        if ($has_building && $has_campus) {
            $campus = $_POST['campus'];
            $building = $_POST['building'];
            $sql = "UPDATE class_schedules SET section=?, subject_code=?, day=?, start_time=?, end_time=?, type=?, campus=?, building=?, room=?, color=? WHERE id=? AND teacher_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssii", $section, $subject_code, $day, $start_time, $end_time, $type, $campus, $building, $room, $color, $schedule_id, $teacher_id);
        } else {
            // Fallback without building and campus
            $sql = "UPDATE class_schedules SET section=?, subject_code=?, day=?, start_time=?, end_time=?, type=?, room=?, color=? WHERE id=? AND teacher_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssii", $section, $subject_code, $day, $start_time, $end_time, $type, $room, $color, $schedule_id, $teacher_id);
        }
        
        if ($stmt->execute()) {
            $success = "Schedule updated successfully!";
            header("Location: dashboard.php?success=1");
            exit;
        } else {
            $error = "Error updating schedule: " . $conn->error;
        }
    } else if (isset($_POST['delete_schedule'])) {
        // Handle schedule deletion
        $schedule_id = $_POST['schedule_id'];
        
        $sql = "DELETE FROM class_schedules WHERE id=? AND teacher_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $schedule_id, $teacher_id);
        
        if ($stmt->execute()) {
            $success = "Schedule deleted successfully!";
            header("Location: dashboard.php?success=1");
            exit;
        } else {
            $error = "Error deleting schedule: " . $conn->error;
        }
    } else {
        // Handle schedule creation - ADD UNIQUE CODE GENERATION
        $section = $_POST['section'];
        $subject_code = $_POST['subject_code'];
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $type = $_POST['type'];
        $room = $_POST['room'];
        $color = $_POST['color'] ?? '#e3e9ff';

        // Generate unique code
        $unique_code = generateUniqueCode($section, $subject_code, $conn);

        // Check if building and campus columns exist
        $check_building = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'building'");
        $has_building = ($check_building->num_rows > 0);
        
        $check_campus = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'campus'");
        $has_campus = ($check_campus->num_rows > 0);

        if ($has_building && $has_campus) {
            $campus = $_POST['campus'];
            $building = $_POST['building'];
            $sql = "INSERT INTO class_schedules (teacher_id, section, subject_code, day, start_time, end_time, type, campus, building, room, color, unique_code) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssssssss", $teacher_id, $section, $subject_code, $day, $start_time, $end_time, $type, $campus, $building, $room, $color, $unique_code);
        } else {
            // Fallback without building and campus
            $sql = "INSERT INTO class_schedules (teacher_id, section, subject_code, day, start_time, end_time, type, room, color, unique_code) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssssss", $teacher_id, $section, $subject_code, $day, $start_time, $end_time, $type, $room, $color, $unique_code);
        }
        
        if ($stmt->execute()) {
            $success = "Schedule added successfully! Unique Code: <strong>$unique_code</strong>";
            header("Location: dashboard.php?success=1&code=" . urlencode($unique_code));
            exit;
        } else {
            $error = "Error adding schedule: " . $conn->error;
        }
    }
}

// Fetch schedules
$sql = "SELECT * FROM class_schedules WHERE teacher_id = ? 
        ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

// Check if announcements table exists
$announcements = [];
$table_check = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($table_check->num_rows > 0) {
    // Fetch announcements
    $announcements_sql = "SELECT a.*, cs.subject_code, cs.section 
                         FROM announcements a 
                         LEFT JOIN class_schedules cs ON a.class_id = cs.id 
                         WHERE a.teacher_id = ? 
                         ORDER BY a.created_at DESC 
                         LIMIT 5";
    $announcements_stmt = $conn->prepare($announcements_sql);
    $announcements_stmt->bind_param("i", $teacher_id);
    $announcements_stmt->execute();
    $announcements_result = $announcements_stmt->get_result();
    
    while ($row = $announcements_result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

$today = date('l');
$todays_classes = array_filter($schedules, function($c) use ($today) { 
    return $c['day'] === $today; 
});
$upcoming_classes = array_filter($schedules, function($c) use ($today) { 
    return $c['day'] !== $today; 
});


// Check if building and campus columns exist for form display
$check_building = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'building'");
$has_building = ($check_building->num_rows > 0);

$check_campus = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'campus'");
$has_campus = ($check_campus->num_rows > 0);

// Check if there's a success message with code
if (isset($_GET['success']) && isset($_GET['code'])) {
    $success = "Schedule added successfully! Unique Code: <strong>" . htmlspecialchars($_GET['code']) . "</strong>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="dashboard">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="profile-simple">
      <h3>Welcome, <?php echo $_SESSION['username']; ?></h3>
      <span class="role">Teacher</span>
    </div>
    <ul class="menu">
      <li class="active"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="myschedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
      <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
      <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
      <li class="logout-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="content">
    <header>
      <h2>Teacher Dashboard</h2>
      <button class="btn-add" id="openModal"><i class="fas fa-plus"></i> Add Class Schedule</button>
    </header>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert success">
        <i class="fas fa-check-circle"></i> Operation completed successfully!
        <?php if (isset($_GET['code'])): ?>
          <br><small>Unique Code: <strong><?php echo htmlspecialchars($_GET['code']); ?></strong></small>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    
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

    <section class="cards">
      <div class="card">
        <h3><i class="fas fa-calendar-day"></i> Today's Classes</h3>
        <?php if ($todays_classes): ?>
          <?php foreach ($todays_classes as $c): ?>
            <div class="class-box" style="background-color: <?= $c['color'] ?? '#f0f6ff' ?>; border-left-color: <?= $c['color'] ?? '#1a73e8' ?>;" data-schedule-id="<?= $c['id'] ?>">
              <h4><?= $c['subject_code']; ?></h4>
              <p><?= $c['section']; ?></p>
              <p><?= date("H:i", strtotime($c['start_time'])) . " - " . date("H:i", strtotime($c['end_time'])); ?> | <?= $c['room']; ?></p>
              <span class="tag"><?= $c['type']; ?></span>
              <?php if (isset($c['unique_code'])): ?>
                <div class="unique-code">
                  <i class="fas fa-key"></i> Code: <?= $c['unique_code']; ?>
                </div>
              <?php endif; ?>
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
            <div class="class-box" style="background-color: <?= $c['color'] ?? '#f0f6ff' ?>; border-left-color: <?= $c['color'] ?? '#1a73e8' ?>;" data-schedule-id="<?= $c['id'] ?>">
              <h4><?= $c['subject_code']; ?></h4>
              <p><?= $c['section']; ?></p>
              <p><?= date("H:i", strtotime($c['start_time'])) . " - " . date("H:i", strtotime($c['end_time'])); ?></p>
              <span class="day"><?= $c['day']; ?></span>
              <?php if (isset($c['unique_code'])): ?>
                <div class="unique-code">
                  <i class="fas fa-key"></i> Code: <?= $c['unique_code']; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No upcoming classes</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- section to display all unique codes -->
    <section class="card">
      <h3><i class="fas fa-key"></i> Class Join Codes</h3>
      <?php if ($schedules): ?>
        <div class="codes-list">
          <?php foreach ($schedules as $schedule): ?>
            <?php if (isset($schedule['unique_code'])): ?>
              <div class="code-item">
                <div class="code-info">
                  <strong><?= $schedule['subject_code']; ?> - <?= $schedule['section']; ?></strong>
                  <span class="code"><?= $schedule['unique_code']; ?></span>
                </div>
                <div class="code-actions">
                  <button class="btn-copy" data-code="<?= $schedule['unique_code']; ?>">
                    <i class="fas fa-copy"></i> Copy
                  </button>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p>No classes created yet. Add a class to generate join codes.</p>
      <?php endif; ?>
    </section>

    <!-- Mini Weekly View -->
    <section class="mini-weekly-view">
      <h3><i class="fas fa-calendar-alt"></i> Weekly Overview</h3>
      <div class="week-days-grid">
        <div class="day-header">Monday</div>
        <div class="day-header">Tuesday</div>
        <div class="day-header">Wednesday</div>
        <div class="day-header">Thursday</div>
        <div class="day-header">Friday</div>
        <div class="day-header">Saturday</div>
        <div class="day-header">Sunday</div>
        
        <div class="day-cell" data-day="0"></div>
        <div class="day-cell" data-day="1"></div>
        <div class="day-cell" data-day="2"></div>
        <div class="day-cell" data-day="3"></div>
        <div class="day-cell" data-day="4"></div>
        <div class="day-cell" data-day="5"></div>
        <div class="day-cell" data-day="6"></div>
      </div>
    </section>
  </main>
</div>

<!-- Schedule Modal -->
<div class="modal" id="scheduleModal" style="display: none;">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-plus"></i> Add New Class Schedule</h3>
      <span class="close" id="closeModal">&times;</span>
    </div>
    <form method="POST" action="">
      <div class="form-group">
        <label for="section">Section/Year</label>
        <input type="text" id="section" name="section" placeholder="e.g., IT-3A" required>
      </div>
      <div class="form-group">
        <label for="subject_code">Subject Code</label>
        <input type="text" id="subject_code" name="subject_code" placeholder="e.g., WEBSYS" required>
      </div>
      <div class="form-group">
        <label for="day">Day</label>
        <select id="day" name="day" required>
          <option value="">Select day</option>
          <option>Monday</option>
          <option>Tuesday</option>
          <option>Wednesday</option>
          <option>Thursday</option>
          <option>Friday</option>
          <option>Saturday</option>
          <option>Sunday</option>
        </select>
      </div>
      <div class="form-group">
        <label for="start_time">Start Time</label>
        <input type="time" id="start_time" name="start_time" required>
      </div>
      <div class="form-group">
        <label for="end_time">End Time</label>
        <input type="time" id="end_time" name="end_time" required>
      </div>
      <div class="form-group">
        <label for="type">Class Type</label>
        <select id="type" name="type" required>
          <option>Lecture</option>
          <option>Lab</option>
        </select>
      </div>
      <?php if ($has_campus): ?>
      <div class="form-group">
        <label for="campus">Campus</label>
        <input type="text" id="campus" name="campus" placeholder="e.g., Main Campus" required>
      </div>
      <?php endif; ?>
      <?php if ($has_building): ?>
      <div class="form-group">
        <label for="building">Building</label>
        <input type="text" id="building" name="building" placeholder="e.g., Building E" required>
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label for="room">Room</label>
        <input type="text" id="room" name="room" placeholder="e.g., E14" required>
      </div>
      <div class="form-group">
        <label for="color">Schedule Color</label>
        <div class="color-options">
          <div class="color-option selected" data-color="#e3e9ff" style="background-color: #e3e9ff;" title="Light Blue"></div>
          <div class="color-option" data-color="#e0f7e9" style="background-color: #e0f7e9;" title="Light Green"></div>
          <div class="color-option" data-color="#fff2e0" style="background-color: #fff2e0;" title="Light Orange"></div>
          <div class="color-option" data-color="#ffe6e6" style="background-color: #ffe6e6;" title="Light Pink"></div>
          <div class="color-option" data-color="#f0e6ff" style="background-color: #f0e6ff;" title="Light Purple"></div>
          <div class="color-option" data-color="#e6f7ff" style="background-color: #e6f7ff;" title="Light Cyan"></div>
        </div>
        <input type="hidden" id="customColor" name="color" value="#e3e9ff">
      </div>
      <div class="form-actions">
        <button type="button" id="closeModalBtn">Cancel</button>
        <button type="submit" class="btn-primary">Add Class</button>
      </div>
    </form>
  </div>
</div>

<!-- Schedule Details Modal -->
<div class="schedule-details-modal" id="scheduleDetailsModal">
  <div class="schedule-details-content">
    <div class="modal-header">
      <h3 id="detailsTitle"><i class="fas fa-calendar-alt"></i> Class Details</h3>
      <span class="close" id="closeDetailsModal">&times;</span>
    </div>
    <div id="scheduleDetails" class="centered-schedule-details">
      <!-- Details will be populated by JavaScript -->
    </div>
    <div class="centered-schedule-actions">
      <button class="btn-edit" id="editSchedule"><i class="fas fa-edit"></i> Edit</button>
      <button class="btn-delete" id="deleteSchedule"><i class="fas fa-trash"></i> Delete</button>
      <button class="btn-announcement" id="createAnnouncement"><i class="fas fa-bullhorn"></i> Make Announcement</button>
      <button class="btn-copy-code" id="copyClassCode" onclick="copyJoinCode()"><i class="fas fa-copy"></i> Copy Join Code</button>
      <button class="btn-close" id="closeDetailsBtn"><i class="fas fa-times"></i> Close</button>
    </div>
    
    <!-- Edit Form (initially hidden) -->
    <div id="editFormContainer" class="edit-form-container">
      <h4><i class="fas fa-edit"></i> Edit Schedule</h4>
      <form method="POST" action="" id="editScheduleForm">
        <input type="hidden" name="edit_schedule" value="1">
        <input type="hidden" name="schedule_id" id="editScheduleId">
        
        <div class="form-group">
          <label for="edit_section">Section/Year</label>
          <input type="text" id="edit_section" name="section" required>
        </div>
        <div class="form-group">
          <label for="edit_subject_code">Subject Code</label>
          <input type="text" id="edit_subject_code" name="subject_code" required>
        </div>
        <div class="form-group">
          <label for="edit_day">Day</label>
          <select id="edit_day" name="day" required>
            <option>Monday</option>
            <option>Tuesday</option>
            <option>Wednesday</option>
            <option>Thursday</option>
            <option>Friday</option>
            <option>Saturday</option>
            <option>Sunday</option>
          </select>
        </div>
        <div class="form-group">
          <label for="edit_start_time">Start Time</label>
          <input type="time" id="edit_start_time" name="start_time" required>
        </div>
        <div class="form-group">
          <label for="edit_end_time">End Time</label>
          <input type="time" id="edit_end_time" name="end_time" required>
        </div>
        <div class="form-group">
          <label for="edit_type">Class Type</label>
          <select id="edit_type" name="type" required>
            <option>Lecture</option>
            <option>Lab</option>
          </select>
        </div>
        <?php if ($has_campus): ?>
        <div class="form-group">
          <label for="edit_campus">Campus</label>
          <input type="text" id="edit_campus" name="campus" required>
        </div>
        <?php endif; ?>
        <?php if ($has_building): ?>
        <div class="form-group">
          <label for="edit_building">Building</label>
          <input type="text" id="edit_building" name="building" required>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label for="edit_room">Room</label>
          <input type="text" id="edit_room" name="room" required>
        </div>
        <div class="form-group">
          <label for="edit_color">Schedule Color</label>
          <div class="color-options edit-color-options">
            <div class="color-option edit-color-option selected" data-color="#e3e9ff" style="background-color: #e3e9ff;" title="Light Blue"></div>
            <div class="color-option edit-color-option" data-color="#e0f7e9" style="background-color: #e0f7e9;" title="Light Green"></div>
            <div class="color-option edit-color-option" data-color="#fff2e0" style="background-color: #fff2e0;" title="Light Orange"></div>
            <div class="color-option edit-color-option" data-color="#ffe6e6" style="background-color: #ffe6e6;" title="Light Pink"></div>
            <div class="color-option edit-color-option" data-color="#f0e6ff" style="background-color: #f0e6ff;" title="Light Purple"></div>
            <div class="color-option edit-color-option" data-color="#e6f7ff" style="background-color: #e6f7ff;" title="Light Cyan"></div>
          </div>
          <input type="hidden" id="edit_color" name="color" value="#e3e9ff">
        </div>
        <div class="form-actions">
          <button type="button" id="cancelEditBtn">Cancel</button>
          <button type="submit" class="btn-primary">Update Schedule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Announcement Modal -->
<div class="modal" id="announcementModal" style="display: none;">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-bullhorn"></i> Create Announcement</h3>
      <span class="close" id="closeAnnouncementModal">&times;</span>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="schedule_id" id="announcementScheduleId">
      <input type="hidden" name="announcement" value="1">     
      <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
      <div class="form-group"><label>Content</label><textarea name="content" required></textarea></div>
      <div class="form-group"><label>Start Date</label><input type="date" name="start_date" required></div>
      <div class="form-group"><label>Start Time</label><input type="time" name="start_time" required></div>
      <div class="form-group"><label>End Date</label><input type="date" name="end_date"></div>
      <div class="form-group"><label>End Time</label><input type="time" name="end_time"></div>
      <div class="toggle-important">
        <input type="checkbox" name="is_important" id="important-toggle">
        <span>Mark as Important</span>
      
      <div class="form-actions">
        <button type="button" id="cancelAnnouncement">Cancel</button>
        <button type="submit">Create Announcement</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal" style="display: none;">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
      <span class="close" id="closeDeleteModal">&times;</span>
    </div>
    <form method="POST" action="" id="deleteForm">
      <input type="hidden" name="delete_schedule" value="1">
      <input type="hidden" name="schedule_id" id="deleteScheduleId">
      
      <p>Are you sure you want to delete this schedule? This action cannot be undone.</p>
      
      <div class="form-actions">
        <button type="button" id="cancelDeleteBtn">Cancel</button>
        <button type="submit" class="btn-delete">Delete Schedule</button>
      </div>
    </form>
  </div>
</div>

<script>
// Pass PHP data to JavaScript
window.schedules = <?php echo json_encode($schedules); ?>;
window.dayMap = {Monday: 0, Tuesday: 1, Wednesday: 2, Thursday: 3, Friday: 4, Saturday: 5, Sunday: 6};

// Copy code functionality
document.addEventListener('DOMContentLoaded', function() {
    // Copy buttons in the codes list
    document.querySelectorAll('.btn-copy').forEach(button => {
        button.addEventListener('click', function() {
            const code = this.getAttribute('data-code');
            navigator.clipboard.writeText(code).then(() => {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    this.innerHTML = originalText;
                }, 2000);
            });
        });
    });

    // Copy code button in schedule details
    document.getElementById('copyClassCode')?.addEventListener('click', function() {
        const scheduleId = document.getElementById('editScheduleId')?.value;
        if (scheduleId) {
            const schedule = window.schedules.find(s => s.id == scheduleId);
            if (schedule && schedule.unique_code) {
                navigator.clipboard.writeText(schedule.unique_code).then(() => {
                    const button = this;
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    setTimeout(() => {
                        button.innerHTML = originalText;
                    }, 2000);
                });
            }
        }
    });
});

// Function to copy the unique class code from the modal
function copyJoinCode() {
    // Find the code text inside your modal
    const codeElement = document.querySelector('.unique-code');
    if (!codeElement) {
        alert("No join code found.");
        return;
    }

    const codeText = codeElement.textContent.trim();

    // Use Clipboard API if available
    navigator.clipboard.writeText(codeText)
        .then(() => {
            // Optional feedback (use alert or console/log)
            alert("Copied Join Code: " + codeText);
        })
        .catch(() => {
            // Fallback method if Clipboard API fails
            const tempInput = document.createElement("input");
            tempInput.value = codeText;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            alert("Copied Join Code: " + codeText);
        });
}
</script>

<script src="../assets/js/teacherdashboard.js"></script>
</body>
</html>