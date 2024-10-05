$(document).ready(function() {
    const btn = $("#btn");
    const sidebar = $(".sidebar");
    const dropdownToggle = $('.nav-link.dropdown-toggle');
    const dropdownMenu = $('.dropdown-menu');

    // Toggle sidebar on button click
    if (btn.length) {
        btn.on('click', function () {
            sidebar.toggleClass("active");
        });
    }

    // Toggle dropdown menu on dropdownToggle click
    if (dropdownToggle.length) {
        dropdownToggle.on('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            dropdownMenu.toggleClass('show');
        });
    }

    // Close dropdown when clicking outside
    $(window).on('click', function(e) {
        if (!dropdownToggle.is(e.target) && dropdownToggle.has(e.target).length === 0 &&
            !dropdownMenu.is(e.target) && dropdownMenu.has(e.target).length === 0) {
            dropdownMenu.removeClass('show');
        }
    });

    // Initialize DataTables for the Student List Table
    $('#studentListTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "language": {
            "lengthMenu": "Hiển thị _MENU_ mục trên mỗi trang",
            "zeroRecords": "Không tìm thấy dữ liệu phù hợp",
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
        "initComplete": function(settings, json) {
            // Assign custom classes to search and pagination elements
            $('.dataTables_filter').addClass('datatable-search');
            $('.dataTables_paginate').addClass('datatable-pagination');
            $('#studentListTable').addClass('datatable'); // Assign .datatable to the table
        }
    });

    // Handle click event on student rows
    $('#studentListTable tbody').on('click', 'tr.student-row', function() {
        const $row = $(this);
        const studentId = $row.data('student-id');

        // Fetch and display student details via AJAX
        $.ajax({
            url: 'fetch_student_details.php', // Ensure this PHP script exists and handles the request
            method: 'POST',
            dataType: 'json',
            data: { student_id: studentId },
            success: function(data) {
                if (data.status === 'success') {
                    // Populate modal with student details
                    $('#studentName').text(data.student.name);
                    $('#studentClass').text(data.student.class);
                    // Show modal
                    $('#studentDetailsModal').modal('show');
                } else {
                    console.error('Error fetching student details:', data.message);
                    alert('Không thể lấy thông tin học sinh.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Đã xảy ra lỗi khi lấy thông tin học sinh.');
            }
        });
    });
});
