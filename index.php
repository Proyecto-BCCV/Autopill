<?php
// Incluir inicialización de sesiones y conexión a la base de datos
require_once 'session_init.php';
include 'conexion.php';

// Verificar si el usuario está logueado
$usuario_logueado = isAuthenticated();

// Permitir acceso directo a index.php si se solicita explícitamente con el parámetro 'home'
// o si se accede directamente desde el botón "Inicio"
$allowDirectAccess = isset($_GET['home']) || isset($_SERVER['HTTP_REFERER']);

// Si el usuario está autenticado, solo redirigir automáticamente si no se solicita acceso directo
if ($usuario_logueado && !$allowDirectAccess) {
    if (function_exists('isCuidador') && isCuidador()) {
        header('Location: dashboard_cuidador.php');
        exit;
    } else {
        // Para usuarios, verificar si necesita vincular ESP32
        if (!empty($_SESSION['needs_esp32'])) {
            header('Location: vincular_esp.php');
            exit;
        } else {
            header('Location: dashboard.php');
            exit;
        }
    }
}

$nombre_usuario = getUserName();
$tipo_usuario = $_SESSION['rol'] ?? '';

// Función para obtener estadísticas básicas (opcional)
function obtenerEstadisticas($conn) {
    $stats = [];
    
    // Contar usuarios registrados
    $query = "SELECT COUNT(*) as total FROM usuarios";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['usuarios'] = $row['total'];
    }
    
    // Contar cuidadores
    $query = "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'cuidador'";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['cuidadores'] = $row['total'];
    }
    
    // Contar módulos activos
    $query = "SELECT COUNT(*) as total FROM modulos";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['modulos'] = $row['total'];
    }
    
    return $stats;
}

