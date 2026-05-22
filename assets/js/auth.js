// Free Would - Auth JavaScript

const API_URL = 'backend/auth.php';

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    setTimeout(() => toast.classList.remove('show'), 3500);
}

// Toggle password visibility
function togglePass(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentElement.querySelector('.toggle-password i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Password strength checker
function checkStrength(password) {
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');
    if (!fill || !text) return;

    let score = 0;
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    fill.className = 'strength-fill';
    if (password.length === 0) {
        text.textContent = '';
        return;
    }
    if (score <= 1) {
        fill.classList.add('weak');
        text.textContent = 'Weak';
        text.style.color = '#EF4444';
    } else if (score <= 2) {
        fill.classList.add('medium');
        text.textContent = 'Medium';
        text.style.color = '#F59E0B';
    } else {
        fill.classList.add('strong');
        text.textContent = 'Strong';
        text.style.color = '#10B981';
    }
}

// Set button loading state
function setLoading(btnId, loading) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    if (loading) {
        btn.disabled = true;
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = '<div class="spinner"></div> Processing...';
    } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
    }
}

// Login handler
async function handleLogin(e) {
    e.preventDefault();
    const form = e.target;
    const email = form.email.value.trim();
    const password = form.password.value;

    if (!email || !password) {
        showToast('Please fill in all fields', 'error');
        return;
    }

    setLoading('loginBtn', true);

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', email, password })
        });
        const data = await response.json();

        if (data.success) {
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user_data', JSON.stringify(data.user));
            showToast('Login successful! Redirecting...', 'success');
            setTimeout(() => window.location.href = 'dashboard.html', 1000);
        } else {
            showToast(data.message || 'Login failed', 'error');
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
    }

    setLoading('loginBtn', false);
}

// Register handler
async function handleRegister(e) {
    e.preventDefault();
    const form = e.target;
    const name = form.name.value.trim();
    const email = form.email.value.trim();
    const password = form.password.value;
    const confirmPassword = form.confirmPassword.value;

    if (!name || !email || !password || !confirmPassword) {
        showToast('Please fill in all fields', 'error');
        return;
    }
    if (password !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }
    if (password.length < 8) {
        showToast('Password must be at least 8 characters', 'error');
        return;
    }

    setLoading('registerBtn', true);

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'register', name, email, password })
        });
        const data = await response.json();

        if (data.success) {
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user_data', JSON.stringify(data.user));
            showToast('Account created successfully!', 'success');
            setTimeout(() => window.location.href = 'dashboard.html', 1000);
        } else {
            showToast(data.message || 'Registration failed', 'error');
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
    }

    setLoading('registerBtn', false);
}

// Forgot password handler
async function handleForgotPassword(e) {
    e.preventDefault();
    const email = e.target.email.value.trim();

    if (!email) {
        showToast('Please enter your email', 'error');
        return;
    }

    setLoading('forgotBtn', true);

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'forgot-password', email })
        });
        const data = await response.json();

        if (data.success) {
            showToast('Reset link sent to your email!', 'success');
            e.target.reset();
        } else {
            showToast(data.message || 'Failed to send reset link', 'error');
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
    }

    setLoading('forgotBtn', false);
}

// Reset password handler
async function handleResetPassword(e) {
    e.preventDefault();
    const form = e.target;
    const password = form.password.value;
    const confirmPassword = form.confirmPassword.value;
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');

    if (!password || !confirmPassword) {
        showToast('Please fill in all fields', 'error');
        return;
    }
    if (password !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }
    if (password.length < 8) {
        showToast('Password must be at least 8 characters', 'error');
        return;
    }
    if (!token) {
        showToast('Invalid or missing reset token', 'error');
        return;
    }

    setLoading('resetBtn', true);

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset-password', token, password })
        });
        const data = await response.json();

        if (data.success) {
            showToast('Password reset successfully!', 'success');
            setTimeout(() => window.location.href = 'login.html', 1500);
        } else {
            showToast(data.message || 'Failed to reset password', 'error');
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
    }

    setLoading('resetBtn', false);
}

// Check if already logged in
(function() {
    const token = localStorage.getItem('auth_token');
    if (token && (window.location.pathname.includes('login') || window.location.pathname.includes('register'))) {
        window.location.href = 'dashboard.html';
    }
})();
