// js/register.js

document.addEventListener('DOMContentLoaded', function () {
    // Forms for each step
    const registrationForm = document.getElementById('registrationForm');
    const currentStepInput = document.getElementById('currentStep');

    // Steps
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const step3 = document.getElementById('step-3');

    // Buttons
    const next1 = document.getElementById('next1');
    const next2 = document.getElementById('next2');
    const next3 = document.getElementById('next3');
    const prev2 = document.getElementById('prev2');
    const prev3 = document.getElementById('prev3');

    // Modal Elements
    const termsModal = document.getElementById('modal-terms');
    const modalBtn = document.getElementById('action-modal');
    const closeButtons = document.querySelectorAll('.close');

    const modals = [termsModal];

    // Function to open a modal
    function openModal(modal) {
        modals.forEach(m => {
            if (m) m.style.display = 'none';
        }); // Close all modals
        if (modal) {
            modal.style.display = 'block';
        }
    }

    // Function to close a modal
    function closeModalFunc(modal) {
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Event listeners for closing modals
    closeButtons.forEach(button => {
        button.addEventListener('click', function () {
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            closeModalFunc(modal);
        });
    });

    // Event listener for opening terms modal
    if (modalBtn) {
        modalBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openModal(termsModal);
        });
    }

    // Event listener to close modals when clicking outside
    window.addEventListener('click', function (e) {
        modals.forEach(modal => {
            if (modal && e.target == modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Validation Functions
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }

    function validatePhone(phone) {
        const re = /^\d{10}$/; // Adjust regex based on your phone number format
        return re.test(String(phone));
    }

    function validatePassword(password) {
        // At least 8 characters, at least one letter and one number
        const re = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
        return re.test(password);
    }

    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // Check for duplicates via AJAX
    async function checkDuplicate(field, value, errorElement) {
        if (value === '') {
            errorElement.textContent = '';
            return false;
        }

        // Show loading indicator
        errorElement.textContent = 'Đang kiểm tra...';

        try {
            const response = await fetch('check_register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `field=${encodeURIComponent(field)}&value=${encodeURIComponent(value)}`
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json();

            if (data.error) {
                errorElement.textContent = data.error;
                return false;
            }

            if (data.exists) {
                errorElement.textContent = `${capitalizeFirstLetter(field)} đã được sử dụng.`;
                return false;
            } else {
                errorElement.textContent = '';
                return true;
            }
        } catch (error) {
            console.error('Error:', error);
            errorElement.textContent = 'Đã xảy ra lỗi. Vui lòng thử lại sau.';
            return false;
        }
    }

    // Event listener for Step 1: Next Button
    if (next1) {
        next1.addEventListener('click', async function () {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();

            const usernameError = document.getElementById('username-error');
            const emailError = document.getElementById('email-error');
            const phoneError = document.getElementById('phone-error');

            let valid = true;

            // Validate username
            if (username === '') {
                usernameError.textContent = 'Vui lòng nhập tên đăng nhập.';
                valid = false;
            } else {
                const usernameUnique = await checkDuplicate('username', username, usernameError);
                if (!usernameUnique) valid = false;
            }

            // Validate email format
            if (!validateEmail(email)) {
                emailError.textContent = 'Vui lòng nhập đúng định dạng email.';
                valid = false;
            } else {
                const emailUnique = await checkDuplicate('email', email, emailError);
                if (!emailUnique) valid = false;
            }

            // Validate phone format
            if (!validatePhone(phone)) {
                phoneError.textContent = 'Vui lòng nhập số điện thoại gồm 10 chữ số.';
                valid = false;
            } else {
                const phoneUnique = await checkDuplicate('phone', phone, phoneError);
                if (!phoneUnique) valid = false;
            }

            if (valid) {
                // Update current step
                currentStepInput.value = 'step1';
                // Submit the form
                registrationForm.submit();
            }
        });
    }

    // Event listener for Step 2: Next Button
    if (next2) {
        next2.addEventListener('click', function () {
            const verificationInputs = document.querySelectorAll('.verification-code-input');
            const verificationError = document.getElementById('verification-error');
            let verificationCode = '';

            verificationInputs.forEach(input => {
                verificationCode += input.value;
            });

            if (verificationCode.length < 6) {
                verificationError.textContent = 'Vui lòng nhập đầy đủ mã xác thực.';
                return;
            } else {
                verificationError.textContent = '';
            }

            // Set the verification code in the hidden input
            document.getElementById('verification_code').value = verificationCode;

            // Update current step
            currentStepInput.value = 'step2';
            // Submit the form
            registrationForm.submit();
        });
    }

    // Handle verification code input
    const verificationInputs = document.querySelectorAll('.verification-code-input');

    verificationInputs.forEach((input, index) => {
        input.addEventListener('input', () => {
            if (input.value.length > 0 && index < verificationInputs.length - 1) {
                verificationInputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && input.value === '' && index > 0) {
                verificationInputs[index - 1].focus();
            }
        });
    });

    // Event listener for Step 2: Previous Button
    if (prev2) {
        prev2.addEventListener('click', function () {
            // Show Step 1 and hide Step 2
            step2.classList.remove('active');
            step1.classList.add('active');
            // Update URL hash
            window.location.hash = 'step-1';
        });
    }

    // Event listener for Step 3: Previous Button
    if (prev3) {
        prev3.addEventListener('click', function () {
            // Show Step 2 and hide Step 3
            step3.classList.remove('active');
            step2.classList.add('active');
            // Update URL hash
            window.location.hash = 'step-2';
        });
    }

    // Toggle password visibility in Step 3
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordError = document.getElementById('password-error');
    const confirmPasswordError = document.getElementById('confirm-password-error');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
            this.classList.toggle('fa-eye');
        });
    }

    if (toggleConfirmPassword && confirmPasswordInput) {
        toggleConfirmPassword.addEventListener('click', function () {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
            this.classList.toggle('fa-eye');
        });
    }

    // Event listener for Step 3: Next Button (Final Submit)
    if (next3) {
        next3.addEventListener('click', function () {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const terms = document.getElementById('terms').checked;
            const roleDisplay = document.getElementById('display_role').value;
            const roleHidden = document.getElementById('role').value;
            const schoolCode = document.getElementById('school_code').value.trim();

            let valid = true;

            // Validate password
            if (!validatePassword(password)) {
                passwordError.textContent = 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm cả chữ cái và số.';
                valid = false;
            } else {
                passwordError.textContent = '';
            }

            // Validate password confirmation
            if (password !== confirmPassword) {
                confirmPasswordError.textContent = 'Mật khẩu và xác nhận mật khẩu không khớp.';
                valid = false;
            } else {
                confirmPasswordError.textContent = '';
            }

            // Validate terms acceptance
            if (!terms) {
                alert('Bạn phải đồng ý với điều khoản sử dụng.');
                valid = false;
            }

            // Additional client-side validation based on role and school code
            if ((roleHidden === 'GiaoVien' || roleHidden === 'QuanLyNhaTruong') && schoolCode === '') {
                alert('Vui lòng nhập Mã Trường.');
                valid = false;
            }

            if (valid) {
                // Update current step
                currentStepInput.value = 'step3';
                // Submit the form
                registrationForm.submit();
            }
        });
    }

    // Show/hide school code input based on role
    const roleInput = document.getElementById('role');
    const schoolCodeDiv = document.getElementById('school-code-div');

    function updateSchoolCodeVisibility() {
        if (roleInput) {
            const role = roleInput.value;
            if (role === 'GiaoVien' || role === 'QuanLyNhaTruong') {
                schoolCodeDiv.style.display = 'flex';
            } else {
                schoolCodeDiv.style.display = 'none';
            }
        }
    }

    // Call on page load
    updateSchoolCodeVisibility();

    // Handle hash-based navigation on page load
    function handleHash() {
        const hash = window.location.hash;
        switch (hash) {
            case '#step-1':
                step1.classList.add('active');
                step2.classList.remove('active');
                step3.classList.remove('active');
                break;
            case '#step-2':
                step1.classList.remove('active');
                step2.classList.add('active');
                step3.classList.remove('active');
                break;
            case '#step-3':
                step1.classList.remove('active');
                step2.classList.remove('active');
                step3.classList.add('active');
                break;
            default:
                // Show Step 1 by default
                step1.classList.add('active');
                step2.classList.remove('active');
                step3.classList.remove('active');
                break;
        }
    }

    // Call handleHash on page load
    handleHash();

    // Listen for hash changes
    window.addEventListener('hashchange', handleHash);

    // Real-time duplicate checking for username, email, and phone
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const nameInput = document.querySelector('input[name="name"]');
    const nameError = document.getElementById('name-error'); // Đảm bảo phần tử này tồn tại trong HTML
    
    // Hàm kiểm tra hợp lệ cho "Họ và Tên"
    function validateName() {
        const nameValue = nameInput.value.trim();
    
        // Kiểm tra xem trường có bị bỏ trống không
        if (nameValue === '') {
            nameError.textContent = 'Vui lòng nhập họ và tên.';
            return false;
        }
    
        // Kiểm tra xem có ít nhất hai từ không (họ và tên)
        const nameParts = nameValue.split(' ').filter(part => part !== '');
        if (nameParts.length < 2) {
            nameError.textContent = 'Vui lòng nhập đầy đủ họ và tên.';
            return false;
        }
    
        // Nếu hợp lệ, xóa thông báo lỗi
        nameError.textContent = '';
        return true;
    }
    
    // Thêm sự kiện khi người dùng rời khỏi trường nhập "Họ và Tên"
    nameInput.addEventListener('blur', validateName);
    
    // (Tuỳ chọn) Kiểm tra khi nhấn nút "Tiếp Theo" ở Bước 1
    const next1Button = document.getElementById('next1');
    next1Button.addEventListener('click', async function () {
        // Kiểm tra hợp lệ cho "Họ và Tên"
        const isNameValid = validateName();
        
        if (isNameValid) {
            // Nếu hợp lệ, thực hiện các kiểm tra khác hoặc tiếp tục đăng ký
            // Ví dụ: Kiểm tra các trường khác như username, email, phone
            // ...
            
            // Nếu tất cả hợp lệ, gửi form
            registrationForm.submit();
        }
    });

    if (usernameInput) {
        usernameInput.addEventListener('blur', async function () {
            const username = this.value.trim();
            const usernameError = document.getElementById('username-error');
            if (username !== '') {
                await checkDuplicate('username', username, usernameError);
            } else {
                usernameError.textContent = 'Vui lòng nhập tên đăng nhập.';
            }
        });
    }

    if (emailInput) {
        emailInput.addEventListener('blur', async function () {
            const email = this.value.trim();
            const emailError = document.getElementById('email-error');
            if (validateEmail(email)) {
                await checkDuplicate('email', email, emailError);
            } else {
                emailError.textContent = 'Vui lòng nhập đúng định dạng email.';
            }
        });
    }

    if (phoneInput) {
        phoneInput.addEventListener('blur', async function () {
            const phone = this.value.trim();
            const phoneError = document.getElementById('phone-error');
            if (validatePhone(phone)) {
                await checkDuplicate('phone', phone, phoneError);
            } else {
                phoneError.textContent = 'Vui lòng nhập số điện thoại gồm 10 chữ số.';
            }
        });
    }

    // Smooth scrolling to the active step
    function scrollToActiveStep() {
        const activeStep = document.querySelector('.form-step.active');
        if (activeStep) {
            activeStep.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // Call scrollToActiveStep on hash change
    window.addEventListener('hashchange', scrollToActiveStep);

    // Initial scroll to active step on page load
    scrollToActiveStep();
});
