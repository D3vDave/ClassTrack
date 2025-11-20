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

// Check if building and campus columns exist
$has_building = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'building'")->num_rows > 0;
$has_campus = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'campus'")->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Schedule - Student</title>
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
    <li class="active"><a href="student_schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
    <li><a href="student_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
    <li><a href="student_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    <li class="logout-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="content">
    <header>
      <h2>My Schedule</h2>
      <button class="btn-add" onclick="window.location.href='student_dashboard.php'">
        <i class="fas fa-plus"></i> Join More Classes
      </button>
    </header>

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
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="<?= ($has_building && $has_campus ? 8 : ($has_building || $has_campus ? 7 : 6)) ?>">
                No classes joined yet. <a href="student_dashboard.php">Join a class</a> to see your schedule.
              </td>
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

<!-- Schedule Details Modal -->
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
window.dayMap = {Monday: 0, Tuesday: 1, Wednesday: 2, Thursday: 3, Friday: 4, Saturday: 5, Sunday: 6};

// View toggle functionality
document.addEventListener('DOMContentLoaded', function() {
  const tableViewBtn = document.getElementById('tableViewBtn');
  const weeklyViewBtn = document.getElementById('weeklyViewBtn');
  const tableView = document.getElementById('tableView');
  const weeklyView = document.getElementById('weeklyView');

  tableViewBtn.addEventListener('click', function() {
    tableView.style.display = 'block';
    weeklyView.style.display = 'none';
    tableViewBtn.classList.add('active');
    weeklyViewBtn.classList.remove('active');
  });

  weeklyViewBtn.addEventListener('click', function() {
    tableView.style.display = 'none';
    weeklyView.style.display = 'block';
    weeklyViewBtn.classList.add('active');
    tableViewBtn.classList.remove('active');
    renderWeeklyView();
  });

  function renderWeeklyView() {
    const dayCells = document.querySelectorAll('.day-cell');
    dayCells.forEach(cell => cell.innerHTML = '');

    window.schedules.forEach(schedule => {
      const dayIndex = window.dayMap[schedule.day];
      if (dayIndex !== undefined) {
        const dayCell = dayCells[dayIndex];
        const scheduleItem = document.createElement('div');
        scheduleItem.className = 'schedule-item';
        scheduleItem.style.backgroundColor = schedule.color || '#e3e9ff';
        scheduleItem.style.borderLeftColor = schedule.color || '#1a73e8';
        
        scheduleItem.innerHTML = `
          <h4>${schedule.subject_code}</h4>
          <p>${schedule.section}</p>
          <p>${schedule.start_time.substring(0, 5)} - ${schedule.end_time.substring(0, 5)}</p>
          <p>${schedule.room}</p>
          <span class="tag">${schedule.type}</span>
        `;
        
        dayCell.appendChild(scheduleItem);
      }
    });
  }

  // Initial render if weekly view is active
  if (weeklyView.style.display !== 'none') {
    renderWeeklyView();
  }

  // Schedule details functionality
  const modal = document.getElementById('scheduleDetailsModal');
  const closeBtn = document.getElementById('closeDetailsModal');
  const closeDetailsBtn = document.getElementById('closeDetailsBtn');
  
  // Close modal when clicking close button
  closeBtn.addEventListener('click', function() {
    modal.style.display = 'none';
  });
  
  closeDetailsBtn.addEventListener('click', function() {
    modal.style.display = 'none';
  });
  
  // Close modal when clicking outside
  window.addEventListener('click', function(event) {
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  });
  
  // Add click handlers to table rows
  const tableRows = document.querySelectorAll('#tableView tbody tr.schedule-row');
  tableRows.forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', function() {
      const scheduleId = this.getAttribute('data-schedule-id');
      const schedule = window.schedules.find(s => s.id == scheduleId);
      
      if (schedule) {
        openScheduleDetails(schedule);
      }
    });
  });
  
  // Add click handlers to weekly view schedule items
  document.addEventListener('click', function(e) {
    if (e.target.closest('.schedule-item')) {
      const scheduleItem = e.target.closest('.schedule-item');
      const subjectCode = scheduleItem.querySelector('h4').textContent;
      
      // Find the corresponding schedule data
      const schedule = window.schedules.find(s => s.subject_code === subjectCode);
      if (schedule) {
        openScheduleDetails(schedule);
      }
    }
  });

  // Function to open schedule details
  function openScheduleDetails(schedule) {
    const detailsContainer = document.getElementById('scheduleDetails');
    
    // Format the details HTML
    detailsContainer.innerHTML = `
      <h3>${schedule.subject_code}</h3>
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
    
    modal.style.display = 'flex';
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
});
</script>
</body>
</html>