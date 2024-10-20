document.addEventListener('DOMContentLoaded', function() {
    const btn = document.querySelector("#btn");
    const sidebar = document.querySelector(".sidebar");
    const dropdownToggle = document.querySelector('.nav-link.dropdown-toggle');
    const dropdownMenu = document.querySelector('.dropdown-menu');

    // Sidebar toggle
    if (btn) {
        btn.addEventListener('click', function() {
            sidebar.classList.toggle("active");
        });
    }

    // Dropdown toggle
    if (dropdownToggle) {
        dropdownToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation(); // Ngăn chặn sự kiện lan truyền
            dropdownMenu.classList.toggle('show');
        });
    }

    // Đóng dropdown khi nhấp bên ngoài
    window.addEventListener('click', function(e) {
        if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
        }
    });
    const updatebtn = document.querySelector('.update-btn');
    
    updatebtn.addEventListener('click', function () {
        // Toggle class when the element is clicked
        updatebtn.classList.toggle('update-btn-clicked');
    });
    setInterval(function(){
        location.reload();
    }, 10000);
});