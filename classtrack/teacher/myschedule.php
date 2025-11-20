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

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

// Handle form submission for creating schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle announcement creation
    if (isset($_POST['announcement'])) {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $schedule_id = $_POST['schedule_id'] ?? '';
        $is_important = isset($_POST['is_important']) ? 1 : 0;
        $start_date = $_POST['start_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $end_time = $_POST['end_time'] ?? '';

        if (!empty($title) && !empty($content) && !empty($schedule_id)) {
            $table_check = $conn->query("SHOW TABLES LIKE 'announcements'");
            if ($table_check->num_rows > 0) {
                $sql = "INSERT INTO announcements (teacher_id, class_id, title, content, is_important, start_date, start_time, end_date, end_time, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iississss", $teacher_id, $schedule_id, $title, $content, $is_important, $start_date, $start_time, $end_date, $end_time);
                if ($stmt->execute()) {
                    $success = "Announcement created successfully!";
                    header("Location: myschedule.php?success=1");
                    exit;
                } else {
                    $error = "Error creating announcement: " . $conn->error;
                }
            }
        }
    }
    // Handle schedule editing
    else if (isset($_POST['edit_schedule'])) {
        $schedule_id = $_POST['schedule_id'] ?? '';
        $section = $_POST['section'] ?? '';
        $subject_code = $_POST['subject_code'] ?? '';
        $day = $_POST['day'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $type = $_POST['type'] ?? '';
        $room = $_POST['room'] ?? '';
        $color = $_POST['color'] ?? '#e3e9ff';

        // Validate required fields
        if (empty($section) || empty($subject_code) || empty($day) || empty($start_time) || empty($end_time) || empty($type) || empty($room)) {
            $error = "All fields are required!";
        } else {
            // Check if building and campus columns exist before including them
            $sql = "SHOW COLUMNS FROM class_schedules LIKE 'building'";
            $result = $conn->query($sql);
            $has_building = ($result->num_rows > 0);
            
            $sql = "SHOW COLUMNS FROM class_schedules LIKE 'campus'";
            $result = $conn->query($sql);
            $has_campus = ($result->num_rows > 0);

            if ($has_building && $has_campus) {
                $campus = $_POST['campus'] ?? '';
                $building = $_POST['building'] ?? '';
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
                header("Location: myschedule.php?success=1");
                exit;
            } else {
                $error = "Error updating schedule: " . $conn->error;
            }
        }
    }
    // Handle schedule deletion
    else if (isset($_POST['delete_schedule'])) {
        $schedule_id = $_POST['schedule_id'] ?? '';
        
        if (!empty($schedule_id)) {
            $sql = "DELETE FROM class_schedules WHERE id=? AND teacher_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $schedule_id, $teacher_id);
            
            if ($stmt->execute()) {
                $success = "Schedule deleted successfully!";
                header("Location: myschedule.php?success=1");
                exit;
            } else {
                $error = "Error deleting schedule: " . $conn->error;
            }
        }
    }
    // Handle schedule creation (add_schedule)
    else if (isset($_POST['add_schedule'])) {
        $section = $_POST['section'] ?? '';
        $subject_code = $_POST['subject_code'] ?? '';
        $day = $_POST['day'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $type = $_POST['type'] ?? '';
        $room = $_POST['room'] ?? '';
        $campus = $_POST['campus'] ?? '';
        $building = $_POST['building'] ?? '';
        $color = $_POST['color'] ?? '#e3e9ff';

        // Validate required fields
        if (empty($section) || empty($subject_code) || empty($day) || empty($start_time) || empty($end_time) || empty($type) || empty($room)) {
            $error = "All fields are required!";
        } else {
            $unique_code = strtoupper(str_replace(' ', '', $section . '-' . $subject_code));

            // Check if building and campus columns exist before including them
            $sql = "SHOW COLUMNS FROM class_schedules LIKE 'building'";
            $result = $conn->query($sql);
            $has_building = ($result->num_rows > 0);
            
            $sql = "SHOW COLUMNS FROM class_schedules LIKE 'campus'";
            $result = $conn->query($sql);
            $has_campus = ($result->num_rows > 0);

            if ($has_building && $has_campus) {
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
                $success = "Schedule added successfully!";
                header("Location: myschedule.php?success=1");
                exit;
            } else {
                $error = "Error adding schedule: " . $conn->error;
            }
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

// Check if building and campus columns exist for form display
$sql = "SHOW COLUMNS FROM class_schedules LIKE 'building'";
$result = $conn->query($sql);
$has_building = ($result->num_rows > 0);

$sql = "SHOW COLUMNS FROM class_schedules LIKE 'campus'";
$result = $conn->query($sql);
$has_campus = ($result->num_rows > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Schedule</title>
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
    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
    <li class="active"><a href="myschedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
    <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    <li class="logout-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
  </ul>
</aside>


  <!-- Main Content -->
  <main class="content">
    <header>
      <h2>My Schedule</h2>
      <button class="btn-add" id="openModal"><i class="fas fa-plus"></i> Add Class Schedule</button>
    </header>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert success">
        <i class="fas fa-check-circle"></i> Operation completed successfully!
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

    <!-- View Toggle -->
    <div class="view-toggle">
      <button id="tableViewBtn" class="active"><i class="fas fa-table"></i> Table View</button>
      <button id="weeklyViewBtn"><i class="fas fa-calendar-week"></i> Weekly View</button>
    </div>

    <!-- Table View -->
    <section class="schedule-table" id="tableView">
      <table>
        <thead>
          <tr>
            <th>Subject Code</th>
            <th>Section/Year</th>
            <th>Day</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Type</th>
            <th>Room</th>
            <?php if ($has_building): ?><th>Building</th><?php endif; ?>
            <?php if ($has_campus): ?><th>Campus</th><?php endif; ?>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($schedules): ?>
            <?php foreach ($schedules as $c): ?>
              <tr class="schedule-row" data-schedule-id="<?= $c['id'] ?>">
                <td><?= $c['subject_code']; ?></td>
                <td><?= $c['section']; ?></td>
                <td><?= $c['day']; ?></td>
                <td><?= date("H:i", strtotime($c['start_time'])); ?></td>
                <td><?= date("H:i", strtotime($c['end_time'])); ?></td>
                <td><?= $c['type']; ?></td>
                <td><?= $c['room']; ?></td>
                <?php if ($has_building): ?><td><?= $c['building'] ?? ''; ?></td><?php endif; ?>
                <?php if ($has_campus): ?><td><?= $c['campus'] ?? ''; ?></td><?php endif; ?>
                <td>
                  <button class="btn-edit view-details-btn" data-schedule-id="<?= $c['id'] ?>"><i class="fas fa-eye"></i> View</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="<?= ($has_building && $has_campus ? 10 : ($has_building || $has_campus ? 9 : 8)) ?>">No schedules found</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Weekly View -->
    <section class="weekly-view" id="weeklyView" style="display: none;">
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
      <input type="hidden" name="add_schedule" value="1">
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
          <option value="">Select type</option>
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
      </div>
      
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
</script>
<script src="../assets/js/teachermyschedule.js"></script>
</body>
</html>