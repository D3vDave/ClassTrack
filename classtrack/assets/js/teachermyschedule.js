// teachermyschedule.js - JavaScript for My Schedule page

// Modal functionality
const modal = document.getElementById('scheduleModal');
const detailsModal = document.getElementById('scheduleDetailsModal');
const announcementModal = document.getElementById('announcementModal');
const deleteModal = document.getElementById('deleteModal');
const openModalBtn = document.getElementById('openModal');
const closeModalBtn = document.getElementById('closeModal');
const closeModalBtn2 = document.getElementById('closeModalBtn');
const closeDetailsBtn = document.getElementById('closeDetailsBtn');
const closeDetailsModal = document.getElementById('closeDetailsModal');
const closeAnnouncementModal = document.getElementById('closeAnnouncementModal');
const cancelAnnouncement = document.getElementById('cancelAnnouncement');
const closeDeleteModal = document.getElementById('closeDeleteModal');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
const createAnnouncementBtn = document.getElementById('createAnnouncement');
const editScheduleBtn = document.getElementById('editSchedule');
const deleteScheduleBtn = document.getElementById('deleteSchedule');
const cancelEditBtn = document.getElementById('cancelEditBtn');
const editFormContainer = document.getElementById('editFormContainer');

// View toggle
const tableViewBtn = document.getElementById('tableViewBtn');
const weeklyViewBtn = document.getElementById('weeklyViewBtn');
const tableView = document.getElementById('tableView');
const weeklyView = document.getElementById('weeklyView');

// Open and close modals
if (openModalBtn) openModalBtn.addEventListener('click', () => modal.style.display = 'flex');
if (closeModalBtn) closeModalBtn.addEventListener('click', () => modal.style.display = 'none');
if (closeModalBtn2) closeModalBtn2.addEventListener('click', () => modal.style.display = 'none');
if (closeDetailsBtn) closeDetailsBtn.addEventListener('click', () => detailsModal.style.display = 'none');
if (closeDetailsModal) closeDetailsModal.addEventListener('click', () => detailsModal.style.display = 'none');
if (closeAnnouncementModal) closeAnnouncementModal.addEventListener('click', () => announcementModal.style.display = 'none');
if (cancelAnnouncement) cancelAnnouncement.addEventListener('click', () => announcementModal.style.display = 'none');
if (closeDeleteModal) closeDeleteModal.addEventListener('click', () => deleteModal.style.display = 'none');
if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', () => deleteModal.style.display = 'none');

if (cancelEditBtn) {
  cancelEditBtn.addEventListener('click', () => {
    editFormContainer.classList.remove('active');
    document.querySelectorAll('.centered-schedule-actions button').forEach(btn => {
      btn.style.display = 'inline-block';
    });
  });
}

// Close modals when clicking outside
window.addEventListener('click', (e) => {
  if (e.target === modal) modal.style.display = 'none';
  if (e.target === detailsModal) detailsModal.style.display = 'none';
  if (e.target === announcementModal) announcementModal.style.display = 'none';
  if (e.target === deleteModal) deleteModal.style.display = 'none';
});

// Color selection - Remove hex input and use only predefined colors
document.querySelectorAll('.color-option').forEach(option => {
  option.addEventListener('click', () => {
    document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
    option.classList.add('selected');
    const color = option.getAttribute('data-color');
    document.getElementById('customColor').value = color;
    if (document.getElementById('colorPreview')) {
      document.getElementById('colorPreview').style.backgroundColor = color;
    }
  });
});

// Edit form color selection
document.querySelectorAll('.edit-color-option').forEach(option => {
  option.addEventListener('click', () => {
    document.querySelectorAll('.edit-color-option').forEach(opt => opt.classList.remove('selected'));
    option.classList.add('selected');
    const color = option.getAttribute('data-color');
    document.getElementById('edit_color').value = color;
    if (document.getElementById('editColorPreview')) {
      document.getElementById('editColorPreview').style.backgroundColor = color;
    }
  });
});

// View toggle functionality
if (tableViewBtn && weeklyViewBtn) {
  tableViewBtn.addEventListener('click', () => {
    tableView.style.display = 'block';
    weeklyView.style.display = 'none';
    tableViewBtn.classList.add('active');
    weeklyViewBtn.classList.remove('active');
  });

  weeklyViewBtn.addEventListener('click', () => {
    tableView.style.display = 'none';
    weeklyView.style.display = 'block';
    weeklyViewBtn.classList.add('active');
    tableViewBtn.classList.remove('active');
    populateWeeklyView();
  });
}

