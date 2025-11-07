<?php
/**
 * Script simple para vincular ESP32_001 con un usuario específico
 * Ejecutar desde navegador: vincular_esp32_manual.php?user_id=TU_USER_ID
 */

require_once 'conexion.php';

// Obtener user_id desde URL
$user_id = $_GET['user_id'] ?? '';

if (!$user_id) {
    echo "<h2>Error: Falta user_id</h2>";
    echo "<p>Usar: <code>vincular_esp32_manual.php?user_id=TU_USER_ID</code></p>";
    echo "<p>Ejemplo: <code>vincular_esp32_manual.php?user_id=123456</code></p>";
    exit;
}

try {
    $conn = obtenerConexion();
    if (!$conn) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "<h2>Vinculando ESP32_001 con usuario $user_id</h2>";
    
    // Verificar si el usuario existe
    $stmt = $conn->prepare("SELECT nombre, apellido FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo "<p style='color: red;'>❌ Error: Usuario $user_id no existe</p>";
        exit;
    }
    
    $usuario = $result->fetch_assoc();
    echo "<p>✅ Usuario encontrado: {$usuario['nombre']} {$usuario['apellido']}</p>";
    
    // Verificar si ESP32_001 existe
    $stmt = $conn->prepare("SELECT * FROM codigos_esp WHERE nombre_esp = 'ESP32_001'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo "<p style='color: orange;'>⚠️ ESP32_001 no existe, creándolo...</p>";
        
        // Crear ESP32_001
        $stmt = $conn->prepare("INSERT INTO codigos_esp (nombre_esp, validado_fisicamente, id_usuario) VALUES ('ESP32_001', 1, ?)");
        $stmt->bind_param("s", $user_id);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ ESP32_001 creado y vinculado con usuario $user_id</p>";
        } else {
            throw new Exception("Error creando ESP32_001: " . $conn->error);
        }
    } else {
        $esp = $result->fetch_assoc();
        echo "<p>✅ ESP32_001 encontrado (ID: {$esp['id_esp']})</p>";
        
        if ($esp['id_usuario']) {
            echo "<p style='color: orange;'>⚠️ ESP32_001 ya estaba vinculado al usuario: {$esp['id_usuario']}</p>";
        }
        
        // Actualizar vinculación
        $stmt = $conn->prepare("UPDATE codigos_esp SET id_usuario = ?, validado_fisicamente = 1 WHERE nombre_esp = 'ESP32_001'");
        $stmt->bind_param("s", $user_id);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ ESP32_001 vinculado exitosamente con usuario $user_id</p>";
        } else {
            throw new Exception("Error vinculando ESP32_001: " . $conn->error);
        }
    }
    
    // Verificar vinculación final
    echo "<hr>";
    $stmt = $conn->prepare("SELECT ce.*, u.nombre, u.apellido FROM codigos_esp ce LEFT JOIN usuarios u ON ce.id_usuario = u.id_usuario WHERE ce.nombre_esp = 'ESP32_001'");
    $stmt->execute();
    $result = $stmt->get_result();
    $final = $result->fetch_assoc();
    
    echo "<h3>Estado final:</h3>";
    echo "<ul>";
    echo "<li><strong>ESP:</strong> {$final['nombre_esp']}</li>";
    echo "<li><strong>ID ESP:</strong> {$final['id_esp']}</li>";
    echo "<li><strong>Usuario vinculado:</strong> {$final['id_usuario']}</li>";
    echo "<li><strong>Nombre:</strong> {$final['nombre']} {$final['apellido']}</li>";
    echo "<li><strong>Validado:</strong> " . ($final['validado_fisicamente'] ? 'Sí' : 'No') . "</li>";
    echo "</ul>";
    
    echo "<p style='color: green; font-weight: bold;'>🎉 Vinculación completada! El ESP32 debería conectarse en unos segundos.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>