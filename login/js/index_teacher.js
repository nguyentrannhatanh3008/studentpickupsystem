// Global error handling to catch unexpected errors and reload the page
window.onerror = function(message, source, lineno, colno, error) {
    console.error(`Global Error: ${message} at ${source}:${lineno}:${colno}`);
    alert('Đã xảy ra lỗi nghiêm trọng. Trang sẽ được tải lại.');
    location.reload();
};

// Handle unhandled promise rejections
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled Promise Rejection:', event.reason);
    alert('Đã xảy ra lỗi nghiêm trọng. Trang sẽ được tải lại.');
    location.reload();
});

$(document).ready(function () {
    // Initialize variables
    const btn = $("#btn"); // Sidebar toggle button
    const sidebar = $(".sidebar"); // Sidebar element
    const dropdownToggle = $('.nav-link.dropdown-toggle'); // Dropdown toggle link
    const dropdownMenu = $('.dropdown-menu'); // Dropdown menu
    const pickupForm = $('#pickupForm'); // Pickup registration form
    const confirmModal = $('#confirmModal'); // Confirmation modal
    const deleteSelectedBtn = $('#deleteSelectedBtn'); // Bulk delete button
    const selectAllCheckbox = $('#selectAll'); // Select All checkbox
    const viewStudentSelect = $('#viewStudentSelect'); // View student info dropdown
    const autofillInfo = $('#autofillInfo'); // Autofill info box
    const pickupStudentSelect = $('#pickupStudentSelect'); // Pickup student select
    const dialogContainer = $('.main-container'); // Container for dialog boxes
    let table; // DataTable instance
    // Track currently displayed dialogs by pickup ID
    const displayedDialogs = new Set();

    // Load sidebar state from localStorage
    if (localStorage.getItem('sidebarState') === 'active') {
        sidebar.addClass("active");
    }

    // Handle sidebar toggle and save state
    if (btn.length) {
        btn.on('click', function () {
            sidebar.toggleClass("active");
            // Save sidebar state to localStorage
            if (sidebar.hasClass("active")) {
                localStorage.setItem('sidebarState', 'active');
            } else {
                localStorage.removeItem('sidebarState');
            }
        });
    }

    // Handle dropdown toggle
    if (dropdownToggle.length) {
        dropdownToggle.on('click', function (event) {
            event.preventDefault();
            event.stopPropagation(); // Prevent event bubbling
            dropdownMenu.toggleClass('show');
        });
    }

    // Close dropdown when clicking outside
    $(window).on('click', function(e) {
        if (dropdownToggle.length && !dropdownToggle.is(e.target) && dropdownMenu.has(e.target).length === 0) {
            dropdownMenu.removeClass('show');
        }
    });
    $(document).on('click', '.view-details-btn', function() {
        const studentId = $(this).data('student-id');
        
        if (!studentId) {
            alert('Không tìm thấy ID học sinh.');
            return;
        }
        
        // Show the modal
        $('#studentDetailsModal').modal('show');
        
        // Show a loading indicator
        $('#studentDetailsContent').html('<p>Đang tải thông tin...</p>');
        
        // Fetch student details via AJAX
        $.ajax({
            url: 'index_teacher.php',
            method: 'POST',
            data: { 
                action: 'fetch_student_details',
                student_id: studentId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const student = response.student;
                    
                    function formatDateTime(dateTimeStr) {
                        if (!dateTimeStr) return 'Không có';
                        // Split the string at the '.' and take the first part
                        return dateTimeStr.split('.')[0];
                    }

                    let detailsHtml = `
                        <table class="table table-bordered">
                            <tr>
                                <th>ID</th>
                                <td>${student.id}</td>
                            </tr>
                            <tr>
                                <th>Mã Học Sinh</th>
                                <td>${student.code}</td>
                            </tr>
                            <tr>
                                <th>Tên</th>
                                <td>${student.name}</td>
                            </tr>
                            <tr>
                                <th>Giới Tính</th>
                                <td>${student.gender || 'Không xác định'}</td>
                            </tr>
                            <tr>
                                <th>Tên Bố</th>
                                <td>${student.fn || 'Không có'}</td>
                            </tr>
                            <tr>
                                <th>Số Điện Thoại Bố</th>
                                <td>${student.fpn || 'Không có'}</td>
                            </tr>
                            <tr>
                                <th>Tên Mẹ</th>
                                <td>${student.mn || 'Không có'}</td>
                            </tr>
                            <tr>
                                <th>Số Điện Thoại Mẹ</th>
                                <td>${student.mpn || 'Không có'}</td>
                            </tr>
                            <tr>
                                <th>Ngày Sinh</th>
                                <td>${student.birthdate || 'Không có'}</td>
                            </tr>
                            <tr>
                                <th>Lớp</th>
                                <td>${student.class || 'Không có'}</td>
                            </tr>
                            <tr>
                                <th>Ngày Tạo</th>
                                <td>${formatDateTime(student.created_at)}</td>
                            </tr>
                            <tr>
                                <th>Ngày Cập Nhật</th>
                                <td>${formatDateTime(student.updated_at)}</td>
                            </tr>
                        </table>
                    `;

                    
                    $('#studentDetailsContent').html(detailsHtml);
                } else {
                    $('#studentDetailsContent').html(`<p class="text-danger">${response.message}</p>`);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $('#studentDetailsContent').html('<p class="text-danger">Đã xảy ra lỗi khi tải thông tin học sinh.</p>');
            }
        });
    });

    // Initialize DataTables with export buttons
    $.fn.dataTable.ext.errMode = 'none'; // Suppress DataTables error alerts
    table = $('#pickupHistoryTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        lengthMenu: [5, 10, 25, 50],
        pageLength: 10,
        language: {
            lengthMenu: "Hiển thị _MENU_ mục trên mỗi trang",
            zeroRecords: "Không tìm thấy kết quả nào.",
            info: "Hiển thị trang _PAGE_ trong tổng số _PAGES_",
            infoEmpty: "Không có mục nào được hiển thị",
            infoFiltered: "(lọc từ _MAX_ mục)",
            emptyTable: "Không tìm thấy lịch sử.",
            search: "Tìm kiếm:",
            paginate: {
                first: "Đầu",
                last: "Cuối",
                next: "Tiếp",
                previous: "Trước"
            }
        },
        dom: 'Bfrtip', // Define the position of buttons
        buttons: [
            {
                extend: 'csvHtml5',
                text: '<i class="fas fa-file-csv"></i> Xuất CSV',
                className: 'btn btn-secondary btn-sm mr-2',
                exportOptions: {
                    columns: ':visible:not(:last-child)' // Exclude the last column (Actions)
                }
            },
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Xuất Excel',
                className: 'btn btn-secondary btn-sm mr-2',
                exportOptions: {
                    columns: ':visible:not(:last-child)'
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> In',
                className: 'btn btn-secondary btn-sm',
                exportOptions: {
                    columns: ':visible:not(:last-child)'
                }
            }
        ],
        responsive: true,
        autoWidth: false,
        rowId: 'pickup_id', // Set rowId to ensure uniqueness
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'dt-body-center',
                render: function (data, type, row) {
                    // Checkbox column
                    const canDelete = canDeletePickup(row);
                    return canDelete ? `<input type='checkbox' class='row-checkbox' data-pickup-id='${row.pickup_id}'>` : '';
                }
            },
            { data: 'student_name', className: 'student-name' },
            { data: 'class', className: 'student-class' },
            { 
                data: 'created_at', 
                type: 'datetime', 
                render: function(data, type, row) {
                    return formatTime(data);
                }
            },
            { 
                data: 'status', 
                render: function(data, type, row) {
                    return `<span id='status_${row.pickup_id}'>${data}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    // Countdown column
                    const expirationTime = getExpirationTime(data.created_at);
                    const isExpired = isPickupExpired(expirationTime);
                    return `<span id='countdown_${data.pickup_id}' data-expiration='${expirationTime.toISOString()}'>${isExpired ? 'Đã hết hạn' : 'Đang đếm ngược...'}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'dt-body-center',
                render: function (data, type, row) {
                    // Actions column
                    const canDelete = canDeletePickup(row);
                    const isPending = data.status.toLowerCase() === 'chờ xử lý';
                    let actions = '';
                    if (canDelete) {
                        actions += `<button class='btn btn-sm btn-danger delete-btn mr-2' data-pickup-id='${data.pickup_id}'><i class='fas fa-trash-alt'></i> Xóa</button>`;
                    }
                    if (isPending) {
                        const replayCooldownRemaining = getReplayCooldownRemaining(data);
                        if (replayCooldownRemaining > 0) {
                            // "Replay" button disabled with countdown
                            const cooldownText = formatCooldown(replayCooldownRemaining);
                            actions += `<button class='btn btn-sm btn-info replay-btn' data-pickup-id='${data.pickup_id}' data-student-id='${data.student_id}' data-deadline='${Math.floor(Date.now() / 1000) + replayCooldownRemaining}' disabled>Phát lại (${cooldownText})</button>`;
                        } else {
                            // "Replay" button enabled
                            actions += `<button class='btn btn-sm btn-info replay-btn' data-pickup-id='${data.pickup_id}' data-student-id='${data.student_id}'><i class='fas fa-redo'></i> Phát lại</button>`;
                        }
                    }
                    return actions;
                }
            }
        ]
    });

    console.log('DataTable initialized:', table);

    // Helper Functions

    /**
     * Format timestamp as 'YYYY-MM-DD HH:mm:ss'
     * @param {string} timeStr - The timestamp string
     * @returns {string} - Formatted time string
     */
    function formatTime(timeStr) {
        const timeWithoutMicroseconds = timeStr.split('.')[0];
        const date = new Date(timeWithoutMicroseconds);

        const year = date.getFullYear();
        const month = ('0' + (date.getMonth() + 1)).slice(-2);
        const day = ('0' + date.getDate()).slice(-2);

        const hours = ('0' + date.getHours()).slice(-2);
        const minutes = ('0' + date.getMinutes()).slice(-2);
        const seconds = ('0' + date.getSeconds()).slice(-2);

        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    /**
     * Calculate expiration time (24 hours from created_at)
     * @param {string} createdAt - The creation timestamp
     * @returns {Date} - Expiration Date object
     */
    function getExpirationTime(createdAt) {
        return new Date(Date.parse(createdAt) + 24 * 60 * 60 * 1000); // 24 hours later
    }

    /**
     * Check if the pickup is expired
     * @param {Date} expirationTime - The expiration Date object
     * @returns {boolean} - True if expired, else false
     */
    function isPickupExpired(expirationTime) {
        return new Date() >= expirationTime;
    }

    /**
     * Determine if a pickup can be deleted
     * @param {Object} row - The DataTable row data
     * @returns {boolean} - True if can delete, else false
     */
    function canDeletePickup(row) {
        const expirationTime = getExpirationTime(row.created_at);
        return isPickupExpired(expirationTime) && row.status.toLowerCase() !== 'chờ xử lý';
    }

    /**
     * Calculate remaining cooldown time for "Replay" button
     * @param {Object} row - The DataTable row data
     * @returns {number} - Remaining cooldown time in seconds
     */
    function getReplayCooldownRemaining(row) {
        const cooldownPeriod = 3 * 60; // 3 minutes in seconds
        const currentTime = Math.floor(Date.now() / 1000);
        const lastReplayTime = row.replay_deadline ? row.replay_deadline - cooldownPeriod : 0;
        const timeSinceLastReplay = currentTime - lastReplayTime;
        return timeSinceLastReplay < cooldownPeriod ? cooldownPeriod - timeSinceLastReplay : 0;
    }

    /**
     * Format cooldown time as "MM:SS"
     * @param {number} seconds - Remaining seconds
     * @returns {string} - Formatted cooldown string
     */
    function formatCooldown(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds}`;
    }

    /**
     * Add a pickup to the history table using DataTables API
     * @param {Object} data - Pickup data
     */
    function addToHistory(data) {
        if (!data || !data.pickup_id) {
            console.error('Invalid data:', data);
            return;
        }

        // Check if the row already exists
        const existingRow = table.row(`#row_${data.pickup_id}`);
        if (existingRow.node()) {
            // Update the existing row
            existingRow.data(data).draw(false);
        } else {
            // Add new row
            table.row.add(data).draw(false);
        }

        // Initialize countdown if not expired
        const expirationTime = getExpirationTime(data.created_at);
        if (!isPickupExpired(expirationTime)) {
            const countdownElement = $(`#countdown_${data.pickup_id}`);
            initializeCountdown(countdownElement, expirationTime, data.pickup_id);
        }

        // If pickup is pending, create a dialog
        if (data.status.toLowerCase() === 'chờ xử lý') {
            createDialog(data.student_name, data.class, data.created_at, data.pickup_id, data.student_id);
        }
    }

    /**
     * Create a dialog box for pending pickups
     * @param {string} name - Student's name
     * @param {string} studentClass - Student's class
     * @param {string} time - Pickup creation time
     * @param {number} pickupId - Pickup ID
     * @param {number} studentId - Student ID
     */
    function createDialog(name, studentClass, time, pickupId, studentId) {
        // Avoid creating duplicate dialogs
        if (displayedDialogs.has(pickupId)) {
            return;
        }

        const dialog = $('<div>', {
            class: 'dialog-box alert alert-info mb-3',
            id: `dialog_${pickupId}`,
            'data-student-name': name,
            'data-student-class': studentClass
        });

        const formattedTime = formatTime(time);

        dialog.html(
            `<h5>Thông tin đón học sinh</h5>
             <p><strong>Tên:</strong> ${name}</p>
             <p><strong>Lớp:</strong> ${studentClass}</p>
             <p><strong>Thời gian:</strong> ${formattedTime}</p>
             <p><strong>Trạng thái:</strong> <span id="dialog_status_${pickupId}" class="text-warning">Chờ xử lý</span></p>
             <div class="btn-group" role="group">
                 <button type="button" data-pickup-id="${pickupId}" data-student-id="${studentId}" class="confirm-btn btn btn-success btn-sm"><i class="fas fa-check"></i> Xác nhận</button>
                 <button type="button" data-pickup-id="${pickupId}" data-student-id="${studentId}" class="cancel-btn btn btn-warning btn-sm"><i class="fas fa-times"></i> Hủy</button>
                 <button type="button" data-pickup-id="${pickupId}" data-student-id="${studentId}" data-student-name="${name}" data-student-class="${studentClass}" class="replay-btn btn btn-info btn-sm">Phát lại</button>
             </div>`
        );
        dialogContainer.append(dialog);

        // Mark this dialog as displayed
        displayedDialogs.add(pickupId);
    }

    /**
     * Initialize countdown timer for pickup expiration
     * @param {jQuery} element - The countdown element
     * @param {Date} endtime - The expiration Date object
     * @param {number} pickupId - Pickup ID
     */
    function initializeCountdown(element, endtime, pickupId) {
        function updateCountdown() {
            const t = getTimeRemaining(endtime);
            if (t.total <= 0) {
                clearInterval(interval);
                element.text('Đã hết hạn');

                // Show checkbox and delete button if needed
                var row = table.row(`#row_${pickupId}`);
                if (row.node()) {
                    // Add checkbox if not already present
                    var checkboxCell = $(row.node()).find('td').eq(0);
                    if ($.trim(checkboxCell.html()) === '') {
                        checkboxCell.html(`<input type='checkbox' class='row-checkbox' data-pickup-id='${pickupId}'>`);
                    }

                    // Add delete button if not already present
                    var actionCell = $(row.node()).find('td').last();
                    if (actionCell.find('.delete-btn').length === 0) {
                        var deleteBtn = `<button class='btn btn-sm btn-danger delete-btn mr-2' data-pickup-id='${pickupId}'><i class='fas fa-trash-alt'></i> Xóa</button>`;
                        actionCell.prepend(deleteBtn);
                    }
                }
            } else {
                let remainingTimeText = '';
                if (t.days > 0) {
                    remainingTimeText += `${t.days} ngày `;
                }
                if (t.hours > 0) {
                    remainingTimeText += `${t.hours} giờ `;
                }
                if (t.minutes > 0) {
                    remainingTimeText += `${t.minutes} phút `;
                }
                if (t.seconds >= 0) {
                    remainingTimeText += `${t.seconds} giây`;
                }

                element.text(remainingTimeText);
            }
        }

        updateCountdown();
        const interval = setInterval(updateCountdown, 1000);
        element.data('interval', interval);
    }

    /**
     * Calculate time remaining until endtime
     * @param {Date} endtime - The expiration Date object
     * @returns {Object} - Time remaining
     */
    function getTimeRemaining(endtime) {
        const total = Date.parse(endtime) - Date.parse(new Date());
        const seconds = Math.floor((total / 1000) % 60);
        const minutes = Math.floor((total / 1000 / 60) % 60);
        const hours = Math.floor((total / (1000 * 60 * 60)) % 24);
        const days = Math.floor(total / (1000 * 60 * 60 * 24));
        return {
            total,
            days,
            hours,
            minutes,
            seconds
        };
    }

    /**
     * Handle replay button clicks within dialogs
     */
    dialogContainer.on('click', 'button', function (event) {
        const button = $(this);
        const pickupId = button.data('pickup-id');

        if (!pickupId) {
            console.error('Không tìm thấy pickup ID.');
            return;
        }

        if (button.hasClass('confirm-btn')) {
            confirmPickup(pickupId, button);
        } else if (button.hasClass('cancel-btn')) {
            cancelPickup(pickupId, button);
        } else if (button.hasClass('replay-btn')) {
            replayPickupInDialog(pickupId, button);
        }
    });

    /**
     * Confirm a pickup
     * @param {number} pickupId - Pickup ID
     * @param {jQuery} button - The clicked button
     */
    function confirmPickup(pickupId, button) {
        console.log(`Confirming pickup ID: ${pickupId}`); // Debug log

        fetch('index_teacher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=confirm&pickup_id=${encodeURIComponent(pickupId)}`
        })
        .then(response => response.json())
        .then(data => {
            console.log('Phản hồi:', data);

            if (data.status === 'success') {
                const statusElement = $(`#status_${pickupId}`);
                if (statusElement.length) {
                    statusElement.text('Đã đón');
                }
                // Remove dialog box
                const dialogBox = button.closest('.dialog-box');
                if (dialogBox.length) {
                    dialogBox.remove();
                    // Remove from displayedDialogs
                    displayedDialogs.delete(pickupId);
                }

                // Hiển thị Modal Xác Nhận Đón Thành Công
                confirmModal.modal('show');
            } else {
                alert(`Lỗi: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Lỗi:', error);
            alert('Đã xảy ra lỗi. Vui lòng thử lại.');
        });
    }

    /**
     * Cancel a pickup
     * @param {number} pickupId - Pickup ID
     * @param {jQuery} button - The clicked button
     */
    function cancelPickup(pickupId, button) {
        console.log(`Cancelling pickup ID: ${pickupId}`); // Debug log

        fetch('index_teacher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=cancel&pickup_id=${encodeURIComponent(pickupId)}`
        })
        .then(response => response.json())
        .then(data => {
            console.log('Phản hồi:', data);

            if (data.status === 'success') {
                // Remove dialog box
                const dialogBox = button.closest('.dialog-box');
                if (dialogBox.length) {
                    dialogBox.remove();
                    // Remove from displayedDialogs
                    displayedDialogs.delete(pickupId);
                }

                // Update status in history table
                const statusCell = $(`#status_${pickupId}`);
                if (statusCell.length) {
                    statusCell.text('Đã hủy');
                }

                // Enable corresponding checkbox
                if (data.student_id) { // Assuming PHP returns 'student_id'
                    const checkbox = $(`.row-checkbox[data-pickup-id='${pickupId}']`);
                    if (checkbox.length) {
                        checkbox.prop('disabled', false);
                        checkbox.prop('checked', false);
                    }
                }
            } else {
                alert(`Lỗi: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Lỗi:', error);
            alert('Đã xảy ra lỗi. Vui lòng thử lại.');
        });
    }

    /**
     * Replay a pickup from dialog
     * @param {number} pickupId - Pickup ID
     * @param {jQuery} button - The clicked button
     */
    function replayPickupInDialog(pickupId, button) {
        console.log(`Replaying pickup ID: ${pickupId}`); // Debug log

        const dialogBox = button.closest('.dialog-box');
        if (!dialogBox.length) {
            alert('Không tìm thấy hộp thoại.');
            return;
        }

        const studentName = dialogBox.data('student-name');
        const studentClass = dialogBox.data('student-class');
        const studentId = button.data('student-id');

        console.log('Student Name:', studentName);
        console.log('Student Class:', studentClass);
        console.log('Student ID:', studentId);

        // Proceed only if studentId is defined
        if (!studentId) {
            console.error('Student ID is undefined.');
            alert('Không thể lấy thông tin học sinh. Vui lòng thử lại.');
            return;
        }

        // Send replay request via AJAX to PHP
        fetch('index_teacher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=replay&pickup_id=${encodeURIComponent(pickupId)}&student_id=${encodeURIComponent(studentId)}&student_name=${encodeURIComponent(studentName)}`
        })
        .then(response => response.json())
        .then(data => {
            console.log('Phản hồi:', data);

            if (data.status === 'success' || data.status === 'cooldown') {
                // Update UI accordingly
                const currentTime = Math.floor(Date.now() / 1000);
                const deadline = data.deadline || (currentTime + 3 * 60); // Default cooldown is 3 minutes

                // Disable the "Replay" button in the dialog and start countdown
                button.prop('disabled', true);
                button.attr('data-deadline', deadline);
                initializeReplayCountdown(button, deadline);

                // Update the "Replay" button in the history table
                const historyButton = $(`.replay-btn[data-pickup-id='${pickupId}']`).not(button);
                if (historyButton.length) {
                    historyButton.prop('disabled', true);
                    historyButton.attr('data-deadline', deadline);
                    initializeReplayCountdown(historyButton, deadline);
                }

                // Update last_replay_time in the data of the row if exists
                const row = table.row(`#row_${pickupId}`);
                if (row.node()) {
                    const rowData = row.data();
                    rowData.last_replay_time = new Date().toISOString();
                    row.data(rowData).draw(false);
                }
            } else {
                alert(`Lỗi: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Lỗi:', error);
            alert(`Đã xảy ra lỗi với yêu cầu: ${error.message}`);
        });
    }

    /**
     * Handle individual replay button clicks in the table
     */
    $(document).on('click', '.replay-btn', function(event) {
        event.preventDefault();
        const button = $(this);
        const pickupId = button.data('pickup-id');
        const studentId = button.data('student-id');
        const studentName = button.closest('tr').find('.student-name').text() || 'Unknown';

        console.log(`Replaying pickup ID: ${pickupId}`);

        // Proceed only if studentId is defined
        if (!studentId) {
            console.error('Student ID is undefined.');
            alert('Không thể lấy thông tin học sinh. Vui lòng thử lại.');
            return;
        }

        // Send replay request via AJAX to PHP
        fetch('index_teacher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=replay&pickup_id=${encodeURIComponent(pickupId)}&student_id=${encodeURIComponent(studentId)}&student_name=${encodeURIComponent(studentName)}`
        })
        .then(response => response.json())
        .then(data => {
            console.log('Phản hồi:', data); // Log response

            if (data.status === 'success' || data.status === 'cooldown') {
                // Update UI accordingly
                const currentTime = Math.floor(Date.now() / 1000);
                const deadline = data.deadline || (currentTime + 3 * 60); // Default cooldown is 3 minutes
                button.prop('disabled', true);
                button.attr('data-deadline', deadline);
                initializeReplayCountdown(button, deadline);

                // Update the "Replay" button in the dialog if it exists
                const dialogButton = $(`#dialog_${pickupId} .replay-btn`);
                if (dialogButton.length) {
                    dialogButton.prop('disabled', true);
                    dialogButton.attr('data-deadline', deadline);
                    initializeReplayCountdown(dialogButton, deadline);
                }

                // Update last_replay_time in the data of the row
                const row = table.row(`#row_${pickupId}`);
                if (row.node()) {
                    const rowData = row.data();
                    rowData.last_replay_time = new Date().toISOString();
                    row.data(rowData).draw(false);
                }
            } else {
                alert(`Lỗi: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Lỗi:', error);
            alert(`Đã xảy ra lỗi với yêu cầu: ${error.message}`);
        });
    });

    /**
     * Initialize replay countdown timer
     * @param {jQuery} button - The replay button element
     * @param {number} deadline - Unix timestamp in seconds
     */
    function initializeReplayCountdown(button, deadline) {
        function updateReplayCountdown() {
            const now = Math.floor(Date.now() / 1000);
            let remaining = deadline - now;

            if (remaining <= 0) {
                clearInterval(interval);
                button.removeAttr('disabled');
                button.html('<i class="fas fa-redo"></i> Phát lại');
                button.removeAttr('data-deadline');
            } else {
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                button.html(`Phát lại (${formatCooldown(remaining)})`);
            }
        }

        // Initialize countdown immediately
        updateReplayCountdown();
        const interval = setInterval(updateReplayCountdown, 1000);
        button.data('interval', interval);
    }

    /**
     * Initialize all replay countdowns on page load
     */
    function initializeReplayCountdowns() {
        $('.replay-btn[disabled]').each(function() {
            const button = $(this);
            const deadline = parseInt(button.attr('data-deadline'), 10);

            if (isNaN(deadline) || deadline <= 0) {
                button.prop('disabled', false).html('<i class="fas fa-redo"></i> Phát lại');
                return;
            }

            initializeReplayCountdown(button, deadline);
        });
    }

    /**
     * Fetch pickup history from server
     */
    function fetchHistory() {
        $.ajax({
            url: 'index_teacher.php', // Ensure your PHP script handles 'action=fetch_history'
            method: 'POST',
            data: { action: 'fetch_history' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    table.clear(); // Clear existing data

                    if (response.history.length === 0) {
                        // Let DataTables handle the empty table message
                        table.draw(false);
                        // Also remove all dialogs as there are no pending pickups
                        dialogContainer.find('.dialog-box').remove();
                        displayedDialogs.clear();
                        return; // Exit the function to prevent further processing
                    }

                    response.history.forEach(function(pickup) {
                        addToHistory(pickup);
                    });

                    table.draw(false);

                    initializeAllCountdowns();
                    initializeReplayCountdowns();
                    synchronizeDialogs(response.history);
                } else {
                    console.error('Error fetching history:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.log('Status:', status);
                console.log('Response:', xhr.responseText);
            }
        });
    }

    /**
     * Synchronize dialogs based on current history
     * @param {Array} history - Array of pickup history objects
     */
    function synchronizeDialogs(history) {
        // Get list of pending pickups from history
        const pendingPickups = history.filter(pickup => pickup.status.toLowerCase() === 'chờ xử lý');
        const pendingPickupIds = pendingPickups.map(pickup => pickup.pickup_id);

        // Get list of currently displayed dialog pickup IDs
        const currentDialogs = dialogContainer.find('.dialog-box');
        const currentDialogIds = currentDialogs.map(function() {
            return $(this).attr('id').replace('dialog_', '');
        }).get();

        // Find pickups that need to be added to dialogs
        const pickupsToAdd = pendingPickupIds.filter(id => !currentDialogIds.includes(id.toString()));

        // Find pickups that need to be removed from dialogs
        const pickupsToRemove = currentDialogIds.filter(id => !pendingPickupIds.includes(parseInt(id)));

        // Add new dialogs
        pendingPickups.forEach(pickup => {
            if (pickupsToAdd.includes(pickup.pickup_id)) {
                createDialog(pickup.student_name, pickup.class, pickup.created_at, pickup.pickup_id, pickup.student_id);
            }
        });

        // Remove dialogs that are no longer pending
        pickupsToRemove.forEach(id => {
            const dialog = $(`#dialog_${id}`);
            if (dialog.length) {
                dialog.remove();
                console.log(`Dialog box for pickup ID ${id} removed.`);
                displayedDialogs.delete(id);
            }
        });

        // Re-initialize replay countdowns for newly added dialogs
        initializeReplayCountdowns();
    }

    /**
     * Handle form submission for pickup registration
     */
    if (pickupForm.length) {
        pickupForm.on('submit', function (event) {
            event.preventDefault(); // Prevent default form submission

            const selectedStudents = pickupStudentSelect.val(); // Get selected students from the multi-select

            if (!selectedStudents || selectedStudents.length === 0) {
                alert('Vui lòng chọn ít nhất một học sinh.');
                return;
            }

            // Send AJAX request to register pickups
            $.ajax({
                url: 'index_teacher.php',
                method: 'POST',
                data: {
                    action: 'registerPickup',
                    students: selectedStudents
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        if (response.pickups && response.pickups.length > 0) {
                            response.pickups.forEach(function(pickup) {
                                console.log('Adding pickup to history:', pickup);
                                addToHistory(pickup);
                                createDialog(pickup.student_name, pickup.class, pickup.created_at, pickup.pickup_id, pickup.student_id);

                                // Disable the selected student option
                                pickupStudentSelect.find(`option[value='${pickup.student_id}']`).prop('disabled', true);
                            });
                        }

                        // Hiển thị Modal Xác Nhận Đón Thành Công
                        confirmModal.modal('show');
                    } else {
                        alert(`Lỗi: ${response.message}`);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('Đã xảy ra lỗi khi đăng ký đón.');
                }
            });
        });
    }

    /**
     * Handle "Select All" checkbox
     */
    selectAllCheckbox.on('change', function() {
        const isChecked = $(this).is(':checked');
        // Only select checkboxes that are visible and not disabled
        $('.row-checkbox:visible:not(:disabled)').prop('checked', isChecked);
        toggleDeleteSelectedBtn();
    });

    /**
     * Handle individual row checkbox change
     */
    $(document).on('change', '.row-checkbox', function() {
        const totalCheckboxes = $('.row-checkbox:visible:not(:disabled)').length;
        const checkedCheckboxes = $('.row-checkbox:checked').length;
        selectAllCheckbox.prop('checked', totalCheckboxes === checkedCheckboxes);
        toggleDeleteSelectedBtn();
    });

    /**
     * Show or hide the "Delete Selected" button
     */
    function toggleDeleteSelectedBtn() {
        if ($('.row-checkbox:checked').length > 0) {
            deleteSelectedBtn.show();
        } else {
            deleteSelectedBtn.hide();
        }
    }

    /**
     * Handle bulk delete button clicks
     */
    deleteSelectedBtn.on('click', function() {
        const selectedPickups = $('.row-checkbox:checked').map(function() {
            return $(this).data('pickup-id');
        }).get();

        if (selectedPickups.length === 0) {
            alert('Vui lòng chọn ít nhất một mục để xóa.');
            return;
        }

        if (!confirm('Bạn có chắc chắn muốn xóa ' + selectedPickups.length + ' mục đã chọn?')) {
            return;
        }

        // Send AJAX request to delete selected pickups
        $.ajax({
            url: 'index_teacher.php',
            method: 'POST',
            data: {
                action: 'delete_pickups',
                pickup_ids: selectedPickups
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    selectedPickups.forEach(function(id) {
                        // Remove the row from DataTable
                        table.row(`#row_${id}`).remove();

                        // Also remove the corresponding dialog box if exists
                        $(`#dialog_${id}`).remove();
                        displayedDialogs.delete(id);

                        // Enable the corresponding student option
                        pickupStudentSelect.find(`option[value='${id}']`).prop('disabled', false);
                    });
                    table.draw(false);
                    alert('Đã xóa các mục đã chọn thành công.');
                    selectAllCheckbox.prop('checked', false);
                    toggleDeleteSelectedBtn();
                } else {
                    alert('Lỗi: ' + response.message);
                }
            },
            error: function() {
                alert('Đã xảy ra lỗi khi xóa các mục.');
            }
        });
    });

    /**
     * Handle individual delete button clicks
     */
    $(document).on('click', '.delete-btn', function() {
        const pickupId = $(this).data('pickup-id');
        if (!confirm('Bạn có chắc chắn muốn xóa mục này không?')) {
            return;
        }

        // Send AJAX request to delete the individual pickup
        $.ajax({
            url: 'index_teacher.php',
            method: 'POST',
            data: {
                action: 'delete_pickups',
                pickup_ids: [pickupId]
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Remove the row from DataTable
                    table.row(`#row_${pickupId}`).remove().draw(false);
                    alert('Đã xóa mục thành công.');

                    // Also remove the corresponding dialog box if exists
                    $(`#dialog_${pickupId}`).remove();
                    displayedDialogs.delete(pickupId);

                    // Enable the corresponding student option
                    pickupStudentSelect.find(`option[value='${pickupId}']`).prop('disabled', false);
                } else {
                    alert('Lỗi: ' + response.message);
                }
            },
            error: function() {
                alert('Đã xảy ra lỗi khi xóa mục.');
            }
        });
    });

    /**
     * Initialize all countdowns for pickup expiration
     */
    function initializeAllCountdowns() {
        $('[id^="countdown_"]').each(function() {
            const element = $(this);
            const pickupId = element.attr('id').replace('countdown_', '');
            const expirationTime = element.data('expiration');

            initializeCountdown(element, expirationTime, pickupId);
        });
    }

    /**
     * Initialize all replay countdowns
     */
    function initializeReplayCountdowns() {
        $('.replay-btn[disabled]').each(function() {
            const button = $(this);
            const deadline = parseInt(button.attr('data-deadline'), 10);

            if (isNaN(deadline) || deadline <= 0) {
                button.prop('disabled', false).html('<i class="fas fa-redo"></i> Phát lại');
                return;
            }

            initializeReplayCountdown(button, deadline);
        });
    }

    /**
     * Initialize replay countdown timer
     * @param {jQuery} button - The replay button element
     * @param {number} deadline - Unix timestamp in seconds
     */
    function initializeReplayCountdown(button, deadline) {
        function updateReplayCountdown() {
            const now = Math.floor(Date.now() / 1000);
            let remaining = deadline - now;

            if (remaining <= 0) {
                clearInterval(interval);
                button.removeAttr('disabled');
                button.html('<i class="fas fa-redo"></i> Phát lại');
                button.removeAttr('data-deadline');
            } else {
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                button.html(`Phát lại (${formatCooldown(remaining)})`);
            }
        }

        // Initialize countdown immediately
        updateReplayCountdown();
        const interval = setInterval(updateReplayCountdown, 1000);
        button.data('interval', interval);
    }

    /**
     * Fetch pickup history on page load and periodically
     */
    fetchHistory();
    initializeAllCountdowns();
    initializeReplayCountdowns();

    // Update history every 5 seconds
    setInterval(fetchHistory, 5000);

    /**
     * Handle selection from View Student Info Dropdown
     */
    viewStudentSelect.on('change', function() {
        const studentId = $(this).val();

        if (studentId) {
            // Fetch student details via AJAX
            $.ajax({
                url: 'index_teacher.php',
                method: 'POST',
                data: {
                    action: 'fetch_student_details',
                    student_id: studentId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#af_student_name').text(response.student.name);
                        $('#af_class').text(response.student.class);
                        $('#af_fpn').text(response.student.fpn);
                        $('#af_mpn').text(response.student.mpn);
                    } else {
                        alert(`Lỗi: ${response.message}`);
                        // Reset autofill info
                        $('#af_student_name').text('Chưa chọn');
                        $('#af_class').text('Chưa chọn');
                        $('#af_fpn').text('Chưa chọn');
                        $('#af_mpn').text('Chưa chọn');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('Đã xảy ra lỗi khi lấy thông tin học sinh.');
                    // Reset autofill info
                    $('#af_student_name').text('Chưa chọn');
                    $('#af_class').text('Chưa chọn');
                    $('#af_fpn').text('Chưa chọn');
                    $('#af_mpn').text('Chưa chọn');
                }
            });
        } else {
            // Reset autofill info
            $('#af_student_name').text('Chưa chọn');
            $('#af_class').text('Chưa chọn');
            $('#af_fpn').text('Chưa chọn');
            $('#af_mpn').text('Chưa chọn');
        }
    });
});
