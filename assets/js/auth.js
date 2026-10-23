

function toggleForms(e, target) {
    if (e && e.preventDefault) e.preventDefault();
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const tabLogin = document.getElementById('tabLoginBtn');
    const tabRegister = document.getElementById('tabRegisterBtn');

    if (target === 'register') {
        if (loginForm) loginForm.classList.remove('active-form');
        if (registerForm) registerForm.classList.add('active-form');
        if (tabLogin) tabLogin.classList.remove('active');
        if (tabRegister) tabRegister.classList.add('active');
        try { history.replaceState(null, null, '#register'); } catch(_) {}
    } else {
        if (registerForm) registerForm.classList.remove('active-form');
        if (loginForm) loginForm.classList.add('active-form');
        if (tabRegister) tabRegister.classList.remove('active');
        if (tabLogin) tabLogin.classList.add('active');
        try { history.replaceState(null, null, '#login'); } catch(_) {}
    }
}

function checkHash() {
    if (window.location.hash === '#register') {
        toggleForms(null, 'register');
    } else if (window.location.hash === '#admin') {
        toggleForms(null, 'login');
        const em = document.querySelector('#loginForm input[name="email"]');
        const pw = document.querySelector('#loginForm input[name="password"]');
        if (em) em.value = 'admin@nsbm.ac.lk';
        if (pw) pw.value = 'password';
    }
}

window.addEventListener('DOMContentLoaded', checkHash);
window.addEventListener('hashchange', checkHash);
