<?php
require_once 'session_init.php';
require_once 'conexion.php';
requireAuth();

$userRole = getUserRole();
$userId = getUserId();

// Preferencia modo oscuro del usuario
$prefDark = 0;
try {
    if ($conn && ($stD = $conn->prepare('SELECT modo_oscuro_config FROM configuracion_usuario WHERE id_usuario = ? LIMIT 1'))) {
        $stD->bind_param('s', $userId);
        if ($stD->execute()) {
            $resD = $stD->get_result();
            if ($rD = $resD->fetch_assoc()) { $prefDark = (int)($rD['modo_oscuro_config'] ?? 0); }
        }
    }
} catch (Throwable $e) { /* silencioso */ }

$vinculos = [];
if ($conn) {
    // Siempre traer ambas direcciones para robustez, añadimos campo tipo: 'paciente' (cuando yo soy cuidador y el otro es paciente) o 'cuidador' (cuando yo soy paciente y el otro es cuidador)
    try {
        if ($st = $conn->prepare("SELECT u.id_usuario, u.nombre_usuario, u.email_usuario, c.estado, 'paciente' AS tipo FROM cuidadores c INNER JOIN usuarios u ON c.paciente_id = u.id_usuario WHERE c.cuidador_id = ?")) {
            $st->bind_param('s', $userId); if ($st->execute()) { $rs=$st->get_result(); while($r=$rs->fetch_assoc()){ $vinculos[]=$r; } }
        }
        if ($st2 = $conn->prepare("SELECT u.id_usuario, u.nombre_usuario, u.email_usuario, c.estado, 'cuidador' AS tipo FROM cuidadores c INNER JOIN usuarios u ON c.cuidador_id = u.id_usuario WHERE c.paciente_id = ?")) {
            $st2->bind_param('s', $userId); if ($st2->execute()) { $rs2=$st2->get_result(); while($r=$rs2->fetch_assoc()){ $vinculos[]=$r; } }
        }
    } catch(Throwable $e) { /* silencioso */ }
}
// Si por rol declarado no hay resultados en su "dirección" pero sí en la opuesta, igual mostramos todo.
// $targetLabel eliminado; ya no se muestra texto entre paréntesis en el título
// Orden opcional: activos primero luego pendientes luego otros
if ($vinculos) {
    usort($vinculos, function($a,$b){
        $p = ['activo'=>0,'pendiente'=>1,'rechazado'=>2,'inactivo'=>3];
        return ($p[strtolower($a['estado'])] ?? 99) <=> ($p[strtolower($b['estado'])] ?? 99);
    });
}
$debugVinculosInfo = [];
if (isset($userId)) {
    // Conteos separados para diagnóstico
    $countPac = 0; $countCuid = 0;
    try {
        if ($conn) {
            if ($stA = $conn->prepare("SELECT COUNT(*) c FROM cuidadores WHERE cuidador_id = ?")) { $stA->bind_param('s',$userId); if($stA->execute()){ $ra=$stA->get_result(); if($rw=$ra->fetch_assoc()) $countCuid=(int)$rw['c']; } }
            if ($stB = $conn->prepare("SELECT COUNT(*) c FROM cuidadores WHERE paciente_id = ?")) { $stB->bind_param('s',$userId); if($stB->execute()){ $rb=$stB->get_result(); if($rw=$rb->fetch_assoc()) $countPac=(int)$rw['c']; } }
        }
    } catch(Throwable $e){ }
    $debugVinculosInfo = [
        'userId'=>$userId,
        'role'=>$userRole,
        'como_cuidador_total'=>$countCuid,
        'como_paciente_total'=>$countPac,
        'total_listados'=>count($vinculos)
    ];
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar vínculos</title>
<script>
// Aplicación temprana de modo oscuro para evitar flash; prioriza localStorage
(function(){
    try {
        var ls = localStorage.getItem('darkMode');
        if (ls === 'enabled') { document.documentElement.classList.add('pre-dark'); }
    } catch(e){}
})();
</script>
<link rel="stylesheet" href="styles.css">
<link rel="icon" href="/icons/lightmode/AutoPill_Logo_Lightmode.png" type="image/png" id="favicon">
<style>
.container { max-width: 900px; margin:120px auto 40px; background: var(--element-bg,#ffffff); padding:32px; border-radius:18px; box-shadow:0 8px 24px rgba(0,0,0,0.12);} 
.toggle-area { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; }
.toggle-area h1 { 
    margin:0; 
    font-size:26px; 
    flex: 1;
    text-align: center;
    margin-right: 40px; /* Compensar el botón de atrás */
}

/* Botón de volver */
.back-button {
    background: none;
    border: none;
    font-size: 24px;
    color: #C154C1;
    cursor: pointer;
    padding: 8px;
    margin-right: 15px;
    border-radius: 50%;
    transition: background-color 0.3s ease;
}

.back-button:hover {
    background-color: rgba(193, 84, 193, 0.1);
}

.btn-primary { background:#6366f1; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:600; }
.btn-primary:hover { background:#4f46e5; }
.table-wrapper { margin-top:24px; display:none; }
.table-wrapper.active { display:block; }

/* Estilos para cards de vínculos */
.vinculo-item {
    width: 100%;
    background: var(--element-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: border-color 0.3s ease;
}

.vinculo-item:hover {
    border-color: #C154C1;
}

.vinculo-info {
    flex: 1;
    text-align: left;
}

.vinculo-info h4 {
    margin: 0 0 10px 0;
    font-size: 1.2em;
    color: var(--text-color);
}

.vinculo-info p {
    margin: 5px 0;
    color: var(--text-color);
    opacity: 0.8;
    font-size: 0.95em;
}

.status-pill { 
    display:inline-block; 
    padding:4px 10px; 
    border-radius:999px; 
    font-size:12px; 
    font-weight:600; 
    background:#e5e7eb; 
    color:#374151; 
    margin-top: 8px;
}
.status-activo { background:#d1fae5; color:#065f46; }
.status-pendiente { background:#fef3c7; color:#92400e; }
.status-inactivo, .status-rechazado { background:#f3f4f6; color:#6b7280; }

.btn-desvincular { 
    background:#dc3545; 
    color:#fff; 
    border:none; 
    padding:8px 16px; 
    border-radius:8px; 
    font-size:14px; 
    cursor:pointer;
    margin-left: 20px;
    align-self: center;
    transition: background-color 0.2s;
}
.btn-desvincular:hover { background:#c82333; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-color);
    opacity: 0.7;
}

/* Modo oscuro */
.dark-mode .container { background: var(--element-bg,#2d2d2d); box-shadow:0 8px 24px rgba(0,0,0,0.35); border:1px solid var(--border-color,#3d3d3d); }
.dark-mode .vinculo-item {
    background: var(--element-bg);
    border-color: var(--border-color);
}
.dark-mode .vinculo-item:hover {
    border-color: #C154C1;
}
.dark-mode .back-button {
    color: #C154C1;
}
.dark-mode .back-button:hover {
    background-color: rgba(193, 84, 193, 0.2);
}
.dark-mode .status-pill { background:#374151; color:#d1d5db; }
.dark-mode .status-activo { background:#065f46; color:#ecfdf5; }
.dark-mode .status-pendiente { background:#92400e; color:#fff7ed; }
.dark-mode .status-inactivo, .dark-mode .status-rechazado { background:#374151; color:#9ca3af; }

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        margin: 100px 16px 32px;
        padding: 24px;
        border-radius: 12px;
    }
    .toggle-area h1 {
        font-size: 22px;
    }
    .vinculo-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    .btn-desvincular {
        margin-left: 0;
        align-self: flex-end;
        width: 100%;
    }
}

@media (max-width: 480px) {
    .container {
        margin: 90px 12px 24px;
        padding: 20px;
        border-radius: 10px;
    }
    .toggle-area h1 {
        font-size: 20px;
    }
    .vinculo-info h4 {
        font-size: 1.1em;
    }
    .vinculo-info p {
        font-size: 0.9em;
    }
    .btn-desvincular {
        padding: 10px 14px;
        font-size: 13px;
    }
}

@media (max-width: 360px) {
    .container {
        margin: 80px 8px 20px;
        padding: 16px;
        border-radius: 8px;
    }
    .toggle-area h1 {
        font-size: 18px;
    }
    .vinculo-info h4 {
        font-size: 1em;
    }
    .vinculo-info p {
        font-size: 0.85em;
    }
    .btn-desvincular {
        padding: 8px 12px;
        font-size: 12px;
    }
}
</style>
</head>
<body class="<?php echo $prefDark ? 'dark-mode' : ''; ?>">
<script>
// Reconciliar: si LS difiere de clase servidor, ajustar y persistir
(function(){
    const body = document.body;
    try {
        const ls = localStorage.getItem('darkMode');
        if (ls === 'enabled' && !body.classList.contains('dark-mode')) {
            body.classList.add('dark-mode');
        } else if (ls === 'disabled' && body.classList.contains('dark-mode')) {
            body.classList.remove('dark-mode');
        } else if (!ls) {
            // Guardar estado actual en LS si no existía
            localStorage.setItem('darkMode', body.classList.contains('dark-mode') ? 'enabled' : 'disabled');
        }
    } catch(e){}
})();
</script>
<?php include __DIR__.'/partials/menu.php'; ?>
<div class="container">
    <div class="toggle-area" style="margin-bottom:8px;">
        <button class="back-button" onclick="window.location.href='mi_cuenta.php'">‹</button>
        <h1>Gestionar vínculos</h1>
    </div>
    <div id="tablaVinculos" class="table-wrapper active" style="display:block;">
        <?php if (empty($vinculos)): ?>
            <div class="empty-state">
                <p style="margin:0; font-size:16px;">No tienes vínculos registrados.</p>
            </div>
        <?php else: ?>
            <?php foreach ($vinculos as $v): 
                $estado = strtolower($v['estado']); 
                $cls='status-pill'; 
                if($estado==='activo') $cls.=' status-activo'; 
                elseif($estado==='pendiente') $cls.=' status-pendiente'; 
                elseif($estado==='inactivo'||$estado==='rechazado') $cls.=' status-inactivo'; 
            ?>
                <div class="vinculo-item" data-target-id="<?php echo htmlspecialchars($v['id_usuario']); ?>">
                    <div class="vinculo-info">
                        <h4><?php echo htmlspecialchars($v['nombre_usuario']); ?></h4>
                        <p>Email: <?php echo htmlspecialchars($v['email_usuario']); ?></p>
                        <p>Tipo: <?php echo htmlspecialchars($v['tipo']); ?></p>
                        <span class="<?php echo $cls; ?>"><?php echo htmlspecialchars($v['estado']); ?></span>
                    </div>
                    <button class="btn-desvincular" data-id="<?php echo htmlspecialchars($v['id_usuario']); ?>">Desvincular</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmación -->
<div class="confirmation-modal-overlay" id="confirmationModal">
    <div class="confirmation-modal">
        <div class="confirmation-modal-header" id="confirmationModalTitle">Confirmar acción</div>
        <div class="confirmation-modal-body" id="confirmationModalMessage">¿Estás seguro de realizar esta acción?</div>
        <div class="confirmation-modal-actions">
            <button class="btn-confirm" id="confirmationModalConfirmBtn">Confirmar</button>
            <button class="btn-cancel" onclick="closeConfirmationModal()">Cancelar</button>
        </div>
    </div>
</div>

<script>
// Funciones del modal de confirmación
function showConfirmationModal(title, message, onConfirm) {
    const modal = document.getElementById('confirmationModal');
    const titleEl = document.getElementById('confirmationModalTitle');
    const messageEl = document.getElementById('confirmationModalMessage');
    const confirmBtn = document.getElementById('confirmationModalConfirmBtn');
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    
    // Remover listeners anteriores clonando el botón
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    newConfirmBtn.addEventListener('click', function() {
        closeConfirmationModal();
        if (onConfirm) onConfirm();
    });
    
    modal.classList.add('show');
    
    // Cerrar modal al hacer clic fuera
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeConfirmationModal();
        }
    });
}

function closeConfirmationModal() {
    const modal = document.getElementById('confirmationModal');
    modal.classList.remove('show');
}

// Tabla visible por defecto; se eliminó el botón toggle

function desvincular(targetId, btn){
    if(!targetId) return;
    
    showConfirmationModal(
        'Desvincular',
        '¿Confirmas desvincularte de este usuario?',
        function() {
            btn.disabled = true; 
            const old = btn.textContent; 
            btn.textContent='...';
            
            fetch('unlink_vinculo.php', { 
                method:'POST', 
                headers:{'Content-Type':'application/json'}, 
                body: JSON.stringify({ target_id: targetId }) 
            })
            .then(async r => {
                const text = await r.text();
                console.log('Respuesta raw del servidor:', text);
                try {
                    return JSON.parse(text);
                } catch(e) {
                    console.error('Error al parsear JSON:', e);
                    console.error('Texto recibido:', text);
                    throw new Error('Respuesta inválida del servidor. Ver consola para detalles.');
                }
            })
            .then(data=>{
                console.log('Respuesta parseada:', data);
                if(!data.success) {
                    let errorMsg = data.error || 'Error desconocido';
                    if(data.detail) errorMsg += ': ' + data.detail;
                    if(data.file && data.line) errorMsg += ' (en ' + data.file + ':' + data.line + ')';
                    throw new Error(errorMsg);
                }
                const item = document.querySelector('.vinculo-item[data-target-id="'+CSS.escape(targetId)+'"]');
                if(item){ item.remove(); }
                if(!document.querySelector('.vinculo-item')){
                    document.querySelector('#tablaVinculos').innerHTML = '<div class="empty-state"><p style="margin:0; font-size:16px;">No tienes vínculos registrados.</p></div>';
                }
                alert('Vínculo eliminado exitosamente');
            })
            .catch(err=>{ 
                console.error('Error al desvincular:', err);
                alert('No se pudo desvincular: ' + err.message); 
            })
            .finally(()=>{ if(btn){ btn.disabled=false; btn.textContent=old; }});
        }
    );
}

document.addEventListener('click', e=>{
    const t = e.target;
    if(t.classList.contains('btn-desvincular')){
        desvincular(t.getAttribute('data-id'), t);
    }
});
</script>
</body>
</html>