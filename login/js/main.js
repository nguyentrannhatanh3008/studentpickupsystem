const inputs = document.querySelectorAll('.input');

function focusFunc() {
    let parent = this.parentNode.parentNode;
    parent.classList.add('focus');
}

function blurFunc() {
    let parent = this.parentNode.parentNode;
    if (this.value == "") {
        parent.classList.remove('focus');
    }
}

inputs.forEach(input => {
    input.addEventListener('focus', focusFunc);
    input.addEventListener('blur', blurFunc);
});

var modal = document.getElementById("modal-terms");
var btn = document.getElementById("action-modal");
var span = document.getElementsByClassName("close")[0];

btn.onclick = function () {
    modal.style.display = "block";
}

span.onclick = function () {
    modal.style.display = "none";
}

window.onclick = function (event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

// Thêm sự kiện cho form login
document.getElementById('loginForm').addEventListener('submit', function(event) {
    event.preventDefault();  // Dừng việc gửi form mặc định
    var username = document.querySelector('[name="username"]').value;
    var password = document.querySelector('[name="password"]').value;
    
    if (username && password) {  // Kiểm tra thông tin đăng nhập
        // Giả sử thông tin đăng nhập đúng, chuyển hướng tới index.php
        window.location.href = 'index.php';  
    } else {
        alert("Please enter both username and password.");
    }
});

document.getElementById('sidebarToggle').addEventListener('click', function() {
    var sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed');

    if (sidebar.classList.contains('collapsed')) {
        this.style.color = '#fff'; // Thay đổi màu khi thu gọn
    } else {
        this.style.color = '#db4b23'; // Màu ban đầu khi mở rộng
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var loginButton = document.getElementById('loginBtn');
    var registerButton = document.getElementById('registerBtn');

    // Chuyển đến trang login khi click
    loginButton.addEventListener('click', function(event) {
        event.preventDefault();  
        window.location.href = 'login.html';  
    });

    // Chuyển đến trang register khi click
    registerButton.addEventListener('click', function(event) {
        event.preventDefault();  
        window.location.href = 'register.html';  
    });
});

document.addEventListener('DOMContentLoaded', function() {
    function navigateTo(url) {
        console.log("Navigating to:", url); 
        window.location.href = url; 
    }

    document.querySelector('.sidebar a[href="index.html"]').addEventListener('click', function(event) {
        event.preventDefault(); 
        navigateTo('index.html'); 
    });

    document.querySelector('.sidebar a[href="notification.html"]').addEventListener('click', function(event) {
        event.preventDefault(); 
        navigateTo('notification.html'); 
    });

    document.querySelector('.sidebar a[href="profile.html"]').addEventListener('click', function(event) {
        event.preventDefault(); 
        navigateTo('profile.html'); 
    });
});
const input = document.querySelectorAll('.input');

function focusFunc() {
    let parent = this.parentNode.parentNode;
    parent.classList.add('focus');
}

function blurFunc() {
    let parent = this.parentNode.parentNode;
    if (this.value == "") {
        parent.classList.remove('focus');
    }
}

inputs.forEach(input => {
    input.addEventListener('focus', focusFunc);
    input.addEventListener('blur', blurFunc);
});
document.addEventListener('DOMContentLoaded', function() {
    // Modal functionality for terms of use
    var modal = document.getElementById("modal-terms");
    var btn = document.getElementById("action-modal");
    var span = document.getElementsByClassName("close")[0];

    btn.onclick = function () {
        modal.style.display = "block";
    }

    span.onclick = function () {
        modal.style.display = "none";
    }

    window.onclick = function (event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Form submission validation
    document.querySelector('form').addEventListener('submit', function(event) {
        var gender = document.querySelector('[name="gender"]').value;
        var phone = document.querySelector('[name="phone"]').value;
        
        // Simple validation checks
        if (gender === "") {
            alert("Please select a gender.");
            event.preventDefault();
        }

        // Additional checks for phone number (if any)
        if (phone && isNaN(phone)) {
            alert("Phone number should only contain digits.");
            event.preventDefault();
        }
    });
});