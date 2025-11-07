<?php
// Conexión al servidor MySQL
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($ip !== '127.0.0.1' && $ip !== '::1') { http_response_code(403); exit('Forbidden'); }
$servername = "localhost";
$username = "root";
$password = ""; // Cambia esto si tu contraseña de MySQL no está vacía
$database = "bg03";

$conn = new mysqli($servername, $username, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Verificar si la tabla 'usuarios' existe
$tableCheckQuery = "SHOW TABLES LIKE 'usuarios'";
$tableCheckResult = $conn->query($tableCheckQuery);

if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
    // Verificar si la columna 'first_login' existe en la tabla 'usuarios'
    $columnCheckQuery = "SHOW COLUMNS FROM usuarios LIKE 'first_login'";
    $columnCheckResult = $conn->query($columnCheckQuery);

    if ($columnCheckResult && $columnCheckResult->num_rows == 0) {
        // Si la columna no existe, agregarla
        $alterQuery = "ALTER TABLE usuarios ADD COLUMN first_login TINYINT(1) NOT NULL DEFAULT 1";
        if ($conn->query($alterQuery) === TRUE) {
            echo "Columna 'first_login' agregada correctamente a la tabla 'usuarios'.<br>";
        } else {
            echo "Error al agregar la columna 'first_login': " . $conn->error . "<br>";
        }
    } else {
        echo "La columna 'first_login' ya existe en la tabla 'usuarios'.<br>";
    }
} else {
    echo "La tabla 'usuarios' no existe.<br>";
}

// Eliminar la tabla 'users' si existe
$dropTableQuery = "DROP TABLE IF EXISTS users";
if ($conn->query($dropTableQuery) === TRUE) {
    echo "Tabla 'users' eliminada correctamente.<br>";
} else {
    echo "Error al eliminar la tabla 'users': " . $conn->error . "<br>";
}

// Agregar columna 'detalles_json' a 'notificaciones' si no existe
$colCheck = $conn->query("SHOW COLUMNS FROM notificaciones LIKE 'detalles_json'");
if ($colCheck && $colCheck->num_rows == 0) {
    $alterNotif = "ALTER TABLE notificaciones ADD COLUMN detalles_json LONGTEXT NULL AFTER mensaje";
    if ($conn->query($alterNotif) === TRUE) {
        echo "Columna 'detalles_json' agregada a 'notificaciones'.<br>";
    } else {
        echo "Error al agregar 'detalles_json' en 'notificaciones': " . $conn->error . "<br>";
    }
} else {
    echo "La columna 'detalles_json' ya existe en 'notificaciones'.<br>";
}

// Agregar columna 'last_seen' a 'usuarios' si no existe
$colLastSeen = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'last_seen'");
if ($colLastSeen && $colLastSeen->num_rows == 0) {
    $alterUser = "ALTER TABLE usuarios ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL AFTER first_login";
    if ($conn->query($alterUser) === TRUE) {
        echo "Columna 'last_seen' agregada a 'usuarios'.<br>";
    } else {
        echo "Error al agregar 'last_seen' en 'usuarios': " . $conn->error . "<br>";
    }
} else {
    echo "La columna 'last_seen' ya existe en 'usuarios'.<br>";
}

// Crear tabla autenticacion_google si no existe (esquema alineado)
$sqlGoogle = "CREATE TABLE IF NOT EXISTS autenticacion_google (
    id_usuario CHAR(6) NOT NULL,
    google_id VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_usuario),
    CONSTRAINT fk_google_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sqlGoogle) === TRUE) {
    echo "Tabla 'autenticacion_google' verificada/creada (id_usuario, google_id).<br>";
} else {
    echo "Error al crear/verificar 'autenticacion_google': " . $conn->error . "<br>";
}

$conn->close();
?>
