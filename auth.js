// ==================== FUNCIONES COMPARTIDAS ====================
/**
/**
 * Función para mostrar/ocultar contraseña
 * @param {string} inputId - ID del input de contraseña
 */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const toggle = input.nextElementSibling;
    const eyeIcon = toggle.querySelector('span');
    if (input.type === 'password') {
        input.type = 'text';
        eyeIcon.className = 'closed-eye-icon';
    } else {
        input.type = 'password';
        eyeIcon.className = 'open-eye-icon';
    }
}

/**
 * Muestra feedback durante el envío de un formulario
 * @param {HTMLElement} form - Elemento formulario
 * @param {string} loadingText - Texto a mostrar durante el envío
 * @returns {Object} - Objeto con métodos para restaurar el estado
 */
function showFormLoading(form, loadingText = 'Enviando...') {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) return { reset: () => {} };
    
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = loadingText;
    
    return {
        reset: () => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    };
}

// ==================== MANEJADORES DE FORMULARIOS ====================
/**
 * Configura el manejo del formulario de login
 */
function setupLoginForm() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email')?.value;
            const password = document.getElementById('password')?.value;
            const remember = document.getElementById('remember')?.checked;
            
            // Validaciones básicas
            if (!email || !password) {
                showWarning('Por favor completa todos los campos', 'Campos requeridos');
                return false;
            }
            
            // Validar que sea un email válido
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showWarning('Por favor ingresa un email válido', 'Email inválido');
                return false;
            }
            
            // Mostrar estado de carga
            const loading = showFormLoading(loginForm, 'Iniciando sesión...');
            
            // Crear FormData para enviar los datos
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            if (remember) {
                formData.append('remember', '1');
            }
            
            // Utilidad: fetch con timeout
            const fetchWithTimeout = (resource, options = {}, timeoutMs = 15000) => {
                const controller = new AbortController();
                const id = setTimeout(() => controller.abort(), timeoutMs);
                return fetch(resource, { ...options, signal: controller.signal })
                    .finally(() => clearTimeout(id));
            };

            // Enviar datos al servidor (evitar caché + tolerancia a server lento)
            const url = 'login_process.php?_ts=' + Date.now(); // cache-bypass
            const requestInit = {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            };

            // Intentar hasta 2 veces con backoff si hay timeout/red de red
            const tryRequest = async () => {
                let lastErr;
                for (let attempt = 1; attempt <= 2; attempt++) {
                    try {
                        return await fetchWithTimeout(url, requestInit, 15000);
                    } catch (e) {
                        lastErr = e;
                        // Si fue abort (timeout) o error de red, backoff y reintento
                        await new Promise(r => setTimeout(r, attempt * 800));
                    }
                }
                throw lastErr || new Error('Error de red');
            };

            tryRequest()
            .then(async response => {
                const contentType = response.headers.get('content-type') || '';
                let rawBody;
                let data;
                try {
                    if (contentType.includes('application/json')) {
                        data = await response.json();
                    } else {
                        // Intentar parsear como JSON aunque el header esté mal configurado
                        rawBody = await response.text();
                        try {
                            data = JSON.parse(rawBody);
                        } catch (parseErr) {
                            throw new Error('Respuesta no JSON (' + response.status + '): ' + rawBody.substring(0,300));
                        }
                    }
                } catch (e) {
                    console.error('Fallo parseando respuesta', e, rawBody);
                    throw new Error('Fallo al procesar la respuesta del servidor');
                }

                if (!response.ok || !data.success) {
                    const msg = (data && (data.error || data.message)) || 'Error al iniciar sesión';
                    throw new Error(msg);
                }
                return data;
            })
            .then(data => {
                loading.reset();
                if (data.message) showSuccess(data.message, 'Inicio de sesión exitoso');
                setTimeout(() => {
                    window.location.href = data.redirect || 'dashboard.php';
                }, 1000);
            })
            .catch(error => {
                loading.reset();
                console.error('[Login] Error capturado:', error);
                const msg = (/abort/i.test(String(error && error.name)) || /network/i.test(String(error)))
                    ? 'Servidor lento o sin respuesta. Intenta nuevamente en unos segundos.'
                    : (error.message || 'Error de conexión. Por favor intenta de nuevo.');
                showError(msg, 'Error de conexión');
            });
        });
    }
}

