<?php
session_start();
include '../db.php';

// Check teacher session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$toast = "";

/* ============================
   FETCH TEACHER'S CLASSES
============================ */
$classes_stmt = $conn->prepare("
    SELECT id, subject_code, section 
    FROM class_schedules 
    WHERE teacher_id = ?
");
$classes_stmt->bind_param("i", $teacher_id);
$classes_stmt->execute();
$classes = $classes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$classes_stmt->close();

/* ============================
   ADD ANNOUNCEMENT
============================ */
if (isset($_POST['add_announcement'])) {
    $class_id = $_POST['class_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] ?: null;
    $start_time = $_POST['start_time'] ?: '00:00:00';
    $end_time = $_POST['end_time'] ?: '23:59:59';

    if ($class_id && $title && $content) {
        $stmt = $conn->prepare("
            INSERT INTO announcements 
            (teacher_id, class_id, title, content, is_important, start_date, start_time, end_date, end_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iississss", $teacher_id, $class_id, $title, $content, $is_important, $start_date, $start_time, $end_date, $end_time);
        $stmt->execute();
        $stmt->close();
        $toast = "Announcement added successfully!";
    } else $toast = "Please fill all fields.";
}

/* ============================
   EDIT ANNOUNCEMENT
============================ */
if (isset($_POST['edit_announcement'])) {
    $id = $_POST['id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] ?: null;
    $start_time = $_POST['start_time'] ?: '00:00:00';
    $end_time = $_POST['end_time'] ?: '23:59:59';

    $stmt = $conn->prepare("
        UPDATE announcements 
        SET title=?, content=?, is_important=?, start_date=?, start_time=?, end_date=?, end_time=?
        WHERE id=? AND teacher_id=?
    ");
    $stmt->bind_param("ssissssii", $title, $content, $is_important, $start_date, $start_time, $end_date, $end_time, $id, $teacher_id);
    $stmt->execute();
    $stmt->close();
    $toast = "Announcement updated successfully!";
}

/* ============================
   DELETE ANNOUNCEMENT
============================ */
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id=? AND teacher_id=?");
    $stmt->bind_param("ii", $delete_id, $teacher_id);
    $stmt->execute();
    $stmt->close();
    $toast = "Announcement deleted successfully.";
}

/* ============================
   DELETE MULTIPLE ANNOUNCEMENTS
============================ */
if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['selected_announcements'])) {
        $placeholders = implode(',', array_fill(0, count($_POST['selected_announcements']), '?'));
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id IN ($placeholders) AND teacher_id=?");
        
        // Bind parameters
        $types = str_repeat('i', count($_POST['selected_announcements'])) . 'i';
        $params = array_merge($_POST['selected_announcements'], [$teacher_id]);
        $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $stmt->close();
        $toast = count($_POST['selected_announcements']) . " announcement(s) deleted successfully.";
    }
}

/* ============================
   DELETE ALL ANNOUNCEMENTS
============================ */
if (isset($_POST['delete_all'])) {
    $stmt = $conn->prepare("DELETE FROM announcements WHERE teacher_id=?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $stmt->close();
    $toast = "All announcements deleted successfully.";
}

/* ============================
   FETCH ALL ANNOUNCEMENTS (ACTIVE AND EXPIRED)
============================ */
$stmt = $conn->prepare("
    SELECT a.*, cs.subject_code, cs.section
    FROM announcements a
    JOIN class_schedules cs ON a.class_id = cs.id
    WHERE a.teacher_id = ?
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check which announcements are expired
$now = date('Y-m-d H:i:s');
foreach ($announcements as &$announcement) {
    $start_datetime = $announcement['start_date'] . ' ' . $announcement['start_time'];
    $end_datetime = $announcement['end_date'] ? $announcement['end_date'] . ' ' . $announcement['end_time'] : null;
    
    $announcement['is_expired'] = false;
    if ($start_datetime > $now) {
        $announcement['status'] = 'scheduled';
    } elseif ($end_datetime && $end_datetime < $now) {
        $announcement['is_expired'] = true;
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
<title>Announcements | ClassTrack</title>
<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard">
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="profile-simple">
      <h3>Welcome, <?php echo $_SESSION['username']; ?></h3>
      <span class="role">Teacher</span>
    </div>
    <ul class="menu">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="myschedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
      <li class="active"><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
      <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
      <li class="logout-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </div>

<!-- Content -->
<div class="content">
    <header>
        <h2>Announcements</h2>
        <div class="header-actions">
            <button class="btn-add" onclick="openModal('addModal')">
                <i class="fas fa-plus"></i> Add Announcement
            </button>
            <?php if ($announcements): ?>
                <button class="btn-delete-all" onclick="openModal('deleteAllModal')">
                     <i class="fas fa-trash-alt" style="color: white;"></i> Delete All
                </button>
            <?php endif; ?>
        </div>
    </header>

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

    <!-- Announcements List -->
    <section class="announcements-section">
      <?php if ($announcements): ?>
        <?php foreach ($announcements as $a): ?>
          <div class="announcement-item <?= $a['is_important'] ? 'important' : '' ?> <?= $a['is_expired'] ? 'expired' : '' ?>" 
               data-announcement-id="<?= $a['id'] ?>">
            <div class="announcement-checkbox">
              <input type="checkbox" name="selected_announcements[]" value="<?= $a['id'] ?>" onchange="updateBulkActions()">
            </div>
            <div class="announcement-content-wrapper" onclick="openDetailsModal(
              <?= $a['id'] ?>,
              '<?= htmlspecialchars(addslashes($a['title'])) ?>',
              '<?= htmlspecialchars(addslashes($a['content'])) ?>',
              <?= $a['is_important'] ?>,
              '<?= $a['start_date'] ?>',
              '<?= $a['end_date'] ?>',
              '<?= $a['start_time'] ?>',
              '<?= $a['end_time'] ?>',
              '<?= $a['subject_code'] ?>',
              '<?= $a['section'] ?>',
              '<?= $a['created_at'] ?>',
              '<?= $a['status'] ?>'
            )">
              <div class="announcement-header">
                <div class="announcement-title">
                  <?= htmlspecialchars($a['title']) ?>
                  <?php if ($a['is_important']): ?><span class="important-badge">Important</span><?php endif; ?>
                  <span class="status-badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
                </div>
                <div class="announcement-date"><?= date('M d, Y', strtotime($a['created_at'])) ?></div>
              </div>
              <div class="announcement-class"><?= $a['subject_code'] ?> - <?= $a['section'] ?></div>
              <div class="announcement-content"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
              <div class="announcement-time-range">
                Active: <?= date('M d, Y h:i A', strtotime($a['start_date'] . ' ' . $a['start_time'])) ?> 
                — <?= $a['end_date'] ? date('M d, Y h:i A', strtotime($a['end_date'] . ' ' . $a['end_time'])) : 'No End' ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-announcements"><p>No announcements found.</p></div>
      <?php endif; ?>
    </section>
  </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Add Announcement</h3>
      <span class="close" onclick="closeModal('addModal')">&times;</span>
    </div>
    <form method="POST">
      <div class="form-group">
        <label>Class</label>
        <select name="class_id" required>
          <option value="">-- Select Class --</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= $c['subject_code'] ?> - <?= $c['section'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
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
        <button type="button" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" name="add_announcement">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Announcement Details</h3>
      <span class="close" onclick="closeModal('detailsModal')">&times;</span>
    </div>
    <div class="announcement-details">
      <div class="detail-group">
        <label>Title:</label>
        <span id="detail_title"></span>
      </div>
      <div class="detail-group">
        <label>Class:</label>
        <span id="detail_class"></span>
      </div>
      <div class="detail-group">
        <label>Content:</label>
        <div id="detail_content" class="detail-content"></div>
      </div>
      <div class="detail-group">
        <label>Status:</label>
        <span id="detail_status" class="status-badge"></span>
      </div>
      <div class="detail-group">
        <label>Important:</label>
        <span id="detail_important"></span>
      </div>
      <div class="detail-group">
        <label>Start:</label>
        <span id="detail_start"></span>
      </div>
      <div class="detail-group">
        <label>End:</label>
        <span id="detail_end"></span>
      </div>
      <div class="detail-group">
        <label>Created:</label>
        <span id="detail_created"></span>
      </div>
    </div>
    <div class="form-actions">
      <button type="button" onclick="closeModal('detailsModal')">Close</button>
      <button type="button" onclick="openEditFromDetails()">Edit</button>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Edit Announcement</h3>
      <span class="close" onclick="closeModal('editModal')">&times;</span>
    </div>
    <form method="POST">
      <input type="hidden" name="id" id="edit_id">
      <div class="form-group"><label>Title</label><input type="text" name="title" id="edit_title" required></div>
      <div class="form-group"><label>Content</label><textarea name="content" id="edit_content" required></textarea></div>
      <div class="form-group"><label>Start Date</label><input type="date" name="start_date" id="edit_start_date" required></div>
      <div class="form-group"><label>Start Time</label><input type="time" name="start_time" id="edit_start_time" required></div>
      <div class="form-group"><label>End Date</label><input type="date" name="end_date" id="edit_end_date"></div>
      <div class="form-group"><label>End Time</label><input type="time" name="end_time" id="edit_end_time"></div>
      <div class="toggle-important">
        <input type="checkbox" name="is_important" id="edit_important">
        <span>Mark as Important</span>
      </div>
      <div class="form-actions">
        <button type="button" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" name="edit_announcement">Save Changes</button>
        <button type="button" onclick="confirmDelete()" class="btn-delete">Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Confirm Deletion</h3>
      <span class="close" onclick="closeModal('deleteModal')">&times;</span>
    </div>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="delete_id" id="delete_id">
      <p>Are you sure you want to delete this announcement? This action cannot be undone.</p>
      <div class="form-actions">
        <button type="button" onclick="closeModal('deleteModal')">Cancel</button>
        <button type="submit" class="btn-delete">Delete Announcement</button>
      </div>
    </form>
  </div>
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
            <p>Are you sure you want to delete <strong id="deleteCount">0</strong> selected announcement(s)? This action cannot be undone.</p>
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
            <p>This will permanently delete all <strong><?php echo count($announcements); ?></strong> announcements. This action cannot be undone.</p>
        </div>
        <form method="POST">
            <div class="form-actions">
                <button type="button" onclick="closeModal('deleteAllModal')">Cancel</button>
                <button type="submit" name="delete_all" class="btn-delete">Delete All</button>
            </div>
        </form>
    </div>
</div>

<!-- Single Delete Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Announcement</h3>
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
        </div>
        <div class="modal-delete-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        <div class="modal-delete-content">
            <h4>Confirm Deletion</h4>
            <p>Are you sure you want to delete this announcement? This action cannot be undone.</p>
        </div>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_id" id="delete_id">
            <div class="form-actions">
                <button type="button" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn-delete">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast -->
<div id="toast"></div>

<script>
let currentAnnouncementId = null;

function openModal(id){document.getElementById(id).style.display='flex';}
function closeModal(id){document.getElementById(id).style.display='none';}

function openDetailsModal(id, title, content, isImportant, startDate, endDate, startTime, endTime, subject, section, created, status) {
  document.getElementById('detail_title').textContent = title;
  document.getElementById('detail_class').textContent = subject + ' - ' + section;
  document.getElementById('detail_content').innerHTML = content.replace(/\n/g, '<br>');
  document.getElementById('detail_status').textContent = status;
  document.getElementById('detail_status').className = 'status-badge ' + status;
  document.getElementById('detail_important').textContent = isImportant ? 'Yes' : 'No';
  document.getElementById('detail_start').textContent = formatDateTime(startDate, startTime);
  document.getElementById('detail_end').textContent = endDate ? formatDateTime(endDate, endTime) : 'No End';
  document.getElementById('detail_created').textContent = new Date(created).toLocaleString();
  
  currentAnnouncementId = id;
  openModal('detailsModal');
}

function openEditFromDetails() {
  closeModal('detailsModal');
  // Populate edit form with current announcement data
  const announcement = document.querySelector(`[data-announcement-id="${currentAnnouncementId}"]`);
  if (announcement) {
    const title = announcement.querySelector('.announcement-title').textContent.split('Important')[0].trim();
    const content = announcement.querySelector('.announcement-content').textContent;
    
    // You would need to store more data in data attributes to populate all fields
    // For now, we'll just set the ID and open the modal
    document.getElementById('edit_id').value = currentAnnouncementId;
    openModal('editModal');
  }
}

function openEditModal(id, title, content, isImportant, startDate, endDate, startTime, endTime) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_title').value = title;
  document.getElementById('edit_content').value = content;
  document.getElementById('edit_start_date').value = startDate;
  document.getElementById('edit_end_date').value = endDate;
  document.getElementById('edit_start_time').value = startTime;
  document.getElementById('edit_end_time').value = endTime;
  document.getElementById('edit_important').checked = isImportant == 1;
  
  currentAnnouncementId = id;
  openModal('editModal');
}

function confirmDelete() {
  if (currentAnnouncementId) {
    document.getElementById('delete_id').value = currentAnnouncementId;
    closeModal('editModal');
    openModal('deleteModal');
  }
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('input[name="selected_announcements[]"]:checked');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    const selectionCounter = document.getElementById('selectionCounter');
    const deleteCount = document.getElementById('deleteCount');
    
    // Update selection count
    const count = checkboxes.length;
    if (count > 0) {
        selectedCount.textContent = count + ' announcement' + (count > 1 ? 's' : '') + ' selected';
        selectionCounter.textContent = count;
        bulkActions.style.display = 'block';
        
        // Update delete modal count
        if (deleteCount) {
            deleteCount.textContent = count;
        }
        
        // Add selected class to announcement items
        checkboxes.forEach(checkbox => {
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
    
    checkboxes.forEach(checkbox => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'selected_announcements[]';
      input.value = checkbox.value;
      container.appendChild(input);
    });
  }
  document.getElementById(id).style.display = 'flex';
}

function formatDateTime(date, time) {
  const dateObj = new Date(date + 'T' + time);
  return dateObj.toLocaleString();
}

function showToast(msg){
  const t=document.getElementById('toast');
  t.textContent=msg;
  t.className='show';
  setTimeout(()=>t.className=t.className.replace('show',''),3000);
}

<?php if ($toast): ?>showToast("<?= $toast ?>");<?php endif; ?>

window.onclick=function(e){
  document.querySelectorAll('.modal').forEach(m=>{if(e.target===m)m.style.display='none';});
}
</script>
</body>
</html>