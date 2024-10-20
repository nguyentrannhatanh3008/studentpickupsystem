$(document).ready(function() {
    const btn = $("#btn");
    const sidebar = $(".sidebar");
    const dropdownToggle = $('.nav-link.dropdown-toggle');
    const dropdownMenu = $('.dropdown-menu');
    const bulkDeleteButton = $("#bulkDeleteButton");
    const headerTrash = $("#headerTrash");
    const selectAllCheckbox = $("#selectAll");

    bulkDeleteButton.html('<i class="fas fa-trash-alt"></i> Xóa');

    // Toggle sidebar on button click
    btn.click(function() {
        sidebar.toggleClass("active");
    });

    // Dropdown toggle
    if (dropdownToggle.length) {
        dropdownToggle.click(function(event) {
            event.preventDefault();
            event.stopPropagation();
            dropdownMenu.toggleClass('show');
        });
    }

    // Close dropdown when clicking outside
    $(window).click(function(e) {
        if (!dropdownToggle.is(e.target) && !dropdownMenu.is(e.target) && dropdownMenu.has(e.target).length === 0) {
            dropdownMenu.removeClass('show');
        }
    });

    console.log('Khởi tạo DataTables...');
    var table = $('#notificationsTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "lengthMenu": [10, 25, 50, 100],
        "pageLength": 10,
        "language": {
            "lengthMenu": "Hiển thị _MENU_ mục trên mỗi trang",
            "zeroRecords": "Bạn không có thông báo nào.",
            "info": "Hiển thị trang _PAGE_ trong tổng số _PAGES_",
            "infoEmpty": "Không có mục nào được hiển thị",
            "infoFiltered": "(lọc từ _MAX_ mục)",
            "search": "Tìm kiếm:",
            "paginate": {
                "first": "Đầu",
                "last": "Cuối",
                "next": "Tiếp",
                "previous": "Trước"
            }
        },
        "responsive": true,
        "autoWidth": false,
        "columnDefs": [
            {
                "targets": 0,
                "orderable": false,
                "searchable": false,
                "className": 'dt-body-center'
            },
            {
                "targets": -1,
                "orderable": false,
                "searchable": false,
                "className": 'dt-body-center'
            },
            {
                "targets": 5,
                "type": "datetime"
            }
        ],
        "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        "initComplete": function(settings, json) {
            $('.dataTables_filter').addClass('datatable-search');
            $('.dataTables_paginate').addClass('datatable-pagination');
            $('#notificationsTable').addClass('datatable');
        }
    });
    console.log('DataTables đã được khởi tạo.');

    // Function để kiểm tra số checkbox được chọn và hiển thị/ẩn nút Xóa
    function toggleBulkDeleteButton() {
        var selectedCount = $('input[type="checkbox"].select-checkbox:checked').length;
        if (selectedCount > 0) {
            bulkDeleteButton.show();
            bulkDeleteButton.html('<i class="fas fa-trash-alt"></i> Xóa (' + selectedCount + ')');
        } else {
            bulkDeleteButton.hide();
            bulkDeleteButton.html('<i class="fas fa-trash-alt"></i> Xóa');
        }
    }

    // Handle individual checkbox click
    $('#notificationsTable tbody').on('change', 'input[type="checkbox"].select-checkbox', function() {
        toggleBulkDeleteButton();

        // Nếu một checkbox bị bỏ chọn, bỏ chọn "Select All" nếu cần
        if (!this.checked) {
            selectAllCheckbox.prop('checked', false);
        } else {
            // Nếu tất cả các checkbox đều được chọn, đánh dấu "Select All" là checked
            var allChecked = $('input[type="checkbox"].select-checkbox').length === $('input[type="checkbox"].select-checkbox:checked').length;
            selectAllCheckbox.prop('checked', allChecked);
        }
    });

    // Handle "Select All" checkbox
    selectAllCheckbox.on('change', function() {
        var isChecked = this.checked;
        $('input[type="checkbox"].select-checkbox').prop('checked', isChecked);
        toggleBulkDeleteButton();
    });

    // Function để xóa các thông báo đã chọn
    function deleteSelectedNotifications(selectedIds, selectedCount) {
        // Gửi yêu cầu AJAX để xóa các thông báo đã chọn
        $.ajax({
            url: 'notification_ajax.php', // URL tới notification_ajax.php
            method: 'POST',
            dataType: 'json',
            data: { delete_ids: selectedIds },
            success: function(data) {
                if (data.status === 'success') {
                    selectedIds.forEach(function(id) {
                        table.row('tr[data-notification-id="' + id + '"]').remove().draw();
                    });

                    // Cập nhật số lượng thông báo
                    let count = parseInt($('#notificationCount').text());
                    $('#notificationCount').text(count - selectedCount);

                    // Ẩn nút Xóa sau khi xóa thành công
                    bulkDeleteButton.hide();
                    bulkDeleteButton.html('<i class="fas fa-trash-alt"></i> Xóa');

                    alert('Xóa thành công ' + selectedCount + ' thông báo đã chọn.');
                    selectAllCheckbox.prop('checked', false);
                } else {
                    console.error('Error deleting notifications:', data.message);
                    alert('Không thể xóa những thông báo đã chọn.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                alert('Đã xảy ra lỗi khi xóa những thông báo đã chọn.');
            }
        });
    }

    // Handle bulk delete button click
    bulkDeleteButton.click(function() {
        var selectedIds = [];
        $('input[type="checkbox"].select-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        var selectedCount = selectedIds.length;

        if (selectedCount === 0) {
            alert('Vui lòng chọn ít nhất một thông báo để xóa.');
            return;
        }

        // Yêu cầu xác nhận với số lượng thông báo được chọn
        if (!confirm('Bạn có chắc chắn muốn xóa ' + selectedCount + ' thông báo đã chọn?')) {
            return;
        }

        // Gọi hàm để xóa các thông báo đã chọn
        deleteSelectedNotifications(selectedIds, selectedCount);
    });

    // Handle click event on header trash icon to trigger bulk delete
    headerTrash.click(function() {
        var selectedIds = [];
        $('input[type="checkbox"].select-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        var selectedCount = selectedIds.length;

        if (selectedCount === 0) {
            alert('Vui lòng chọn ít nhất một thông báo để xóa.');
            return;
        }

        // Yêu cầu xác nhận với số lượng thông báo được chọn
        if (!confirm('Bạn có chắc chắn muốn xóa ' + selectedCount + ' thông báo đã chọn?')) {
            return;
        }

        // Gọi hàm để xóa các thông báo đã chọn
        deleteSelectedNotifications(selectedIds, selectedCount);
    });

    // Handle click event on notification rows using event delegation
    $('#notificationsTable tbody').on('click', 'tr.notification-row', function(e) {
        // Tránh mở modal khi click vào checkbox hoặc nút xóa
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).closest('.delete-notification').length) {
            return;
        }

        const $row = $(this);
        const notificationId = $row.data('notification-id');

        // Đọc các cột dữ liệu
        const title = $row.find('td:nth-child(3)').text();
        const message = $row.find('td:nth-child(4)').text();
        const status = $row.find('td:nth-child(5)').text();
        const sentAt = $row.find('td:nth-child(6)').text();

        // Populate the modal with notification details
        $('#modalNotificationTitle').text(title);
        $('#modalNotificationMessage').text(message);
        $('#modalNotificationTime').text(sentAt);
        $('#modalNotificationStatus').text(status);

        // Enable or disable the "Đã Đọc" button based on status
        if (status.trim() === 'Chưa đọc') { 
            $('#markAsReadButton').prop('disabled', false).show();
        } else {
            $('#markAsReadButton').prop('disabled', true).hide();
        }

        // Store the notification ID in the button's data attribute for later use
        $('#markAsReadButton').data('notification-id', notificationId);

        // Show the modal
        $('#notificationDetailsModal').modal('show');
    });

    // Handle "Đã Đọc" button click
    $('#markAsReadButton').click(function() {
        const notificationId = $(this).data('notification-id');
        const $button = $(this);

        console.log('Notification ID:', notificationId);
        console.log('AJAX URL:', 'notification_ajax.php');

        // Disable the button to prevent multiple clicks
        $button.prop('disabled', true);

        // Send AJAX request to update notification status
        $.ajax({
            url: 'notification_ajax.php', // URL tới notification_ajax.php
            method: 'POST',
            dataType: 'json',
            data: { notification_id: notificationId },
            success: function(data) {
                if (data.status === 'success') {
                    // Update the UI to reflect the new status
                    $('tr.notification-row[data-notification-id="' + notificationId + '"] td:nth-child(5)').text('Đã Đọc');

                    // Update the icon color
                    $('tr.notification-row[data-notification-id="' + notificationId + '"] td:nth-child(2) i')
                        .removeClass('icon-gray')
                        .addClass('icon-orange');

                    // Optionally, update the notification count
                    let count = parseInt($('#notificationCount').text());
                    if (count > 0) {
                        $('#notificationCount').text(count - 1);
                    }

                    // Disable the "Đã Đọc" button as it's already read
                    $button.prop('disabled', true).hide();

                    // Close the modal after a short delay
                    setTimeout(function() {
                        $('#notificationDetailsModal').modal('hide');
                    }, 1000);

                    // Optional: Show a success notification
                    alert('Đánh dấu thông báo là đã đọc thành công.');
                } else {
                    console.error('Error updating notification status:', data.message);
                    alert('Không thể cập nhật trạng thái thông báo.');
                    $button.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                alert('Đã xảy ra lỗi khi cập nhật trạng thái thông báo.');
                $button.prop('disabled', false); // Re-enable the button on error
            }
        });
    });

    // Handle individual delete button click
    $('#notificationsTable tbody').on('click', '.delete-notification', function(e) {
        e.stopPropagation(); // Ngăn không cho sự kiện click lan ra hàng

        var notificationId = $(this).data('id');
        if (!confirm('Bạn có chắc chắn muốn xóa thông báo này?')) {
            return;
        }

        // Send AJAX request to delete the notification
        $.ajax({
            url: 'notification_ajax.php', // URL tới notification_ajax.php
            method: 'POST',
            dataType: 'json',
            data: { delete_ids: [notificationId] }, // Gửi dưới dạng mảng
            success: function(data) {
                if (data.status === 'success') {
                    // Remove the deleted row from DataTable
                    table.row('tr[data-notification-id="' + notificationId + '"]').remove().draw();

                    // Update notification count
                    let count = parseInt($('#notificationCount').text());
                    $('#notificationCount').text(count - 1);

                    alert('Xóa thông báo thành công.');
                } else {
                    console.error('Error deleting notification:', data.message);
                    alert('Không thể xóa thông báo này.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                alert('Đã xảy ra lỗi khi xóa thông báo.');
            }
        });
    });

    // Handle mark all as read
    $('#markAllReadIcon').on('click', function() {
        // Hiển thị modal xác nhận
        $('#confirmMarkAllReadModal').modal('show');
    });

    // Handle confirmation of marking all as read
    $('#markAllReadConfirmBtn').on('click', function() {
        // Gửi yêu cầu AJAX để đánh dấu tất cả đã đọc
        $.ajax({
            url: 'notification_ajax.php',
            type: 'POST',
            data: {
                action: 'mark_all_read'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Cập nhật giao diện: thay đổi trạng thái và màu sắc icon
                    $('.notification-row').each(function() {
                        const statusCell = $(this).find('td').eq(4); // Cột trạng thái
                        const icon = $(this).find('td').eq(1).find('i'); // Cột icon

                        if (statusCell.text().trim() === 'Chưa đọc') {
                            statusCell.text('Đã Đọc');
                            icon.removeClass('icon-gray').addClass('icon-orange');
                        }
                    });

                    // Cập nhật số lượng thông báo
                    let count = parseInt($('#notificationCount').text());
                    $('#notificationCount').text(0);

                    // Đóng modal
                    $('#confirmMarkAllReadModal').modal('hide');

                    alert('Tất cả thông báo đã được đánh dấu là đã đọc.');
                } else {
                    alert('Lỗi: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Lỗi AJAX:', error);
                alert('Đã xảy ra lỗi khi đánh dấu tất cả đã đọc.');
            }
        });
    });

    // Optional: Reload the page every 10 seconds to fetch new notifications
    setInterval(function(){
        location.reload();
    }, 10000);
});