/**
 * Configura el manejo del formulario de registro
 */
function setupRegisterForm() {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return;
    
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('registerEmail')?.value;
        const password = document.getElementById('registerPassword')?.value;
        const confirmPassword = document.getElementById('confirmPassword')?.value;
        const role = document.querySelector('input[name="role"]:checked')?.value;
        
        // Validaciones básicas
        if (!email || !password || !confirmPassword) {
            showWarning('Por favor completa todos los campos', 'Campos requeridos');
            return false;
        }
        
        // Validar email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showWarning('Por favor ingresa un email válido', 'Email inválido');
            return false;
        }
        
        // Validar que las contraseñas coincidan
        if (password !== confirmPassword) {
            showWarning('Las contraseñas no coinciden', 'Error de confirmación');
            return false;
        }
        
        // Validar contraseña
        if (password.length < 8) {
            showWarning('La contraseña debe tener al menos 8 caracteres', 'Contraseña muy corta');
            return false;
        }
        
        if (!/\d/.test(password)) {
            showWarning('La contraseña debe contener al menos un número', 'Contraseña incompleta');
            return false;
        }
        
        if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            showWarning('La contraseña debe contener al menos un carácter especial', 'Contraseña incompleta');
            return false;
        }
        
        // Validar que se haya seleccionado un rol
        if (!role) {
            showWarning('Por favor selecciona un rol', 'Rol requerido');
            return false;
        }
        

        
        // Mostrar estado de carga
        const loading = showFormLoading(registerForm, 'Registrando...');
        
        // Crear FormData para enviar los datos
        const formData = new FormData();
        formData.append('email', email);
        formData.append('password', password);
        formData.append('confirmPassword', confirmPassword);
        formData.append('role', role);
        
        // Enviar datos al servidor
        fetch('register_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loading.reset();
            
            if (data.success) {
                // Mostrar mensaje de éxito
                showSuccess(data.message || 'Usuario registrado exitosamente', 'Registro completado');
                // Redirigir al login con un pequeño delay para mostrar la notificación
                setTimeout(() => {
                    window.location.href = data.redirect || 'login.php';
                }, 1500);
            } else {
                // Mostrar error
                showError(data.error || 'Error al registrar usuario', 'Error de registro');
            }
        })
        .catch(error => {
            loading.reset();
            console.error('Error:', error);
            showError('Error de conexión. Por favor intenta de nuevo.', 'Error de conexión');
        });
    });
}

/**
 * Configura el manejo del formulario de recuperación de contraseña
 */
function setupForgotPasswordForm() {
    const form = document.getElementById('forgotPasswordForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = document.getElementById('email')?.value;
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        showWarning('Por favor ingresa un email válido', 'Email requerido');
                        return false;
                }
                const loading = showFormLoading(form, 'Enviando...');
                const fd = new FormData();
                fd.append('email', email);
                fetch('forgot_password_start.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(() => {
                        loading.reset();
                        showInfo(`Si la cuenta existe, enviamos un enlace a ${email}`, 'Enlace enviado');
                        setTimeout(() => {
                            window.location.href = 'verificar-codigo.php';
                        }, 2000);
                    })
                    .catch(() => {
                        loading.reset();
                        showError('Hubo un problema. Intenta nuevamente.', 'Error de envío');
                    });
    });
}

// ==================== INICIALIZACIÓN ====================
/**
 * Inicializa todos los manejadores de eventos cuando el DOM está listo
 */
// ==================== FUNCIONALIDADES GENERALES ====================

/**
 * Función para mostrar/ocultar contraseña
 * @param {string} inputId - ID del input de contraseña
 */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const toggle = input.nextElementSibling;
    const eyeIcon = toggle.querySelector('span');
    if (input.type === 'password') {
        input.type = 'text';
        eyeIcon.className = 'closed-eye-icon';
    } else {
        input.type = 'password';
        eyeIcon.className = 'open-eye-icon';
    }
}

