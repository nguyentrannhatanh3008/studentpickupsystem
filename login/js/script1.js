
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
        dropdownToggle.addEventListener('click', function(event) {
            event.preventDefault();
            dropdownMenu.classList.toggle('show');
        });
    }

    // Close dropdown when clicking outside
    window.addEventListener('click', function(e) {
        if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
        }
    });
});