$(document).ready(function() {
    // Khởi tạo DataTable
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
        // Get student data from data attributes
        const studentId = $(this).data('student-id');
        const studentName = $(this).data('student-name');
        const studentClass = $(this).find('td:nth-child(3)').text();

        // Update modal content
        $('#studentName').text(studentName);
        $('#studentClass').text(studentClass);

        // Set the QR code image src to get_qr.php
        const qrCodeURL = `get_qr.php?student_id=${studentId}`;

        // Set the QR code image src
        $('#studentQRCode').attr('src', qrCodeURL);

        // Show the modal
        $('#studentDetailsModal').modal('show');
    });

    // Sidebar toggle
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

    setInterval(function(){
        location.reload();
    }, 5000);
});