$estadisticas = obtenerEstadisticas($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autopill</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
</head>
<body>
    <?php if ($usuario_logueado): ?>
        <!-- Usar menú común para usuarios logueados -->
        <?php 
        // Incluir el menú común pero ocultando algunos elementos específicos para index
        include __DIR__ . '/partials/menu.php'; 
        ?>
    <?php else: ?>
        <!-- Header para usuarios no logueados -->
        <header class="header">
            <nav class="nav-container">
                <button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
                <a href="index.php" class="logo">
                    <span class="logo-icon"></span>
                    Autopill
                </a>
                <div class="user-menu">
                    <div class="register-now-text" style="color: #C154C1; font-weight: 600; cursor: pointer;" onclick="toggleUserMenu()">Ingresa aquí</div>
                    <div class="user-dropdown">
                        <a href="login.php" class="dropdown-item"><span class="login-icon"></span>Iniciar sesión</a>
                        <a href="register.php" class="dropdown-item"><span class="register-icon"></span>Registrarse</a>
                    </div>
                </div>
            </nav>
        </header>

        <!-- Menú móvil para usuarios no logueados -->
        <div class="mobile-menu">
            <div class="menu-header">
                <div class="user-info">
                    <span class="user-icon-default"></span>
                    <span>Bienvenido</span>
                </div>
            </div>
            <div class="menu-items">
                <div class="menu-item">
                    <a href="index.php"><span class="home-icon"></span>Inicio</a>
                </div>
                <div class="menu-item">
                    <a href="login.php"><span class="login-icon"></span>Iniciar sesión</a>
                </div>
                <div class="menu-item">
                    <a href="register.php"><span class="register-icon"></span>Registrarse</a>
                </div>
                <div class="menu-item">
                    <div class="toggle-item">
                        <span><span class="dark-mode-icon"></span>Modo oscuro</span>
                        <label class="switch">
                            <input type="checkbox" id="darkModeToggle">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Hero Section with Background Image -->
        <section class="hero-section-new" id="heroSection">
            <div class="hero-background"></div>
            
            <!-- Hero Content Overlay -->
            <div class="hero-content-overlay">
                <div class="hero-content-new">
                    <h1 class="hero-title-new">Un recordatorio para tu bienestar, siempre a tiempo</h1>
                    <p class="hero-subtitle-new">Programa y organiza tus pastillas a tu manera</p>
                    <div class="scroll-indicator">
                        <p class="scroll-text">Saber más</p>
                        <button class="scroll-arrow" onclick="scrollToCards()">
                            <span>↓</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cards Section -->
        <section class="cards-section" id="cardsSection">
            <div class="card card-cuidador">
                <div class="card-gradient-bg"></div>
                <div class="card-content">
                    <h2 class="card-title">Cuidador</h2>
                    <p class="card-description">
                        Para aquellos que quieren administrar la rutina de sus seres queridos a distancia
                    </p>
                    <?php if (!$usuario_logueado): ?>
                        <a href="register.php?tipo=cuidador" class="card-link">Registrarse como Cuidador</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card card-usuario">
                <div class="card-gradient-bg"></div>
                <div class="card-content">
                    <h2 class="card-title">Usuario</h2>
                    <p class="card-description">
                        Para aquellos que buscan programar y organizar sus pastillas de manera simple y efectiva
                    </p>
                    <?php if (!$usuario_logueado): ?>
                        <a href="register.php?tipo=usuario" class="card-link">Registrarse como Usuario</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Contact Form Section -->
        <section class="contact-section" id="contactSection">
            <div class="contact-wrapper">
                <div class="contact-text">
                    <h2>¡Dejanos un comentario y comunicate con nosotros!</h2>
                    <p>Estamos aquí para ayudarte. Completa el formulario y te responderemos a la brevedad.</p>
                </div>
                <div class="contact-form-wrapper">
                    <form id="contactFormIndex" class="contact-form-index">
                        <div class="form-row-index">
                            <div class="input-group-index">
                                <label for="nombre">Nombre</label>
                                <input type="text" id="nombre" name="nombre" required>
                            </div>
                            <div class="input-group-index">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="input-group-index">
                            <label for="comentario">Comentario</label>
                            <textarea id="comentario" name="comentario" rows="5" required></textarea>
                        </div>
                        
                        <button type="submit" class="submit-btn-index">Enviar</button>
                        
                        <div id="submitFeedbackIndex" class="submit-feedback-index" style="display: none;">
                            <span id="feedbackMessageIndex"></span>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer-index">
        <p>AutoPill 2025 - Todos los derechos reservados</p>
    </footer>

    <style>
        /* Reset main-content padding for hero section */
        body {
            background-color: #ffffff;
        }

        body.dark-mode {
            background-color: #1a1a1a;
        }

        body .main-content {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
            background-color: transparent;
        }

        /* Hero Section with Background */
        .hero-section-new {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 100vh;
            overflow: hidden;
            margin: 0 !important;
            padding: 0 !important;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100vw;
            height: 100vh;
            min-height: 100vh;
            background-image: 
                linear-gradient(to bottom, 
                    rgba(0, 0, 0, 0.3) 0%, 
                    rgba(0, 0, 0, 0.3) 75%,
                    rgba(255, 255, 255, 0.3) 80%,
                    rgba(255, 255, 255, 0.6) 85%,
                    rgba(255, 255, 255, 0.85) 92%,
                    rgba(255, 255, 255, 1) 100%),
                url('/icons/index/imagen1.png?v=<?php echo time(); ?>');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            z-index: 1;
        }

        body.dark-mode .hero-background {
            background-image: 
                linear-gradient(to bottom, 
                    rgba(0, 0, 0, 0.3) 0%, 
                    rgba(0, 0, 0, 0.3) 75%,
                    rgba(26, 26, 26, 0.4) 80%,
                    rgba(26, 26, 26, 0.7) 85%,
                    rgba(26, 26, 26, 0.9) 92%,
                    rgba(26, 26, 26, 1) 100%),
                url('/icons/index/imagen1.png?v=<?php echo time(); ?>');
        }

        /* Hero Content Overlay - Centered with offset down */
        .hero-content-overlay {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            pointer-events: none;
            padding-top: 10vh; /* Offset hacia abajo */
        }

        .hero-content-overlay * {
            pointer-events: auto;
        }

        .hero-content-new {
            text-align: center;
            padding: 40px 20px;
            max-width: 800px;
            width: 100%;
        }

        .hero-title-new {
            font-size: 3rem;
            font-weight: 700;
            color: #ffffff;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.8), 
                         0 0 10px rgba(0, 0, 0, 0.6);
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero-subtitle-new {
            font-size: 1.5rem;
            color: #ffffff;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.7), 
                         0 0 8px rgba(0, 0, 0, 0.5);
            margin-bottom: 40px;
        }

        .scroll-indicator {
            margin-top: 100px;
        }

        .scroll-text {
            font-size: 1.2rem;
            color: #ffffff;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.7), 
                         0 0 8px rgba(0, 0, 0, 0.5);
            margin-bottom: 15px;
            font-weight: 500;
        }

        .scroll-arrow {
            background: linear-gradient(135deg, var(--accent-color, #C154C1) 0%, var(--accent-hover, #A13BA1) 100%);
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            color: white;
            font-size: 2.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(193, 84, 193, 0.3);
        }

        .scroll-arrow:hover {
            transform: translateY(5px);
            box-shadow: 0 6px 20px rgba(193, 84, 193, 0.4);
        }

        .scroll-arrow span {
            animation: bounce 2s infinite;
            font-weight: 900;
            line-height: 1;
            -webkit-text-stroke: 2px white;
            text-stroke: 2px white;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        /* Cards Section - restore padding */
        .cards-section {
            padding: 80px 20px;
            background-color: #ffffff;
            position: relative;
            z-index: 5;
            display: flex;
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
            justify-content: center;
            align-items: stretch;
        }

        body.dark-mode .cards-section {
            background-color: #1a1a1a;
        }

        /* Card Styles */
        .card {
            position: relative;
            flex: 1;
            max-width: 500px;
            min-height: 380px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background-color: #ffffff;
        }

        body.dark-mode .card {
            background-color: #1a1a1a;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
        }

        .card-gradient-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        /* Cuidador Card - Imagen arriba, gradiente abajo con transición suave */
        .card-cuidador .card-gradient-bg {
            background: linear-gradient(to bottom, 
                transparent 0%, 
                transparent 40%, 
                rgba(193, 84, 193, 0.3) 45%,
                rgba(193, 84, 193, 0.6) 50%,
                rgba(193, 84, 193, 0.85) 55%,
                #C154C1 60%, 
                #C154C1 100%), 
                url('/icons/index/imagen2_cuidador.png');
            background-size: 100% 100%, cover;
            background-position: center, right center;
            background-repeat: no-repeat, no-repeat;
        }

        /* Usuario Card - Imagen arriba, gradiente abajo con transición suave */
        .card-usuario .card-gradient-bg {
            background: linear-gradient(to bottom, 
                transparent 0%, 
                transparent 40%, 
                rgba(193, 84, 193, 0.3) 45%,
                rgba(193, 84, 193, 0.6) 50%,
                rgba(193, 84, 193, 0.85) 55%,
                #C154C1 60%, 
                #C154C1 100%), 
                url('/icons/index/imagen3_usuario.png');
            background-size: 100% 100%, cover;
            background-position: center, center;
            background-repeat: no-repeat, no-repeat;
        }

        .card-content {
            position: relative;
            z-index: 2;
            padding: 0 30px 30px 30px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            text-align: center;
            padding-top: 200px;
        }

        .card-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 15px;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.4);
            margin-top: 0;
        }

        .card-description {
            font-size: 1rem;
            line-height: 1.5;
            color: #ffffff;
            margin-bottom: 20px;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.3);
        }

        .card-link {
            display: inline-block;
            padding: 12px 28px;
            background-color: #ffffff;
            color: #C154C1;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            align-self: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            margin-bottom: 10px;
        }

        .card-link:hover {
            background-color: #f0f0f0;
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        body.dark-mode .card-link {
            background-color: #ffffff;
            color: #C154C1;
        }

        body.dark-mode .card-link:hover {
            background-color: #f0f0f0;
        }

        /* Contact Section */
        .contact-section {
            padding: 80px 20px;
            background-color: #ffffff;
            position: relative;
            z-index: 100;
            clear: both;
            display: block;
        }
        
        body.dark-mode .contact-section {
            background-color: #1a1a1a;
        }
        
        /* Stats Section - restore padding */
        .stats-section {
            padding: 60px 20px;
            background-color: #ffffff;
            position: relative;
            z-index: 5;
        }

        body.dark-mode .stats-section {
            background-color: #1a1a1a;
        }

        /* Footer */
        .footer-index {
            background-color: #ffffff;
            color: #1D1D1D;
            text-align: center;
            padding: 30px 20px;
            font-size: 1rem;
            font-weight: 500;
            position: relative;
            z-index: 100;
            border-top: 1px solid #e1e8ed;
        }

        .footer-index p {
            margin: 0;
        }

        body.dark-mode .footer-index {
            background-color: #2A2A2A;
            color: #ffffff;
            border-top: 1px solid #444;
        }

        body.dark-mode .footer-index p {
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }

        .contact-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 60px;
            align-items: start;
        }

        .contact-text h2 {
            font-size: 2.5rem;
            color: var(--text-color, #333);
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .contact-text p {
            font-size: 1.1rem;
            color: var(--text-secondary, #666);
            line-height: 1.6;
        }

        .contact-form-wrapper {
            background: var(--bg-secondary, #fff);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .contact-form-index .form-row-index {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .contact-form-index .input-group-index {
            margin-bottom: 0;
        }

        .contact-form-index label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color, #555);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .contact-form-index input,
        .contact-form-index textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color, #e1e8ed);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--bg-secondary, #fff);
            color: var(--text-color, #333);
            box-sizing: border-box;
        }

        .contact-form-index input:focus,
        .contact-form-index textarea:focus {
            outline: none;
            border-color: var(--accent-color, #C154C1);
            box-shadow: 0 0 0 3px rgba(193, 84, 193, 0.1);
        }

        .contact-form-index textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
            margin-bottom: 20px;
        }

        .submit-btn-index {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent-color, #C154C1) 0%, var(--accent-hover, #A13BA1) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn-index:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(193, 84, 193, 0.3);
        }

        .submit-feedback-index {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
        }

        .submit-feedback-index.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .submit-feedback-index.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Dark Mode Styles */
        body.dark-mode .hero-title-new {
            color: #ffffff;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.9), 
                         0 0 10px rgba(0, 0, 0, 0.7);
        }

        body.dark-mode .hero-subtitle-new {
            color: #ffffff;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.8), 
                         0 0 8px rgba(0, 0, 0, 0.6);
        }

        body.dark-mode .scroll-text {
            color: #ffffff;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.8), 
                         0 0 8px rgba(0, 0, 0, 0.6);
        }

        body.dark-mode .contact-section {
            background-color: var(--bg-primary, #1a1a1a);
        }

        body.dark-mode .contact-text h2 {
            color: var(--text-color, #fff);
        }

        body.dark-mode .contact-text p {
            color: var(--text-secondary, #ccc);
        }

        body.dark-mode .contact-form-wrapper {
            background: var(--bg-secondary, #2a2a2a);
        }

        body.dark-mode .contact-form-index label {
            color: var(--text-color, #fff);
        }

        body.dark-mode .contact-form-index input,
        body.dark-mode .contact-form-index textarea {
            background-color: var(--bg-secondary, #333);
            border-color: var(--border-color, #555);
            color: var(--text-color, #fff);
        }

        body.dark-mode .contact-form-index input:focus,
        body.dark-mode .contact-form-index textarea:focus {
            border-color: var(--accent-color, #C154C1);
            box-shadow: 0 0 0 3px rgba(193, 84, 193, 0.2);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            /* Header Mobile Fixes */
            .header {
                padding: 15px 10px;
            }

            .nav-container {
                padding: 0 5px !important;
                gap: 10px;
            }

            .logo {
                font-size: 0.9rem !important;
                padding: 2px 4px !important;
                flex-shrink: 0;
            }

            .logo-icon {
                width: 30px;
                height: 20px;
                margin-right: 4px;
            }

            .register-now-text {
                font-size: 0.85rem !important;
                padding: 4px 8px;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .user-menu {
                flex-shrink: 0;
            }

            .mobile-menu-btn {
                font-size: 20px;
                padding: 5px;
                flex-shrink: 0;
            }

            /* Hero Section Mobile */
            .hero-section-new {
                min-height: 100vh;
                height: auto;
            }

            .hero-background {
                width: 100vw;
                min-height: 100vh;
                background-size: cover;
                background-position: center center;
            }

            .hero-content-overlay {
                padding-top: 5vh;
                padding-left: 15px;
                padding-right: 15px;
            }

            .hero-content-new {
                padding: 20px 15px;
            }

            .hero-title-new {
                font-size: 1.8rem;
                margin-bottom: 15px;
                line-height: 1.3;
            }

            .hero-subtitle-new {
                font-size: 1rem;
                margin-bottom: 30px;
            }

            .scroll-indicator {
                margin-top: 60px;
            }

            .scroll-text {
                font-size: 1rem;
                margin-bottom: 12px;
            }

            .scroll-arrow {
                width: 50px;
                height: 50px;
                font-size: 2rem;
            }

            /* Cards Section Mobile */
            .cards-section {
                flex-direction: column;
                padding: 50px 15px;
                gap: 30px;
            }

            .card {
                max-width: 100%;
                min-height: 400px;
                margin: 0 auto;
                width: 100%;
            }

            .card-content {
                padding: 0 20px 30px 20px;
                padding-top: 220px;
            }

            .card-title {
                font-size: 1.7rem;
                margin-bottom: 15px;
            }

            .card-description {
                font-size: 0.95rem;
                line-height: 1.5;
                margin-bottom: 25px;
            }

            .card-link {
                padding: 12px 28px;
                font-size: 0.95rem;
            }

            /* Gradient backgrounds ajustados para mobile */
            .card-cuidador .card-gradient-bg {
                background: linear-gradient(to bottom, 
                    transparent 0%, 
                    transparent 35%, 
                    rgba(193, 84, 193, 0.3) 40%,
                    rgba(193, 84, 193, 0.6) 45%,
                    rgba(193, 84, 193, 0.85) 50%,
                    #C154C1 55%, 
                    #C154C1 100%), 
                    url('/icons/index/imagen2_cuidador.png');
                background-size: 100% 100%, cover;
                background-position: center, center;
            }

            .card-usuario .card-gradient-bg {
                background: linear-gradient(to bottom, 
                    transparent 0%, 
                    transparent 35%, 
                    rgba(193, 84, 193, 0.3) 40%,
                    rgba(193, 84, 193, 0.6) 45%,
                    rgba(193, 84, 193, 0.85) 50%,
                    #C154C1 55%, 
                    #C154C1 100%), 
                    url('/icons/index/imagen3_usuario.png');
                background-size: 100% 100%, cover;
                background-position: center, center;
            }

            /* Contact Section Mobile */
            .contact-section {
                padding: 50px 15px;
            }

            .contact-wrapper {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .contact-text h2 {
                font-size: 1.6rem;
                margin-bottom: 15px;
            }

            .contact-text p {
                font-size: 1rem;
                line-height: 1.5;
            }

            .contact-form-wrapper {
                padding: 25px 20px;
            }

            .contact-form-index .form-row-index {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .contact-form-index .input-group-index {
                margin-bottom: 15px;
            }

            .contact-form-index input,
            .contact-form-index textarea {
                padding: 12px;
                font-size: 0.95rem;
            }

            .contact-form-index textarea {
                min-height: 100px;
                margin-bottom: 15px;
            }

            .submit-btn-index {
                padding: 14px;
                font-size: 0.95rem;
            }

            /* Footer Mobile */
            .footer-index {
                padding: 25px 15px;
                font-size: 0.9rem;
            }
        }

        /* Medium mobile devices (between small and tablet) */
        @media (min-width: 481px) and (max-width: 768px) {
            .logo {
                font-size: 0.95rem !important;
            }

            .logo-icon {
                width: 32px;
                height: 20px;
            }

            .register-now-text {
                font-size: 0.9rem !important;
            }
        }

        /* Tablet Adjustments */
        @media (min-width: 769px) and (max-width: 1024px) {
            .hero-title-new {
                font-size: 2.5rem;
            }

            .hero-subtitle-new {
                font-size: 1.3rem;
            }

            .cards-section {
                padding: 70px 30px;
                gap: 30px;
            }

            .card {
                min-height: 480px;
            }

            .card-content {
                padding: 0 25px 35px 25px;
                padding-top: 250px;
            }

            .contact-wrapper {
                gap: 50px;
            }

            .contact-text h2 {
                font-size: 2.2rem;
            }

            .contact-form-wrapper {
                padding: 35px;
            }
        }

        /* Small mobile devices */
        @media (max-width: 480px) {
            /* Header extra small screens */
            .header {
                padding: 12px 5px;
            }

            .nav-container {
                padding: 0 3px !important;
                gap: 5px;
            }

            .logo {
                font-size: 0.8rem !important;
                padding: 2px !important;
            }

            .logo-icon {
                width: 25px;
                height: 18px;
                margin-right: 3px;
            }

            .register-now-text {
                font-size: 0.75rem !important;
                padding: 3px 6px;
            }

            .mobile-menu-btn {
                font-size: 18px;
                padding: 3px;
            }

            .hero-title-new {
                font-size: 1.5rem;
                margin-bottom: 12px;
            }

            .hero-subtitle-new {
                font-size: 0.9rem;
                margin-bottom: 25px;
            }

            .scroll-indicator {
                margin-top: 40px;
            }

            .scroll-text {
                font-size: 0.9rem;
            }

            .scroll-arrow {
                width: 45px;
                height: 45px;
                font-size: 1.8rem;
            }

            .card {
                min-height: 380px;
            }

            .card-content {
                padding: 0 15px 25px 15px;
                padding-top: 200px;
            }

            .card-title {
                font-size: 1.5rem;
                margin-bottom: 12px;
            }

            .card-description {
                font-size: 0.9rem;
                margin-bottom: 20px;
            }

            .card-link {
                padding: 10px 24px;
                font-size: 0.9rem;
            }

            .contact-text h2 {
                font-size: 1.4rem;
            }

            .contact-text p {
                font-size: 0.95rem;
            }

            .contact-form-wrapper {
                padding: 20px 15px;
            }
        }
    </style>

    <script src="auth.js"></script>
    <script>
        // Smooth scroll function
        function scrollToCards() {
            const cardsSection = document.getElementById('cardsSection');
            if (cardsSection) {
                cardsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Contact form submission
        document.addEventListener('DOMContentLoaded', function() {
            const contactForm = document.getElementById('contactFormIndex');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const submitBtn = contactForm.querySelector('.submit-btn-index');
                    const feedback = document.getElementById('submitFeedbackIndex');
                    const feedbackMessage = document.getElementById('feedbackMessageIndex');
                    const originalText = submitBtn.textContent;
                    
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Enviando...';
                    
                    const formData = new FormData(contactForm);
                    
                    fetch('enviar_contacto_index.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        feedback.style.display = 'block';
                        if (data.success) {
                            feedback.className = 'submit-feedback-index success';
                            feedbackMessage.textContent = data.message || '¡Mensaje enviado con éxito!';
                            contactForm.reset();
                        } else {
                            feedback.className = 'submit-feedback-index error';
                            feedbackMessage.textContent = data.error || 'Error al enviar el mensaje';
                        }
                        
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                        
                        setTimeout(() => {
                            feedback.style.display = 'none';
                        }, 5000);
                    })
                    .catch(error => {
                        feedback.style.display = 'block';
                        feedback.className = 'submit-feedback-index error';
                        feedbackMessage.textContent = 'Error al enviar el mensaje. Por favor intenta nuevamente.';
                        
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                        
                        setTimeout(() => {
                            feedback.style.display = 'none';
                        }, 5000);
                    });
                });
            }
        });
    </script>
    <?php if (!$usuario_logueado): ?>
    <script>
        function toggleMenu() {
            const mobileMenu = document.querySelector('.mobile-menu');
            if (mobileMenu) {
                mobileMenu.classList.toggle('active');
            }
        }

        // Cerrar menú al hacer clic fuera
        document.addEventListener('click', function(e) {
            const mobileMenu = document.querySelector('.mobile-menu');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (mobileMenu && mobileMenu.classList.contains('active') && 
                !mobileMenu.contains(e.target) && 
                !menuBtn.contains(e.target)) {
                mobileMenu.classList.remove('active');
            }
        });

        // Modo oscuro
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            const savedMode = localStorage.getItem('darkMode');
            if (savedMode === 'enabled') {
                document.body.classList.add('dark-mode');
                darkModeToggle.checked = true;
            }
            
            darkModeToggle.addEventListener('change', function() {
                if (this.checked) {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('darkMode', 'disabled');
                }
            });
        }

        function toggleUserMenu() {
            const userDropdown = document.querySelector('.user-dropdown');
            if (userDropdown) {
                userDropdown.classList.toggle('active');
            }
        }

        // Cerrar el menú al hacer clic fuera
        document.addEventListener('click', function(e) {
            const userMenu = document.querySelector('.user-menu');
            const userDropdown = document.querySelector('.user-dropdown');
            const registerText = document.querySelector('.register-now-text');
            
            if (userDropdown && userDropdown.classList.contains('active') && 
                !userMenu.contains(e.target) && 
                !registerText.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });
    </script>
    <?php endif; ?>

        <?php 
        // Limpiar mensajes sin mostrarlos
        if (isset($_SESSION['mensaje'])) {
            unset($_SESSION['mensaje']);
        }
        if (isset($_SESSION['tipo_mensaje'])) {
            unset($_SESSION['tipo_mensaje']);
        }
        ?>
    </script>
</body>
</html> 