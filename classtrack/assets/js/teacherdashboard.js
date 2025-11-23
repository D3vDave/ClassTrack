// teacherdashboard.js - JavaScript for Dashboard functionality

// Global variables
window.currentScheduleId = null;

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
const copyClassCodeBtn = document.getElementById('copyClassCode');

// Function to copy the unique class code
function copyJoinCode() {
    const scheduleId = window.currentScheduleId;
    
    if (!scheduleId) {
        alert("No schedule selected.");
        return;
    }

    const schedule = window.schedules.find(s => s.id == scheduleId);
    
    if (!schedule || !schedule.unique_code) {
        alert("No join code found for this class.");
        return;
    }

    const codeText = schedule.unique_code;

    navigator.clipboard.writeText(codeText)
        .then(() => {
            if (copyClassCodeBtn) {
                const originalText = copyClassCodeBtn.innerHTML;
                copyClassCodeBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    copyClassCodeBtn.innerHTML = originalText;
                }, 2000);
            }
        })
        .catch(() => {
            const tempInput = document.createElement("input");
            tempInput.value = codeText;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            
            if (copyClassCodeBtn) {
                const originalText = copyClassCodeBtn.innerHTML;
                copyClassCodeBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    copyClassCodeBtn.innerHTML = originalText;
                }, 2000);
            }
        });
}

// Function to check if two schedules overlap
function doSchedulesOverlap(scheduleA, scheduleB) {
    if (scheduleA.day !== scheduleB.day) return false;
    
    const startA = new Date(`2000-01-01T${scheduleA.start_time}`);
    const endA = new Date(`2000-01-01T${scheduleA.end_time}`);
    const startB = new Date(`2000-01-01T${scheduleB.start_time}`);
    const endB = new Date(`2000-01-01T${scheduleB.end_time}`);
    
    return startA < endB && startB < endA;
}

// Function to find overlapping schedules
function findOverlappingSchedules(schedules) {
    const overlaps = [];
    
    for (let i = 0; i < schedules.length; i++) {
        for (let j = i + 1; j < schedules.length; j++) {
            const scheduleA = schedules[i];
            const scheduleB = schedules[j];
            
            if (doSchedulesOverlap(scheduleA, scheduleB)) {
                // Check if this overlap already exists
                const existingOverlap = overlaps.find(overlap => 
                    overlap.some(s => s.id === scheduleA.id) && 
                    overlap.some(s => s.id === scheduleB.id)
                );
                
                if (!existingOverlap) {
                    overlaps.push([scheduleA, scheduleB]);
                }
            }
        }
    }
    
    return overlaps;
}

// Function to check for schedule conflicts
function checkScheduleConflicts(newSchedule, existingSchedules, excludeId = null) {
    const conflicts = [];
    
    existingSchedules.forEach(schedule => {
        // Skip the schedule being edited
        if (excludeId && schedule.id == excludeId) return;
        
        if (doSchedulesOverlap(newSchedule, schedule)) {
            conflicts.push(schedule);
        }
    });
    
    return conflicts;
}

// Function to show overlap warning
function showOverlapWarning(conflicts, schedule) {
    if (conflicts.length > 0) {
        const conflictList = conflicts.map(c => 
            `${c.subject_code} (${c.section}): ${c.start_time.substring(0,5)}-${c.end_time.substring(0,5)}`
        ).join('\n');
        
        const warningMsg = `This schedule conflicts with:\n${conflictList}\n\nDo you want to continue anyway?`;
        
        return confirm(warningMsg);
    }
    return true;
}

// Populate mini weekly view with overlap handling
function populateMiniWeeklyView(schedules, dayMap) {
    if (!schedules || !dayMap) return;
    
    // Clear all day cells first
    document.querySelectorAll('.day-cell').forEach(cell => {
        cell.innerHTML = '';
    });
    
    // Group schedules by day and check for overlaps
    const schedulesByDay = {};
    
    schedules.forEach(schedule => {
        const dayIndex = dayMap[schedule.day];
        if (dayIndex === undefined) return;
        
        if (!schedulesByDay[dayIndex]) {
            schedulesByDay[dayIndex] = [];
        }
        schedulesByDay[dayIndex].push(schedule);
    });
    
    // Process each day
    Object.keys(schedulesByDay).forEach(dayIndex => {
        const daySchedules = schedulesByDay[dayIndex];
        const dayCell = document.querySelector(`.day-cell[data-day="${dayIndex}"]`);
        
        if (!dayCell) return;
        
        // Check for overlapping schedules
        const overlaps = findOverlappingSchedules(daySchedules);
        
        // Display overlap warning if needed
        if (overlaps.length > 0) {
            const warningDiv = document.createElement('div');
            warningDiv.className = 'overlap-warning';
            warningDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <span>${overlaps.length} overlapping class(es)</span>
            `;
            dayCell.appendChild(warningDiv);
        }
        
        // Add schedule items
        daySchedules.forEach(schedule => {
            const scheduleItem = document.createElement('div');
            scheduleItem.className = 'schedule-item';
            scheduleItem.style.backgroundColor = schedule.color || '#e3e9ff';
            scheduleItem.setAttribute('data-schedule-id', schedule.id);
            
            // Check if this schedule is involved in any overlap
            const isOverlapping = overlaps.some(overlap => 
                overlap.some(s => s.id === schedule.id)
            );
            
            if (isOverlapping) {
                scheduleItem.classList.add('overlapping');
            }
            
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
                ${isOverlapping ? '<div class="overlap-indicator"><i class="fas fa-exclamation-circle"></i></div>' : ''}
            `;
            
            // Add click event
            scheduleItem.addEventListener('click', () => {
                showScheduleDetails(schedule);
            });
            
            dayCell.appendChild(scheduleItem);
        });
    });
}

