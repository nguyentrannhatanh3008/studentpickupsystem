document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector("#btn");
    const sidebar = document.querySelector(".sidebar");
    const dropdownToggle = document.querySelector('.nav-link.dropdown-toggle');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    let studentCheckboxes = document.querySelectorAll('#studentCheckboxes input[type="checkbox"]');
    const autoFillContainer = document.getElementById('autoFillContainer');
    const pickupForm = document.getElementById('pickupForm');
    const historyTableBody = document.getElementById('pickupHistoryBody');
    const dialogContainer = document.getElementById('dialogContainer');

    // Load sidebar state from localStorage
    if (localStorage.getItem('sidebarState') === 'active') {
        sidebar.classList.add("active");
    }

    // Handle sidebar toggle and save state
    if (btn) {
        btn.addEventListener('click', function () {
            sidebar.classList.toggle("active");
            // Save sidebar state to localStorage
            if (sidebar.classList.contains("active")) {
                localStorage.setItem('sidebarState', 'active');
            } else {
                localStorage.removeItem('sidebarState');
            }
        });
    }

    // Handle dropdown toggle
    if (dropdownToggle) {
        dropdownToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation(); // Prevent event bubbling
            dropdownMenu.classList.toggle('show');
        });
    }

    // Close dropdown when clicking outside
    window.addEventListener('click', function(e) {
        if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
        }
    });
    
    // Handle checkbox changes for students
    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            autoFillContainer.innerHTML = ''; // Clear container

            // Convert NodeList to Array and filter selected checkboxes
            const selectedStudents = Array.from(studentCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => ({
                    id: cb.value,
                    name: cb.getAttribute('data-name'),
                    class: cb.getAttribute('data-class')
                }));

            // Update auto-fill container with selected students
            selectedStudents.forEach(student => {
                const autoFillBox = document.createElement('div');
                autoFillBox.className = 'auto-fill-box';
                autoFillBox.innerHTML = `
                    <p><strong>Tên:</strong> ${student.name}</p>
                    <p><strong>Lớp:</strong> ${student.class}</p>
                    <p><strong>Thời gian:</strong> ${new Date().toLocaleString()}</p>
                `;
                autoFillContainer.appendChild(autoFillBox);
            });
        });
    });

    // Handle form submission for pickup registration
    if (pickupForm) {
        pickupForm.addEventListener('submit', function (event) {
            event.preventDefault(); // Prevent default form submission

            const selectedStudents = [];
            studentCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedStudents.push(checkbox.value);
                }
            });

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
            .then(response => response.text())
            .then(text => {
                console.log('Phản hồi thô:', text); // Log raw response

                try {
                    const data = JSON.parse(text);
                    if (data.status === 'success') {
                        data.pickups.forEach(item => {
                            addToHistory(item);
                            createDialog(item.student_name, item.class, item.created_at, item.pickup_id);
                        });

                        // Uncheck and disable only the selected checkboxes
                        selectedStudents.forEach(id => {
                            const checkbox = document.querySelector(`#studentCheckboxes input[type="checkbox"][value="${id}"]`);
                            if (checkbox) {
                                checkbox.checked = false;
                                checkbox.disabled = true; // Disable only this checkbox
                            }
                        });

                        // Clear auto-fill container
                        autoFillContainer.innerHTML = '';

                        alert('Đăng ký đón thành công!');
                    } else {
                        alert(`Lỗi: ${data.message}`);
                    }
                } catch (error) {
                    console.error('JSON không hợp lệ:', text);
                    alert(`Định dạng JSON không hợp lệ từ máy chủ: ${error.message}`);
                }
            })
            .catch(error => {
                console.error('Đã xảy ra lỗi:', error);
                alert(`Đã xảy ra lỗi với yêu cầu: ${error.message}`);
            });
        });
    }

    // Function to add a new row to the pickup history table
    function addToHistory(data) {
        // Remove 'No History' message if it exists
        const noHistoryMessage = document.querySelector('.no-history-message');
        if (noHistoryMessage) {
            noHistoryMessage.parentElement.remove();
        }
    
        const newRow = document.createElement('tr');
        newRow.id = `row_${data.pickup_id}`;
        newRow.innerHTML = `
            <td><input type='checkbox' class='row-checkbox' data-pickup-id='${data.pickup_id}'></td>
            <td>${data.student_name}</td>
            <td>${data.class}</td>
            <td>${data.created_at}</td>
            <td id="status_${data.pickup_id}">Chờ xử lý</td>
            <td><button class='btn btn-sm btn-danger delete-btn' data-pickup-id='${data.pickup_id}'><i class='fas fa-trash-alt'></i></button></td>
        `;
        historyTableBody.prepend(newRow);
    }

    // Function to create a new dialog box
    function createDialog(name, studentClass, time, pickupId) {
        const dialog = document.createElement('div');
        dialog.className = 'dialog-box';
        const formattedTime = time;
        dialog.innerHTML = `
            <h2>Thông tin đón con</h2>
            <p><strong>Tên:</strong> ${name}</p>
            <p><strong>Lớp:</strong> ${studentClass}</p>
            <p><strong>Thời gian:</strong> ${formattedTime}</p>
            <p><strong>Trạng thái:</strong> <span id="dialog_status_${pickupId}" style="color: orange;">Chờ xử lý</span></p>
            <button type="button" data-pickup-id="${pickupId}" class="confirm-btn btn btn-success">Xác nhận</button>
            <button type="button" data-pickup-id="${pickupId}" class="cancel-btn btn btn-warning">Hủy</button>
        `;
        dialogContainer.appendChild(dialog);

        // No need to update checkbox state globally since server đã khóa checkbox
    }

    // Event delegation for confirm and cancel buttons within dialog boxes
    dialogContainer.addEventListener('click', function (event) {
        const target = event.target;
        if (target.classList.contains('confirm-btn')) {
            event.preventDefault();
            confirmPickup(target.dataset.pickupId, target);
        } else if (target.classList.contains('cancel-btn')) {
            event.preventDefault();
            cancelPickup(target.dataset.pickupId, target);
        }
    });

    // Function to confirm a pickup
    function confirmPickup(pickupId, button) {
        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=confirm&pickup_id=${encodeURIComponent(pickupId)}`
        })
        .then(response => response.text())
        .then(text => {
            console.log('Phản hồi thô:', text);
    
            try {
                const data = JSON.parse(text);
                if (data.status === 'success') {
                    const statusElement = document.getElementById(`status_${pickupId}`);
                    if (statusElement) {
                        statusElement.innerText = 'Đã đón';
                    } else {
                        console.error(`Không tìm thấy phần tử trạng thái với ID status_${pickupId}.`);
                    }
                    // Mở khóa checkbox tương ứng
                    const checkbox = document.querySelector(`#studentCheckboxes input[type="checkbox"][value="${data.student_id}"]`);
                    if (checkbox) {
                        checkbox.disabled = false;
                    }
                    // Loại bỏ dialog box
                    removeDialog(button.closest('.dialog-box'));
                } else {
                    alert(`Lỗi: ${data.message}`);
                }
            } catch (error) {
                console.error('JSON không hợp lệ:', text);
                alert(`Định dạng JSON không hợp lệ từ máy chủ: ${error.message}`);
            }
        })
        .catch(error => {
            console.error('Lỗi:', error);
            alert('Đã xảy ra lỗi. Vui lòng thử lại.');
        });
    }
    
    function cancelPickup(pickupId, button) {
        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=cancel&pickup_id=${encodeURIComponent(pickupId)}`
        })
        .then(response => response.text())
        .then(text => {
            console.log('Phản hồi thô:', text);
    
            try {
                const data = JSON.parse(text);
                if (data.status === 'success') {
                    // Loại bỏ dialog box
                    const dialogBox = button.closest('.dialog-box');
                    if (dialogBox) {
                        dialogBox.remove();
                    }
    
                    // Cập nhật trạng thái trong bảng lịch sử
                    const statusCell = document.getElementById(`status_${pickupId}`);
                    if (statusCell) {
                        statusCell.textContent = 'Đã hủy';
                    }
    
                    // Mở khóa checkbox tương ứng
                    const checkbox = document.querySelector(`#studentCheckboxes input[type="checkbox"][value="${data.student_id}"]`);
                    if (checkbox) {
                        checkbox.disabled = false;
                        checkbox.checked = false;
                    }
                } else {
                    alert(`Lỗi: ${data.message}`);
                }
            } catch (error) {
                console.error('JSON không hợp lệ:', text);
                alert(`Định dạng JSON không hợp lệ từ máy chủ: ${error.message}`);
            }
        })
        .catch(error => {
            console.error('Lỗi:', error);
            alert('Đã xảy ra lỗi. Vui lòng thử lại.');
        });
    }
    
    // Function to remove a dialog box
    function removeDialog(dialogElement) {
        dialogElement.remove();
        // No need to update checkbox state globally since server đã khóa checkbox
    }


    // Handle checkbox and delete button interactions in the pickup history table
    const deleteSelectedBtn = $('#deleteSelectedBtn');
    const selectAllCheckbox = $('#selectAll');
    const deleteAllBtn = $('#deleteAllBtn');
    const pickupHistoryBody = $('#pickupHistoryBody');
    const noHistoryRow = $('#noHistoryRow');

    // Handle "Select All" checkbox
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
                        $('#row_' + id).remove();
                    });
                    alert('Đã xóa ' + selectedPickups.length + ' mục thành công.');
                    selectAllCheckbox.prop('checked', false);
                    toggleDeleteSelectedBtn();

                    // Check if there are no more pickups and show "No History" message
                    if ($('.row-checkbox').length === 0) {
                        pickupHistoryBody.append('<tr id="noHistoryRow"><td colspan="6" class="no-history-message">Không tìm thấy lịch sử.</td></tr>');
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
                    $('#row_' + pickupId).remove();
                    alert('Đã xóa mục thành công.');

                    // Check if there are no more pickups and show "No History" message
                    if ($('.row-checkbox').length === 0) {
                        pickupHistoryBody.append('<tr id="noHistoryRow"><td colspan="6" class="no-history-message">Không tìm thấy lịch sử.</td></tr>');
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
    
});