// ==================== CONFIGURACIÓN INICIAL ====================
document.addEventListener('DOMContentLoaded', function() {
    // Configurar el botón de acceso directo
    const directAccessBtn = document.getElementById('directAccessBtn');
    if (directAccessBtn) {
        directAccessBtn.addEventListener('click', function() {
            window.location.href = 'dashboard.html';
        });
    }
    
    document.querySelectorAll('.password-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const input = this.previousElementSibling;
            if (!input || !(input instanceof HTMLInputElement)) return;
            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';
            const eyeIcon = this.querySelector('span');
            if (eyeIcon) {
                eyeIcon.className = makeVisible ? 'closed-eye-icon' : 'open-eye-icon';
            }
            this.setAttribute('aria-pressed', makeVisible ? 'true' : 'false');
            this.setAttribute('aria-label', makeVisible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });
    
    // Configurar formularios de login y registro
    setupLoginForm();
    setupRegisterForm();
    
    // Redirección para "Olvidé contraseña"
    document.querySelectorAll('.forgot-password').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'forgot-password.php';
        });
    });
    
    // Redirección para "Regístrate"
    document.querySelectorAll('.register-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'register.html';
        });
    });

    // Manejo del menú móvil
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navMenu = document.querySelector('.nav-menu');

    if (mobileMenuBtn && navMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });

        // Cerrar el menú al hacer clic en un enlace
        const navLinks = navMenu.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                navMenu.classList.remove('active');
            });
        });
    }

    // Manejo de selección de días
    const dayButtons = document.querySelectorAll('.day-btn');
    dayButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.classList.toggle('active');
        });
    });

    // Manejo de AM/PM
    const amPmButtons = document.querySelectorAll('.am-pm-btn');
    amPmButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Desactivar todos los botones
            amPmButtons.forEach(btn => btn.classList.remove('active'));
            // Activar el botón clickeado
            this.classList.add('active');
        });
    });

    // Manejo de inputs de hora y minutos
    const hourInput = document.querySelector('.hour-input');
    const minuteInput = document.querySelector('.minute-input');

    // Formatear minutos para mostrar siempre dos dígitos
    minuteInput?.addEventListener('change', function() {
        let value = parseInt(this.value);
        if (isNaN(value)) value = 0;
        if (value < 0) value = 0;
        if (value > 59) value = 59;
        this.value = value.toString().padStart(2, '0');
    });

    // Validar hora (1-12)
    hourInput?.addEventListener('change', function() {
        let value = parseInt(this.value);
        if (isNaN(value)) value = 1;
        if (value < 1) value = 1;
        if (value > 12) value = 12;
        this.value = value;
    });

    // Manejar el botón de confirmar
    const submitBtn = document.querySelector('.config-submit-btn');
    submitBtn?.addEventListener('click', function() {
        const selectedDays = Array.from(document.querySelectorAll('.day-btn.active'))
            .map(btn => btn.dataset.day);
        
        const hour = document.querySelector('.hour-input')?.value || '12';
        const minute = document.querySelector('.minute-input')?.value || '00';
        const period = document.querySelector('.am-pm-btn.active')?.dataset.period || 'AM';

        const config = {
            days: selectedDays,
            time: `${hour}:${minute} ${period}`
        };

        console.log('Configuración guardada:', config);
        // Aquí puedes agregar la lógica para guardar en la base de datos
        // Por ahora solo mostraremos una notificación
        showSuccess('Configuración guardada exitosamente', 'Guardado completo');
    });

    // Configurar los enlaces de Contáctanos
    document.querySelectorAll('a[href*="report_problem.html"], .contact-link').forEach(link => {
        link.addEventListener('click', handleContactClick);
    });
});

/**
 * Maneja la redirección del enlace de Contáctanos según el estado de autenticación
 * y la página actual
 */
function handleContactClick(event) {
    event.preventDefault();
    
    // Verificar si el usuario está autenticado
    const isAuthenticated = localStorage.getItem('user') !== null;
    
    // Obtener la página actual
    const currentPage = window.location.pathname.split('/').pop();
    
            if (isAuthenticated) {
        // Si está autenticado, redirigir a la sección de contacto en el dashboard
        window.location.href = 'dashboard.php#contact';
    } else {
        // Si no está autenticado, verificar la página actual
        const publicPages = ['index.html', 'login.html', 'register.html', 'forgot-password.php', 'verificar-codigo.html', 'nueva-password.html'];
        
        if (publicPages.includes(currentPage)) {
            // En páginas públicas, mostrar la sección de contacto en index.html
            window.location.href = 'index.html#contact';
        } else {
            // En otras páginas, redirigir a la página de contacto
            window.location.href = 'report_problem.html';
        }
    }
}