// Populate weekly view with start/end times
function populateWeeklyView() {
  const schedules = window.schedules || [];
  const dayMap = {Monday: 0, Tuesday: 1, Wednesday: 2, Thursday: 3, Friday: 4, Saturday: 5, Sunday: 6};
  
  // Clear existing schedule items
  document.querySelectorAll('.schedule-item').forEach(item => item.remove());
  
  // Clear all day cells first
  document.querySelectorAll('.day-cell').forEach(cell => {
    cell.innerHTML = '';
  });
  
  schedules.forEach(schedule => {
    const dayIndex = dayMap[schedule.day];
    if (dayIndex === undefined) return;
    
    const dayCell = document.querySelector(`.day-cell[data-day="${dayIndex}"]`);
    if (dayCell) {
      // Create schedule item
      const scheduleItem = document.createElement('div');
      scheduleItem.className = 'schedule-item';
      scheduleItem.style.backgroundColor = schedule.color || '#e3e9ff';
      scheduleItem.setAttribute('data-schedule-id', schedule.id);
      
      // Format times
      const startTime = new Date(`2000-01-01T${schedule.start_time}`);
      const endTime = new Date(`2000-01-01T${schedule.end_time}`);
      const startFormatted = startTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
      const endFormatted = endTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
      
      // Add content
      scheduleItem.innerHTML = `
        <h4>${schedule.subject_code}</h4>
        <p>${schedule.section}</p>
        <p>${startFormatted} - ${endFormatted}</p>
        <p>${schedule.room}</p>
      `;
      
      // Add click event
      scheduleItem.addEventListener('click', () => {
        showScheduleDetails(schedule);
      });
      
      dayCell.appendChild(scheduleItem);
    }
  });
}

// Show schedule details
function showScheduleDetails(schedule) {
  const detailsContainer = document.getElementById('scheduleDetails');
  if (!detailsContainer) return;
  
  // Format time for display
  const startTime = schedule.start_time.substring(0, 5);
  const endTime = schedule.end_time.substring(0, 5);
  
  detailsContainer.innerHTML = `
    <h3>${schedule.subject_code}</h3>
    <table class="details-table">
      <tr>
        <th>Section</th>
        <td>${schedule.section}</td>
      </tr>
      <tr>
        <th>Day</th>
        <td>${schedule.day}</td>
      </tr>
      <tr>
        <th>Start Time</th>
        <td>${startTime}</td>
      </tr>
      <tr>
        <th>End Time</th>
        <td>${endTime}</td>
      </tr>
      <tr>
        <th>Type</th>
        <td>${schedule.type}</td>
      </tr>
      <tr>
        <th>Room</th>
        <td>${schedule.room}</td>
      </tr>
      ${schedule.building ? `
      <tr>
        <th>Building</th>
        <td>${schedule.building}</td>
      </tr>
      ` : ''}
      ${schedule.campus ? `
      <tr>
        <th>Campus</th>
        <td>${schedule.campus}</td>
      </tr>
      ` : ''}
    </table>
  `;
  
  // Set up buttons
  if (editScheduleBtn) {
    editScheduleBtn.onclick = () => {
      // Hide action buttons
      document.querySelectorAll('.centered-schedule-actions button').forEach(btn => {
        btn.style.display = 'none';
      });
      
      // Show edit form
      editFormContainer.classList.add('active');
      
      // Populate edit form
      document.getElementById('editScheduleId').value = schedule.id;
      document.getElementById('edit_section').value = schedule.section;
      document.getElementById('edit_subject_code').value = schedule.subject_code;
      document.getElementById('edit_day').value = schedule.day;
      document.getElementById('edit_start_time').value = schedule.start_time;
      document.getElementById('edit_end_time').value = schedule.end_time;
      document.getElementById('edit_type').value = schedule.type;
      document.getElementById('edit_room').value = schedule.room;
      
      if (schedule.building) {
        document.getElementById('edit_building').value = schedule.building;
      }
      
      if (schedule.campus) {
        document.getElementById('edit_campus').value = schedule.campus;
      }
      
      // Set the color option
      const color = schedule.color || '#e3e9ff';
      document.getElementById('edit_color').value = color;
      if (document.getElementById('editColorPreview')) {
        document.getElementById('editColorPreview').style.backgroundColor = color;
      }
      
      // Select the corresponding color option
      document.querySelectorAll('.edit-color-option').forEach(option => {
        option.classList.remove('selected');
        if (option.getAttribute('data-color') === color) {
          option.classList.add('selected');
        }
      });
    };
  }
  
  if (deleteScheduleBtn) {
    deleteScheduleBtn.onclick = () => {
      document.getElementById('deleteScheduleId').value = schedule.id;
      deleteModal.style.display = 'flex';
    };
  }
  
  if (createAnnouncementBtn) {
    createAnnouncementBtn.onclick = () => {
      document.getElementById('announcementScheduleId').value = schedule.id;
      announcementModal.style.display = 'flex';
    };
  }
  
  detailsModal.style.display = 'flex';
}

// Add click events to table rows and view buttons
document.querySelectorAll('.schedule-row, .view-details-btn').forEach(element => {
  element.addEventListener('click', () => {
    const scheduleId = element.getAttribute('data-schedule-id');
    const schedule = (window.schedules || []).find(s => s.id == scheduleId);
    if (schedule) {
      showScheduleDetails(schedule);
    }
  });
});

// Initialize the weekly view if it's active
document.addEventListener('DOMContentLoaded', () => {
  if (weeklyViewBtn && weeklyViewBtn.classList.contains('active')) {
    populateWeeklyView();
  }
});

