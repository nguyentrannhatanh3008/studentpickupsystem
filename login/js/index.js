// index.js

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
    const autoFillContainer = $('#autoFillContainer'); // Container for auto-filled student info
    const pickupForm = $('#pickupForm'); // Pickup registration form
    const dialogContainer = $('#dialogContainer'); // Container for dialog boxes
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

    // Handle checkbox changes for students
    const studentCheckboxes = $('#studentCheckboxes input[type="checkbox"]');
    studentCheckboxes.on('change', function () {
        autoFillContainer.empty(); // Clear container

        // Get selected students
        const selectedStudents = studentCheckboxes.filter(':checked').map(function() {
            return {
                id: $(this).val(),
                name: $(this).data('name'),
                class: $(this).data('class')
            };
        }).get();

        // Update auto-fill container with selected students
        selectedStudents.forEach(student => {
            const autoFillBox = $('<div>', { class: 'auto-fill-box' });
            autoFillBox.html(`
                <p><strong>Tên:</strong> ${student.name}</p>
                <p><strong>Lớp:</strong> ${student.class}</p>
                <p><strong>Thời gian:</strong> ${new Date().toLocaleString()}</p>
            `);
            autoFillContainer.append(autoFillBox);
        });
    });

    // Handle form submission for pickup registration
    if (pickupForm.length) {
        pickupForm.on('submit', function (event) {
            event.preventDefault(); // Prevent default form submission

            const selectedStudents = studentCheckboxes.filter(':checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedStudents.length === 0) {
                alert('Vui lòng chọn ít nhất một học sinh.');
                return;
            }

            // Create FormData for AJAX request
            const formData = new FormData();
            formData.append('registerPickup', true);
            selectedStudents.forEach(id => formData.append('students[]', id));

            // Send AJAX request
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new TypeError("Không nhận được JSON từ server.");
                }
                return response.json();
            })
            .then(data => {
                console.log('Phản hồi:', data); // Log response

                if (data.status === 'success') {
                    if (data.pickups && data.pickups.length > 0) {
                        data.pickups.forEach(item => {
                            console.log('Adding item to history:', item); // Log each item
                            addToHistory(item);
                            createDialog(item.student_name, item.class, item.created_at, item.pickup_id, item.student_id);
                        });
                    } else {
                        console.warn('No pickups found in the response.');
                    }

                    // Optionally reload the page if history is empty after adding pickups
                    checkAndReloadIfEmpty();

                    // Uncheck and disable only the selected checkboxes
                    selectedStudents.forEach(id => {
                        const checkbox = $(`#studentCheckboxes input[type="checkbox"][value="${id}"]`);
                        if (checkbox.length) {
                            checkbox.prop('checked', false);
                            checkbox.prop('disabled', true); // Disable only this checkbox
                        }
                    });

                    // Clear auto-fill container
                    autoFillContainer.empty();

                    alert('Đăng ký đón thành công!');
                } else {
                    alert(`Lỗi: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Đã xảy ra lỗi:', error);
                alert(`Đã xảy ra lỗi với yêu cầu: ${error.message}`);
            });
        });
    }

    // Initialize DataTables with rowId option
    $.fn.dataTable.ext.errMode = 'none'; // Suppress DataTables error alerts
    table = $('#pickupHistoryTable').DataTable({
        data: [], // Empty data initially
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
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
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
                    const canDelete = canDeletePickup(data);
                    return canDelete ? `<input type='checkbox' class='row-checkbox' data-pickup-id='${data.pickup_id}'>` : '';
                }
            },
            { data: 'student_name', className: 'student-name' },
            { data: 'class', className: 'student-class' },
            { data: 'created_at', type: 'datetime', render: function(data, type, row) {
                return formatTime(data);
            }},
            { data: 'status', render: function(data, type, row) {
                return `<span id='status_${row.pickup_id}'>${data}</span>`;
            }},
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
                    const canDelete = canDeletePickup(data);
                    const isPending = data.status.toLowerCase() === 'chờ xử lý';
                    let actions = '';
                    if (canDelete) {
                        actions += `<button class='btn btn-sm btn-danger delete-btn' data-pickup-id='${data.pickup_id}'><i class='fas fa-trash-alt'></i> Xóa</button>`;
                    }
                    if (isPending) {
                        const replayCooldownRemaining = getReplayCooldownRemaining(data);
                        if (replayCooldownRemaining > 0) {
                            const cooldownText = formatCooldown(replayCooldownRemaining);
                            actions += `<button class='btn btn-sm btn-info replay-btn' data-pickup-id='${data.pickup_id}' data-student-id='${data.student_id}' data-deadline='${Math.floor(Date.now() / 1000) + replayCooldownRemaining}' disabled>Phát lại (${cooldownText})</button>`;
                        } else {
                            actions += `<button class='btn btn-sm btn-info replay-btn' data-pickup-id='${data.pickup_id}' data-student-id='${data.student_id}'><i class='fas fa-redo'></i> Phát lại</button>`;
                        }
                    }
                    return actions;
                }
            }
        ]
    });

    console.log('DataTable initialized:', table);

    // Function to add a pickup to the history table using DataTables API
    function addToHistory(data) {
        if (!table) {
            console.error('DataTable instance "table" is not initialized.');
            throw new Error('DataTable instance "table" is not initialized.');
        }

        if (!data || !data.pickup_id) {
            console.error('Invalid data:', data);
            return; // Early exit if data is missing or malformed
        }

        // Check if the row already exists
        const existingRow = table.row(`#${data.pickup_id}`);
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
    }

    // Function to format timestamp as 'YYYY-MM-DD HH:mm:ss'
    function formatTime(timeStr) {
        // Remove microseconds if present
        const timeWithoutMicroseconds = timeStr.split('.')[0];
        const date = new Date(timeWithoutMicroseconds);

        // Format time as 'YYYY-MM-DD HH:mm:ss'
        const year = date.getFullYear();
        const month = ('0' + (date.getMonth() + 1)).slice(-2); // Months start at 0
        const day = ('0' + date.getDate()).slice(-2);

        const hours = ('0' + date.getHours()).slice(-2);
        const minutes = ('0' + date.getMinutes()).slice(-2);
        const seconds = ('0' + date.getSeconds()).slice(-2);

        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    // Helper functions
    function canDeletePickup(data) {
        const expirationTime = getExpirationTime(data.created_at);
        return isPickupExpired(expirationTime) && data.status.toLowerCase() !== 'chờ xử lý';
    }

    function getExpirationTime(createdAt) {
        return new Date(Date.parse(createdAt) + 24 * 60 * 60 * 1000);
    }

    function isPickupExpired(expirationTime) {
        return new Date() >= expirationTime;
    }

    function getReplayCooldownRemaining(data) {
        const cooldownPeriod = 3 * 60; // 3 minutes in seconds
        const currentTime = Math.floor(Date.now() / 1000);
        const lastReplayTime = data.last_replay_time ? Math.floor(Date.parse(data.last_replay_time) / 1000) : 0;
        const timeSinceLastReplay = currentTime - lastReplayTime;
        return timeSinceLastReplay < cooldownPeriod ? cooldownPeriod - timeSinceLastReplay : 0;
    }

    function formatCooldown(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds}`;
    }

    function createDialog(name, studentClass, time, pickupId, studentId) {
        // Avoid creating duplicate dialogs
        if (displayedDialogs.has(pickupId)) {
            return;
        }

        const dialog = $('<div>', {
            class: 'dialog-box',
            id: `dialog_${pickupId}`,
            'data-student-name': name,
            'data-student-class': studentClass
        });

        const formattedTime = formatTime(time);

        dialog.html(`
            <h2>Thông tin đón con</h2>
            <p><strong>Tên:</strong> ${name}</p>
            <p><strong>Lớp:</strong> ${studentClass}</p>
            <p><strong>Thời gian:</strong> ${formattedTime}</p>
            <p><strong>Trạng thái:</strong> <span id="dialog_status_${pickupId}" style="color: orange;">Chờ xử lý</span></p>
            <button type="button" data-pickup-id="${pickupId}" data-student-id="${studentId}" class="confirm-btn btn btn-success">Xác nhận</button>
            <button type="button" data-pickup-id="${pickupId}" data-student-id="${studentId}" class="cancel-btn btn btn-warning">Hủy</button>
            <button type="button" data-pickup-id="${pickupId}" data-student-id="${studentId}" class="replay-btn btn btn-info">Phát lại</button>
        `);
        dialogContainer.append(dialog);

        // Mark this dialog as displayed
        displayedDialogs.add(pickupId);
    }

    // Event delegation for dialog buttons (Confirm, Cancel, Replay)
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

    // Function to confirm a pickup
    function confirmPickup(pickupId, button) {
        console.log(`Confirming pickup ID: ${pickupId}`); // Debug log

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=confirm&pickup_id=${encodeURIComponent(pickupId)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => { throw new Error('Server did not return JSON: ' + text); });
            }
        })
        .then(data => {
            console.log('Phản hồi:', data);

            if (data.status === 'success') {
                const statusElement = $(`#status_${pickupId}`);
                if (statusElement.length) {
                    statusElement.text('Đã đón');
                }
                // Unlock corresponding checkbox
                if (data.student_id) { // Assuming PHP returns 'student_id'
                    const checkbox = $(`#studentCheckboxes input[type="checkbox"][value="${data.student_id}"]`);
                    if (checkbox.length) {
                        checkbox.prop('disabled', false);
                    }
                }
                // Remove dialog box
                const dialogBox = button.closest('.dialog-box');
                if (dialogBox.length) {
                    dialogBox.remove();
                    // Remove from displayedDialogs
                    displayedDialogs.delete(pickupId);
                } else {
                    console.error('Không tìm thấy dialog box để loại bỏ.');
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

    // Function to cancel a pickup
    function cancelPickup(pickupId, button) {
        console.log(`Cancelling pickup ID: ${pickupId}`); // Debug log

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=cancel&pickup_id=${encodeURIComponent(pickupId)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => { throw new Error('Server did not return JSON: ' + text); });
            }
        })
        .then(data => {
            console.log('Phản hồi:', data);

            if (data.status === 'success') {
                // Remove dialog box
                const dialogBox = button.closest('.dialog-box');
                if (dialogBox.length) {
                    dialogBox.remove();
                    // Remove from displayedDialogs
                    displayedDialogs.delete(pickupId);
                } else {
                    console.error('Không tìm thấy dialog box để loại bỏ.');
                }

                // Update status in history table
                const statusCell = $(`#status_${pickupId}`);
                if (statusCell.length) {
                    statusCell.text('Đã hủy');
                }

                // Unlock corresponding checkbox
                if (data.student_id) { // Assuming PHP returns 'student_id'
                    const checkbox = $(`#studentCheckboxes input[type="checkbox"][value="${data.student_id}"]`);
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

    $(document).on('click', '.replay-btn', function(event) {
        event.preventDefault();
        const button = $(this);
        const pickupId = button.data('pickup-id');

        replayPickup(pickupId, button);
    });

    
    function replayPickupInDialog(pickupId, button) {
        console.log(`Replaying pickup ID: ${pickupId}`); // Debug log

        const dialogBox = button.closest('.dialog-box');
        if (!dialogBox.length) {
            alert('Không tìm thấy hộp thoại.');
            return;
        }

        const studentName = dialogBox.data('student-name');
        const studentClass = dialogBox.data('student-class');

        console.log('Student Name:', studentName);
        console.log('Student Class:', studentClass);

        const studentId = button.data('student-id'); // Ensure data-student-id is present

        console.log('Student ID:', studentId);

        // Proceed only if studentId is defined
        if (!studentId) {
            console.error('Student ID is undefined.');
            alert('Không thể lấy thông tin học sinh. Vui lòng thử lại.');
            return;
        }

        // Send replay request via AJAX to PHP
        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=replay&pickup_id=${encodeURIComponent(pickupId)}&student_id=${encodeURIComponent(studentId)}&student_name=${encodeURIComponent(studentName)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => { throw new Error('Server did not return JSON: ' + text); });
            }
        })
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
                const row = table.row(`#${pickupId}`);
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
            alert('Đã xảy ra lỗi. Vui lòng thử lại.');
        });
    }

    // Hàm replayPickup đã được cập nhật
    function replayPickup(pickupId, button) {
        console.log(`Replaying pickup ID: ${pickupId}`); // Debug log

        const studentName = button.closest('tr').find('.student-name').data('student-name') || 'Unknown';
        const studentId = button.data('student-id'); // Get student-id from button's data attribute

        console.log('Student Name:', studentName);
        console.log('Student ID:', studentId);

        // Proceed only if studentId is defined
        if (!studentId) {
            console.error('Student ID is undefined.');
            alert('Không thể lấy thông tin học sinh. Vui lòng thử lại.');
            return;
        }

        // Send replay request via AJAX to PHP
        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=replay&pickup_id=${encodeURIComponent(pickupId)}&student_id=${encodeURIComponent(studentId)}&student_name=${encodeURIComponent(studentName)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => { throw new Error('Server did not return JSON: ' + text); });
            }
        })
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
                const row = table.row(`#${pickupId}`);
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

    // Hàm initializeReplayCountdown đã được cập nhật
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
                button.html(`Phát lại (${minutes}:${seconds < 10 ? '0' : ''}${seconds})`);
            }
        }

        // Initialize countdown immediately
        updateReplayCountdown();

        // Update every second
        const interval = setInterval(updateReplayCountdown, 1000);

        // Save interval ID so it can be cleared later if needed
        button.data('interval', interval);
    }


    // Function to initialize all replay countdowns
    function initializeReplayCountdowns() {
        const replayButtons = $('.replay-btn[disabled]');
        replayButtons.each(function() {
            const button = $(this);
            const deadline = parseInt(button.attr('data-deadline'), 10);

            if (isNaN(deadline) || deadline <= 0) {
                button.prop('disabled', false).html('<i class="fas fa-redo"></i> Phát lại');
                return;
            }

            initializeReplayCountdown(button, deadline);
        });
    }

    // Function to initialize countdown for pickup expiration
    function initializeCountdown(element, endtime, pickupId) {
        let interval; // Declare 'interval' first

        function updateCountdown() {
            const t = getTimeRemaining(endtime);
            if (t.total <= 0) {
                clearInterval(interval);
                element.text('Đã hết hạn');

                // Show checkbox and delete button if needed
                var row = table.row(`#${pickupId}`);
                if (row.node()) {
                    // Add checkbox if not already present
                    var checkboxCell = $(row.node()).find('td').eq(0);
                    if ($.trim(checkboxCell.html()) === '') {
                        checkboxCell.html(`<input type='checkbox' class='row-checkbox' data-pickup-id='${pickupId}'>`);
                    }

                    // Add delete button if not already present
                    var actionCell = $(row.node()).find('td').last();
                    if (actionCell.find('.delete-btn').length === 0) {
                        var deleteBtn = `<button class='btn btn-sm btn-danger delete-btn' data-pickup-id='${pickupId}'><i class='fas fa-trash-alt'></i> Xóa</button>`;
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
        interval = setInterval(updateCountdown, 1000);
        element.data('interval', interval);
    }

    // Function to calculate time remaining until endtime
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

    function fetchHistory() {
        $.ajax({
            url: 'get_history.php', // Ensure this file exists and returns JSON in the correct format
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    table.clear(); // Clear existing data

                    if (response.history.length === 0) {
                        // Let DataTables handle the empty table message
                        table.draw(false);
                        // Also remove all dialogs as there are no pending pickups
                        dialogContainer.empty();
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
    // Function to initialize all countdowns for pickup expiration
    function initializeAllCountdowns() {
        $('[id^="countdown_"]').each(function() {
            const element = $(this);
            const pickupId = element.attr('id').replace('countdown_', '');
            const expirationTime = element.data('expiration');

            initializeCountdown(element, expirationTime, pickupId);
        });
    }

    // Function to synchronize dialogs
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
                displayedDialogs.delete(parseInt(id));
            }
        });

        // Re-initialize replay countdowns for newly added dialogs
        initializeReplayCountdowns();
    }

    // Call fetchHistory on page load
    fetchHistory();
    initializeAllCountdowns();
    initializeReplayCountdowns();

    // Update history every 5 seconds
    setInterval(fetchHistory, 5000);


    // Function to fetch pickup history via AJAX
    function fetchHistory() {
        $.ajax({
            url: 'get_history.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    table.clear(); // Xóa dữ liệu cũ
    
                    if (response.history.length === 0) {
                        table.draw(false);
                        dialogContainer.empty();
                        displayedDialogs.clear();
                    } else {
                        response.history.forEach(function(pickup) {
                            addToHistory(pickup);
                        });
                        table.draw(false);
                        initializeAllCountdowns();
                        initializeReplayCountdowns();
                        synchronizeDialogs(response.history);
                    }
    
                    // Cập nhật trạng thái của các checkbox
                    updateStudentCheckboxes(response.disabled_students);
    
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
    function updateStudentCheckboxes(disabledStudents) {
        const disabledIds = disabledStudents.map(id => parseInt(id));
    
        $('#studentCheckboxes input[type="checkbox"]').each(function() {
            const checkbox = $(this);
            const studentId = parseInt(checkbox.val());
    
            if (disabledIds.includes(studentId)) {
                checkbox.prop('disabled', true);
                checkbox.prop('checked', false);
            } else {
                checkbox.prop('disabled', false);
            }
        });
    }
    

    // Call fetchHistory on page load
    fetchHistory();
    initializeAllCountdowns();
    initializeReplayCountdowns();

    // Update history every 5 seconds
    setInterval(fetchHistory, 5000);

    // Handle "Select All" checkbox
    const selectAllCheckbox = $('#selectAll');
    selectAllCheckbox.on('change', function() {
        const isChecked = $(this).is(':checked');
        // Only select checkboxes that are visible and not disabled (excluding Pending)
        $('.row-checkbox:visible').prop('checked', isChecked);
        toggleDeleteSelectedBtn();
    });

    // When any row checkbox changes, update the "Select All" checkbox state
    $(document).on('change', '.row-checkbox', function() {
        const totalCheckboxes = $('.row-checkbox:visible').length;
        const checkedCheckboxes = $('.row-checkbox:checked').length;
        selectAllCheckbox.prop('checked', totalCheckboxes === checkedCheckboxes);
        toggleDeleteSelectedBtn();
    });

    // Function to show or hide the "Delete Selected" button
    const deleteSelectedBtn = $('#deleteSelectedBtn');
    function toggleDeleteSelectedBtn() {
        if ($('.row-checkbox:checked').length > 0) {
            deleteSelectedBtn.show();
        } else {
            deleteSelectedBtn.hide();
        }
    }

    // Handle bulk delete button clicks
    $('#deleteSelectedBtn, #deleteAllBtn').on('click', function() {
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
            url: 'index.php', // Path to your PHP script handling deletion
            method: 'POST',
            data: {
                action: 'delete_pickups',
                pickup_ids: selectedPickups
            },
            success: function(response) {
                if (response.status === 'success') {
                    selectedPickups.forEach(function(id) {
                        // Remove the row from DataTable
                        table.row(`#${id}`).remove();

                        // Also remove the corresponding dialog box if exists
                        $(`#dialog_${id}`).remove();
                        displayedDialogs.delete(id);
                    });
                    table.draw(false);
                    alert('Đã xóa ' + selectedPickups.length + ' mục thành công.');
                    selectAllCheckbox.prop('checked', false);
                    toggleDeleteSelectedBtn();

                    // Check if there are no more pickups and let DataTables handle the empty message
                    if (table.rows().count() === 0) {
                        table.draw(false);
                        dialogContainer.empty();
                        displayedDialogs.clear();
                    }
                } else {
                    alert('Lỗi: ' + response.message);
                }
            },
            error: function() {
                alert('Đã xảy ra lỗi khi xóa các mục.');
            }
        });
    });

    // Handle individual delete button clicks
    $(document).on('click', '.delete-btn', function() {
        const pickupId = $(this).data('pickup-id');
        if (!confirm('Bạn có chắc chắn muốn xóa mục này?')) {
            return;
        }

        // Send AJAX request to delete the individual pickup
        $.ajax({
            url: 'index.php',
            method: 'POST',
            data: {
                action: 'delete_pickups',
                pickup_ids: [pickupId]
            },
            success: function(response) {
                if (response.status === 'success') {
                    // Remove the row from DataTable
                    table.row(`#${pickupId}`).remove().draw(false);
                    alert('Đã xóa mục thành công.');

                    // Also remove the corresponding dialog box if exists
                    $(`#dialog_${pickupId}`).remove();
                    displayedDialogs.delete(pickupId);

                    // Check if there are no more pickups and let DataTables handle the empty message
                    if (table.rows().count() === 0) {
                        table.draw(false);
                        dialogContainer.empty();
                        displayedDialogs.clear();
                    }
                } else {
                    alert('Lỗi: ' + response.message);
                }
            },
            error: function() {
                alert('Đã xảy ra lỗi khi xóa mục.');
            }
        });
    });

    // Function to format cooldown time as "MM:SS"
    function formatCooldown(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds}`;
    }

    // Function to calculate time remaining until endtime
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

    // Function to check if the table is empty and reload the page
    function checkAndReloadIfEmpty() {
        if (table.rows().count() === 0) {
            // Reload the page after a short delay to ensure UI updates
            setTimeout(() => {
                location.reload();
            }, 1000); // 1-second delay
        }
    }

    /**
     * synchronizeDialogs Function
     * This function ensures that the dialog boxes in the UI are in sync with the current
     * pending pickups from the server. It adds new dialogs for new pickups and removes
     * dialogs for pickups that are no longer pending.
     *
     * @param {Array} history - Array of pickup objects from the server.
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
        const pickupsToAdd = pendingPickupIds.filter(id => !currentDialogIds.includes(id));

        // Find pickups that need to be removed from dialogs
        const pickupsToRemove = currentDialogIds.filter(id => !pendingPickupIds.includes(id));

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

    // Function to fetch pickup history via AJAX
    function fetchHistory() {
        $.ajax({
            url: 'get_history.php', // Ensure this file exists and returns JSON in the correct format
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    table.clear(); // Clear existing data

                    if (response.history.length === 0) {
                        // Let DataTables handle the empty table message
                        table.draw(false);
                        // Also remove all dialogs as there are no pending pickups
                        dialogContainer.empty();
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

    // Call fetchHistory on page load
    fetchHistory();
    initializeAllCountdowns();
    initializeReplayCountdowns();

    // Update history every 5 seconds
    setInterval(fetchHistory, 5000);
});