// Show schedule details
function showScheduleDetails(schedule) {
    // Set the current schedule ID
    window.currentScheduleId = schedule.id;
    
    const detailsContainer = document.getElementById('scheduleDetails');
    if (!detailsContainer) return;
    
    // Check for overlaps with this schedule
    const overlaps = checkScheduleConflicts(schedule, window.schedules, schedule.id);
    
    // Format time for display
    const startTime = schedule.start_time.substring(0, 5);
    const endTime = schedule.end_time.substring(0, 5);
    
    detailsContainer.innerHTML = `
        <h3>${schedule.subject_code}</h3>
        ${overlaps.length > 0 ? `
            <div class="overlap-warning-details">
                <i class="fas fa-exclamation-triangle"></i>
                <span>This schedule overlaps with ${overlaps.length} other class(es)</span>
            </div>
        ` : ''}
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
            ${schedule.unique_code ? `
            <tr>
                <th>Unique Code</th>
                <td><strong>${schedule.unique_code}</strong></td>
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

// Copy join code button
if (copyClassCodeBtn) {
    copyClassCodeBtn.addEventListener('click', copyJoinCode);
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
    });
});

// Edit form color selection
document.querySelectorAll('.edit-color-option').forEach(option => {
    option.addEventListener('click', () => {
        document.querySelectorAll('.edit-color-option').forEach(opt => opt.classList.remove('selected'));
        option.classList.add('selected');
        const color = option.getAttribute('data-color');
        document.getElementById('edit_color').value = color;
    });
});

// Add form validation for schedule conflicts
document.addEventListener('DOMContentLoaded', () => {
    // Check schedule form for conflicts
    const scheduleForm = document.querySelector('form[method="POST"]');
    if (scheduleForm && !scheduleForm.querySelector('input[name="edit_schedule"]')) {
        scheduleForm.addEventListener('submit', function(e) {
            const section = document.getElementById('section').value;
            const subject_code = document.getElementById('subject_code').value;
            const day = document.getElementById('day').value;
            const start_time = document.getElementById('start_time').value;
            const end_time = document.getElementById('end_time').value;
            
            if (section && subject_code && day && start_time && end_time) {
                const newSchedule = {
                    day: day,
                    start_time: start_time,
                    end_time: end_time
                };
                
                const conflicts = checkScheduleConflicts(newSchedule, window.schedules);
                if (conflicts.length > 0 && !showOverlapWarning(conflicts, newSchedule)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
    
    // Check edit form for conflicts
    const editForm = document.getElementById('editScheduleForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const schedule_id = document.getElementById('editScheduleId').value;
            const day = document.getElementById('edit_day').value;
            const start_time = document.getElementById('edit_start_time').value;
            const end_time = document.getElementById('edit_end_time').value;
            
            if (schedule_id && day && start_time && end_time) {
                const editedSchedule = {
                    day: day,
                    start_time: start_time,
                    end_time: end_time
                };
                
                const conflicts = checkScheduleConflicts(editedSchedule, window.schedules, schedule_id);
                if (conflicts.length > 0 && !showOverlapWarning(conflicts, editedSchedule)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
    
    // Populate the mini weekly view
    populateMiniWeeklyView(window.schedules, window.dayMap);
    
    // Add click events to class boxes
    document.querySelectorAll('.class-box').forEach(box => {
        box.addEventListener('click', () => {
            const scheduleId = box.getAttribute('data-schedule-id');
            const schedule = (window.schedules || []).find(s => s.id == scheduleId);
            if (schedule) {
                showScheduleDetails(schedule);
            }
        });
    });
    
    // Add click events to code copy buttons
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
});