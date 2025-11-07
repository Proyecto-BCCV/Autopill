/**
 * monitor_alarmas.js
 * Sistema automático de monitoreo de alarmas
 * Ejecuta polling cada 20 segundos para verificar alarmas pendientes
 * y crear notificaciones de pastilla dispensada
 * 
 * Se ejecuta automáticamente cuando el usuario está en el dashboard
 */

(function() {
    'use strict';
    
    // Configuración
    const POLLING_INTERVAL = 20000; // 20 segundos
    const ENDPOINT = 'monitor_alarmas.php';
    
    // Variables de estado
    let pollingTimer = null;
    let isRunning = false;
    let consecutiveErrors = 0;
    const MAX_CONSECUTIVE_ERRORS = 5;
    
    /**
     * Log de debugging en consola (solo en desarrollo)
     */
    function log(message, data = null) {
        const timestamp = new Date().toLocaleTimeString('es-AR', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
        
        console.log(`[Monitor Alarmas ${timestamp}] ${message}`);
        if (data) {
            console.log(data);
        }
    }
    
    /**
     * Ejecuta una verificación de alarmas
     */
    async function checkAlarms() {
        try {
            log('🔍 Verificando alarmas pendientes...');
            
            const response = await fetch(ENDPOINT, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                consecutiveErrors = 0; // Reset contador de errores
                
                const stats = data.estadisticas || {};
                
                // Log solo si hay actividad relevante
                if (stats.notificaciones_creadas > 0) {
                    log(`✅ ${stats.notificaciones_creadas} notificación(es) creada(s)`, data);
                    
                    // Opcional: Recargar notificaciones en la UI si existe la función
                    if (typeof window.reloadNotifications === 'function') {
                        window.reloadNotifications();
                    }
                } else if (stats.alarmas_disparadas > 0) {
                    log(`⏭️  ${stats.alarmas_disparadas} alarma(s) detectadas pero ya notificadas`, data);
                }
                // Si no hay alarmas, no hacer log para no saturar la consola
                
            } else {
                log('⚠️  Respuesta con error:', data);
                consecutiveErrors++;
            }
            
        } catch (error) {
            consecutiveErrors++;
            log(`❌ Error en verificación (${consecutiveErrors}/${MAX_CONSECUTIVE_ERRORS}):`, error.message);
            
            // Si hay demasiados errores consecutivos, pausar el monitoreo
            if (consecutiveErrors >= MAX_CONSECUTIVE_ERRORS) {
                log('🛑 Demasiados errores consecutivos. Deteniendo monitoreo temporal...');
                stopMonitoring();
                
                // Reintentar después de 2 minutos
                setTimeout(() => {
                    log('🔄 Reintentando monitoreo...');
                    consecutiveErrors = 0;
                    startMonitoring();
                }, 120000); // 2 minutos
            }
        }
    }
    
    /**
     * Inicia el monitoreo automático
     */
    function startMonitoring() {
        if (isRunning) {
            log('⚠️  El monitoreo ya está activo');
            return;
        }
        
        log('▶️  Iniciando monitoreo automático de alarmas');
        log(`⏱️  Intervalo: ${POLLING_INTERVAL / 1000} segundos`);
        
        isRunning = true;
        
        // Ejecutar inmediatamente la primera vez
        checkAlarms();
        
        // Configurar polling periódico
        pollingTimer = setInterval(checkAlarms, POLLING_INTERVAL);
        
        log('✅ Monitoreo iniciado correctamente');
    }
    
    /**
     * Detiene el monitoreo automático
     */
    function stopMonitoring() {
        if (!isRunning) {
            return;
        }
        
        log('⏸️  Deteniendo monitoreo de alarmas');
        
        if (pollingTimer) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }
        
        isRunning = false;
        log('⏹️  Monitoreo detenido');
    }
    
    /**
     * Maneja la visibilidad de la página para pausar/reanudar el monitoreo
     */
    function handleVisibilityChange() {
        if (document.hidden) {
            // Página no visible - NO detenemos el monitoreo porque queremos 
            // que siga funcionando en segundo plano
            log('👁️  Página oculta - Monitoreo continúa en segundo plano');
        } else {
            // Página visible de nuevo
            log('👁️  Página visible - Verificando estado del monitoreo');
            
            // Si no está corriendo, reiniciar
            if (!isRunning) {
                startMonitoring();
            }
        }
    }
    
    /**
     * Inicialización cuando el DOM está listo
     */
    function init() {
        log('🚀 Inicializando sistema de monitoreo de alarmas');
        
        // Iniciar monitoreo automáticamente
        startMonitoring();
        
        // Escuchar cambios de visibilidad de la página
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Detener monitoreo al cerrar/abandonar la página
        window.addEventListener('beforeunload', () => {
            stopMonitoring();
        });
        
        // Exponer funciones globalmente para debugging/control manual
        window.alarmMonitor = {
            start: startMonitoring,
            stop: stopMonitoring,
            checkNow: checkAlarms,
            isRunning: () => isRunning,
            getStatus: () => ({
                running: isRunning,
                interval: POLLING_INTERVAL,
                consecutiveErrors: consecutiveErrors
            })
        };
        
        log('✅ Sistema inicializado correctamente');
        log('💡 Usa window.alarmMonitor para control manual');
    }
    
    // Iniciar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM ya está listo
        init();
    }
    
})();
