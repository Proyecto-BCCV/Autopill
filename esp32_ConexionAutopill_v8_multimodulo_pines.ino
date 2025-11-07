/*
 * PastillSSL - Cliente ESP32 con Auto-registro MULTI-MÓDULO + LED y BUZZER
 * Versión 5.0 - ALERTAS VISUALES Y SONORAS SIMULTÁNEAS
 * 
 * NUEVAS CARACTERÍSTICAS v5.0:
 * - LED se enciende durante dispensación de pastilla
 * - Buzzer suena simultáneamente durante dispensación
 * - Servo se mueve mientras LED y buzzer están activos
 * - Alerta final con 3 parpadeos y pitidos
 * - Todo sincronizado por módulo correspondiente
 * 
 * MEJORAS PRINCIPALES v4.0:
 * - Soporte para hasta 5 módulos con servos independientes
 * - Reconoce TODAS las alarmas del usuario vinculado
 * - Sistema mejorado de consulta de alarmas usando tabla 'alarmas'
 * - Configuración flexible de pines de servo por módulo
 * - Mejor manejo de días de la semana
 * - Optimización de rendimiento y memoria
 * 
 * CONFIGURACIÓN DE HARDWARE (basada en TEST_SERVO_LED_BUZZER_OK):
 * - LED: GPIO 4
 * - Buzzer: GPIO 5
 * - Módulo 1: GPIO 18 (Servo 180°)
 * - Módulo 2: GPIO 19 (Servo 180°)
 * - Módulo 3: GPIO 21 (Servo 180°)
 * - Botón Reset WiFi: GPIO 0 (BOOT integrado)
 * 
 * ⚠️ IMPORTANTE: Para 3 servos NECESITAS fuente externa 5V/2A
 * - ESP32 puede dar: 500mA máximo total
 * - 1 servo consume: ~300mA
 * - 3 servos: ~900mA (REQUIERE fuente externa obligatoriamente)
 * 
 * SOLUCIÓN DEFINITIVA: Fuente externa 5V/2A:
 *   - Power bank, cargador de celular, o fuente PC
 *   - Conexión: Fuente (+5V) → VCC servos | Fuente (GND) → GND común
 * 
 * PINES DISPONIBLES: 13, 12, 14, 27, 26, 25, 33, 32, 35, 34
 * IMPORTANTE: GPIO 34 y 35 son SOLO ENTRADA
 * 
 * FUNCIONAMIENTO:
 * 1. Se registra automáticamente en el servidor
 * 2. Verifica vinculación con usuario cada 30 segundos
 * 3. Consulta TODAS las alarmas del usuario cada 10 segundos
 * 4. Ejecuta servos según el módulo de cada alarma
 * 5. NUEVO: Activa LED, buzzer y servo SIMULTÁNEAMENTE
 * 6. Reporta ejecuciones al servidor
 * 
 * VINCULACIÓN: Usar códigos 001, 0001 o ESP32_001 en vincular_esp.php
 */

 #include <WiFi.h>
 #include <HTTPClient.h>
 #include <WiFiClientSecure.h>
 #include <ArduinoJson.h>
 #include <ESP32Servo.h>
 #include <time.h>
 #include <WiFiManager.h>      // Librería para configuración automática de WiFi
 #include <ESPmDNS.h>          // Para acceso mediante nombre de dominio local
 #include <vector>
 
 // WiFiManager instance para configuración automática de WiFi
 WiFiManager wm;
 
 // Configuración del servidor web externo
 const char* server_host = "pastillero.webhop.net"; // Servidor web externo
 const int server_port = 80;                         // Puerto HTTP estándar
 const char* api_key = "esp32_alarm_2024_secure_key_987654321";
 
 // Configuración del dispositivo
 String device_code = "ESP32_001"; // Código único del dispositivo - DEBE COINCIDIR CON LA BASE DE DATOS
 String firmware_version = "5.0.0"; // NUEVA VERSIÓN con LED y Buzzer
 
 // ═══════════════════════════════════════════════════════════════
 // NUEVA CONFIGURACIÓN v5.0: LED Y BUZZER
 // ═══════════════════════════════════════════════════════════════
 // IMPORTANTE: Configuración basada en TEST_SERVO_LED_BUZZER_OK
 const int LED_PIN = 19;         // Pin para el LED (indica dispensación) - GPIO 4
 const int BUZZER_PIN = 18;      // Pin para el Buzzer (alerta sonora) - GPIO 5
 const int BUZZER_FREQ = 2000;  // Frecuencia del buzzer en Hz
 const int BUZZER_RESOLUTION = 8; // Resolución PWM de 8 bits (0-255)
 // ═══════════════════════════════════════════════════════════════
 
 // ═══════════════════════════════════════════════════════════════
 // LED FEEDBACK SYSTEM - Estados visuales del sistema
 // ═══════════════════════════════════════════════════════════════
 enum LedState {
     LED_STARTUP,        // LED encendido 3 segundos al inicio
     LED_CONNECTING,     // LED parpadeando rápido (conectando a WiFi)
     LED_READY,          // LED encendido fijo (listo para recibir alarmas)
     LED_DISPENSING      // LED usado durante dispensación de pastillas
     // NOTA: Modo AP usa 3 parpadeos como señal, luego LED apagado
     //       (WiFiManager bloquea, imposible hacer parpadeo continuo)
 };
 
 LedState currentLedState = LED_STARTUP;
 unsigned long lastLedToggle = 0;
 bool ledOn = false;
 
 // Tiempo de parpadeo rápido (en milisegundos)
 const unsigned long LED_FAST_BLINK_INTERVAL = 200;   // 200ms = parpadeo rápido
 // ═══════════════════════════════════════════════════════════════
 
 // CONFIGURACIÓN ESPECÍFICA PARA DEBUGGING DE VINCULACIÓN
 bool force_debug_linkage = true; // Activar debug detallado de vinculación
 
 // Configuración de hardware - SOPORTE MULTI-MÓDULO
 // Configuración basada en TEST_SERVO_LED_BUZZER_OK con 3 servos
 const int MAX_MODULES = 5;  // 5 módulos como en el test
const int servo_pins[MAX_MODULES] = {32, 25, 33, 22, 23}; // s1:32, s2:25, s3:33, s4:22, s5:23
 Servo servos[MAX_MODULES]; // Array de servos
 
 // Pin para botón de reset WiFi (botón integrado del ESP32)
 const int resetButtonPin = 0; // Botón BOOT integrado del ESP32
 
 // Variables para el botón de reset WiFi
 unsigned long lastButtonPress = 0;
 const unsigned long debounceDelay = 50;
 bool lastButtonState = HIGH;
 
 // Variables de estado
 bool device_registered = false;
 bool device_validated = false;
 bool time_synchronized = false;
 String linked_user_id = ""; // ID del usuario vinculado (String para mayor flexibilidad)
 unsigned long last_heartbeat = 0;
 unsigned long last_alarm_check = 0;
 unsigned long last_alarm_execution_check = 0;
 unsigned long last_notification_check = 0;
 unsigned long last_time_check = 0;
 unsigned long last_linkage_check = 0;
 unsigned long last_time_display = 0;  // Nueva variable para mostrar hora
 unsigned long last_intensive_check = 0; // Para verificación cada segundo cuando se acerca alarma
 const unsigned long heartbeat_interval = 30000; // 30 segundos
 const unsigned long alarm_check_interval = 5000; // 5 segundos - verificar alarmas muy frecuentemente para precisión
 const unsigned long notification_check_interval = 5000; // 5 segundos
 const unsigned long time_check_interval = 120000; // 2 minutos - verificación más frecuente
 const unsigned long linkage_check_interval = 30000; // 30 segundos
 const unsigned long time_display_interval = 15000; // 15 segundos - mostrar hora más frecuentemente
 
 // Configuración de tiempo para Buenos Aires, Argentina
 const char* ntpServers[] = {
     "pool.ntp.org",          // Servidor internacional (muy confiable)
     "time.google.com",       // Servidor de Google (confiable)
     "time.cloudflare.com",   // Servidor Cloudflare (rápido)
     "0.ar.pool.ntp.org",     // Servidor NTP de Argentina
     "time.nist.gov"          // Servidor NIST (backup)
 };
 const int ntpServerCount = 5;
 const long gmtOffset_sec = -3 * 3600; // GMT-3 (Buenos Aires - UTC-3)
 const int daylightOffset_sec = 0;      // Argentina no usa horario de verano actualmente
 
 // Array para rastrear alarmas ejecutadas (evitar ejecuciones múltiples)
 struct ExecutedAlarm {
     int id;
     unsigned long timestamp;
 };
 const int MAX_EXECUTED_ALARMS = 20;
 ExecutedAlarm executed_alarms[MAX_EXECUTED_ALARMS];
 int executed_alarm_count = 0;
 
 // Variables para rastrear las próximas alarmas (MÚLTIPLES ALARMAS SIMULTÁNEAS)
 struct PendingAlarm {
     int id;
     String time;
     String name;
     int module;
     bool active;
 };
 const int MAX_PENDING_ALARMS = 10; // Máximo de alarmas pendientes simultáneas
 PendingAlarm pending_alarms[MAX_PENDING_ALARMS];
 int pending_alarm_count = 0;
 
 // Bandera para evitar ejecuciones concurrentes
 bool is_executing_alarms = false;
 
 // Declaraciones de funciones
 void setupWiFiWithManager();
 void checkResetButton();
 void setupTime();
 bool registerDevice();
 void showFinalStatus();
 void sendHeartbeat();
 void checkAllAlarms();
 void checkNextAlarm();
 int getMinutesUntilAlarm(String alarmTime);
 void checkNotifications();
 void checkTimeSync();
 void checkLinkageStatus();
 void displayCurrentTime();
 String getCurrentTime();
 void processAlarmResponse(String response, String currentTime);
 bool isDayActive(String diasSemana);
 int detectModuleFromName(String alarmName);
 bool isAlarmAlreadyExecuted(int alarmId);
 void markAlarmAsExecuted(int alarmId);
 void executeAlarmForModule(int alarmId, String alarmName, int moduleNum);
 void executeAlarmsInParallel(int alarmIndices[], int count);
 void reportAlarmExecution(int alarmId, bool success, int moduleNum);
 void initializeServos();
 void forceTimeSync();
 bool isExactTimeMatch(String alarmTime);
 bool isTimeForPreAlert(String alarmTime);
 void findNextAlarm();
 void checkNextAlarm();
 int getMinutesUntilAlarm(String alarmTime);
 int getMinutesUntilAlarmWithTolerance(String alarmTime);
 bool testInternetConnectivity();
 bool isWithin10SecondsOfAlarm(String alarmTime);
 bool isWithin10SecondsWindow(String alarmTime);
 bool hasAlarmWithin10Seconds();
 void intensiveAlarmCheck();
 void parseDebugLogs(String response, String currentTime);
 
 // ═══════════════════════════════════════════════════════════════
 // NUEVAS FUNCIONES v5.0: CONTROL DE LED Y BUZZER
 // ═══════════════════════════════════════════════════════════════
 void activateLED();
 void deactivateLED();
 void activateBuzzer();
 void deactivateBuzzer();
 void moveServoTo180(int moduleIndex);
 void moveServoTo0(int moduleIndex);
 void initializeAlertSystem();
 void updateLedFeedback();  // Nueva función para manejar estados del LED
 void setLedState(LedState newState);
 // ═══════════════════════════════════════════════════════════════
 
 void setup() {
     Serial.begin(115200);
     Serial.println("╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  PastillSSL ESP32 v5.0 - MULTI-MÓDULO + LED & BUZZER     ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
     Serial.println("Iniciando con soporte para " + String(MAX_MODULES) + " módulos...");
     Serial.println("NUEVA CARACTERÍSTICA v5.0: Alertas visuales y sonoras SIMULTÁNEAS");
     Serial.println("- LED en GPIO " + String(LED_PIN) + " (se enciende durante dispensación)");
     Serial.println("- Buzzer en GPIO " + String(BUZZER_PIN) + " (suena durante dispensación)");
    Serial.println("- Servos: GPIO 32, 25, 33, 22, 23 (se mueven MIENTRAS LED y buzzer están activos)");
     
     // ═══════════════════════════════════════════════════════════════
     // NUEVO: Inicializar sistema de alertas (LED, Buzzer Y SERVOS)
     // Esta función configura TODO - no necesitamos initializeServos()
     // ═══════════════════════════════════════════════════════════════
     initializeAlertSystem();
     
     // ═══════════════════════════════════════════════════════════════
     // LED FEEDBACK: Startup - Encender LED por 3 segundos
     // ═══════════════════════════════════════════════════════════════
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  LED FEEDBACK: STARTUP - Encendido por 3 segundos         ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
     setLedState(LED_STARTUP);
     delay(3000);  // Mantener LED encendido 3 segundos
     digitalWrite(LED_PIN, LOW);  // Apagar después del startup
     Serial.println("[LED FEEDBACK] Startup completado - LED apagado\n");
     // ═══════════════════════════════════════════════════════════════
     
     // Configurar botón de reset WiFi
     pinMode(resetButtonPin, INPUT_PULLUP);
     
     // Conectar a WiFi con configuración automática
     Serial.println("Iniciando conexion WiFi con seleccion de red...");
     setupWiFiWithManager();
     
     if (WiFi.status() == WL_CONNECTED) {
         Serial.println("WiFi OK - Configurando tiempo...");
         
         // Intentar sincronización hasta 3 veces si falla
         int timeRetries = 0;
         while (!time_synchronized && timeRetries < 3) {
             setupTime();
             timeRetries++;
             
             if (!time_synchronized) {
                 Serial.println("Reintento " + String(timeRetries) + "/3 de sincronizacion NTP...");
                 delay(2000);
             }
         }
         
         if (time_synchronized) {
             Serial.println("Tiempo sincronizado exitosamente");
         } else {
             Serial.println("ADVERTENCIA - No se pudo sincronizar tiempo, continuando...");
         }
         
         Serial.println("Registrando dispositivo...");
         bool registrationSuccess = registerDevice();
         
         if (registrationSuccess) {
             Serial.println("OK - Registro completado - Dispositivo listo");
         } else {
             Serial.println("WARN - Registro con problemas - Funcionamiento en modo limitado");
             device_registered = true;
         }
         
         // Mostrar estado final
         showFinalStatus();
         
         // ═══════════════════════════════════════════════════════════════
         // LED FEEDBACK: Si está listo, encender LED fijo
         // ═══════════════════════════════════════════════════════════════
         if (device_registered) {
             Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
             Serial.println("║  LED FEEDBACK: READY - ESP listo para recibir alarmas    ║");
             Serial.println("╚═══════════════════════════════════════════════════════════╝");
             setLedState(LED_READY);
         }
         // ═══════════════════════════════════════════════════════════════
     } else {
         Serial.println("ERROR - WiFi fallo - modo offline");
     }
     
     Serial.println("=== SETUP COMPLETADO - INICIANDO LOOP ===");
 }
 
 void loop() {
     // Actualizar estado del LED de feedback
     updateLedFeedback();
     
     // Verificar botón de reset WiFi
     checkResetButton();
     
     // Verificar conexión WiFi
     if (WiFi.status() != WL_CONNECTED) {
         Serial.println("WiFi desconectado, reintentando...");
         setupWiFiWithManager();
         return;
     }
     
     unsigned long now = millis();
     
     // Heartbeat periódico
     if (now - last_heartbeat > heartbeat_interval) {
         sendHeartbeat();
         last_heartbeat = now;
     }
     
     // Verificar próxima alarma si el dispositivo está registrado y vinculado
     if (device_registered && !linked_user_id.isEmpty()) {
         // Buscar nuevas alarmas cada 30 segundos
         if ((now - last_alarm_check > alarm_check_interval)) {
             checkAllAlarms(); // Busca y configura todas las alarmas pendientes
             last_alarm_check = now;
         }
         
         // VERIFICACIÓN INTENSIVA: Si hay alarmas pendientes y alguna está cerca
         if (pending_alarm_count > 0 && hasAlarmWithin10Seconds()) {
             // Verificar cada segundo cuando se acerca una alarma
             if (now - last_intensive_check > 1000) {
                 Serial.println("*** MODO INTENSIVO ACTIVADO - Verificando alarmas cada 1 segundo...");
                 intensiveAlarmCheck();  // Solo información de debug
                 checkNextAlarm();        // EJECUTAR ALARMAS TAMBIÉN
                 last_intensive_check = now;
             }
         } else {
             // Verificación normal cada 5 segundos
             if (now - last_alarm_execution_check > 5000) {
                 checkNextAlarm(); // Ejecuta si llegó el momento
                 last_alarm_execution_check = now;
             }
         }
     }
     
     // Verificar notificaciones para actualizaciones inmediatas
     if (device_registered && (now - last_notification_check > notification_check_interval)) {
         checkNotifications();
         last_notification_check = now;
     }
     
     // Verificar sincronización de tiempo periódicamente
     if (now - last_time_check > time_check_interval) {
         checkTimeSync();
         last_time_check = now;
     }
     
     // Verificar estado de vinculación periódicamente
     if (device_registered && (now - last_linkage_check > linkage_check_interval)) {
         checkLinkageStatus();
         
         // Si después de la verificación normal aún no hay vinculación, usar verificación forzada
         if (linked_user_id.isEmpty()) {
             Serial.println("No hay vinculación detectada, ejecutando verificación forzada...");
             forceCheckLinkage();
         }
         
         last_linkage_check = now;
     }
     
     // Mostrar hora actual periódicamente
     if (time_synchronized && (now - last_time_display > time_display_interval)) {
         displayCurrentTime();
         last_time_display = now;
     }
 
     // Delay adaptivo: más rápido cuando hay alarmas próximas
     if (pending_alarm_count > 0 && hasAlarmWithin10Seconds()) {
         delay(100); // 100ms cuando se acerca alarma (verificación muy frecuente)
     } else {
         delay(1000); // 1 segundo en operación normal
     }
 }
 
 // ═══════════════════════════════════════════════════════════════
 // IMPLEMENTACIÓN DE NUEVAS FUNCIONES v5.0: LED, BUZZER Y SERVO
 // ═══════════════════════════════════════════════════════════════
 
 /**
  * Activa el LED
  */
 void activateLED() {
     digitalWrite(LED_PIN, HIGH);
     Serial.println("[LED] Encendido");
 }
 
 /**
  * Desactiva el LED
  */
 void deactivateLED() {
     digitalWrite(LED_PIN, LOW);
     Serial.println("[LED] Apagado");
 }
 
 /**
  * Activa el Buzzer
  */
 void activateBuzzer() {
     ledcWriteTone(BUZZER_PIN, 1300);
     Serial.println("[BUZZER] Sonando a 1500 Hz");
 }
 
 /**
  * Desactiva el Buzzer
  */
 void deactivateBuzzer() {
     ledcWrite(BUZZER_PIN, 0);
     Serial.println("[BUZZER] Silenciado");
 }
 
 /**
  * Mueve el servo a 180°
  */
 void moveServoTo180(int moduleIndex) {
     servos[moduleIndex].write(180);
     Serial.println("[SERVO " + String(moduleIndex + 1) + "] Moviendo a 180°");
 }
 
 /**
  * Mueve el servo a 0°
  */
 void moveServoTo0(int moduleIndex) {
     servos[moduleIndex].write(0);
     Serial.println("[SERVO " + String(moduleIndex + 1) + "] Moviendo a 0°");
 }
 
 /**
  * Inicializa el sistema de alertas (LED y Buzzer)
  */
 void initializeAlertSystem() {
     Serial.println("╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  INICIALIZANDO SISTEMA DE ALERTAS v5.0                   ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
    
    // Initialize servos first (before buzzer to avoid PWM conflicts)
    Serial.println("\nConectando servos...");
    for (int i = 0; i < MAX_MODULES; i++) {
        servos[i].attach(servo_pins[i]);
        
        if (servos[i].attached()) {
            Serial.println("Servo " + String(i + 1) + " conectado en GPIO " + String(servo_pins[i]));
            // Changed from 0° to 180° initial position
            servos[i].write(180);  // Initialize all servos at 180°
        } else {
            Serial.println("ERROR: No se pudo conectar servo " + String(i + 1) + "!");
        }
    }
    
    // Initialize LED
    pinMode(LED_PIN, OUTPUT);
    digitalWrite(LED_PIN, LOW);
    Serial.println("LED configurado en GPIO " + String(LED_PIN));
    
    // Initialize Buzzer
    ledcAttach(BUZZER_PIN, 2000, 8);
    ledcWrite(BUZZER_PIN, 0);  // Turn off buzzer immediately
    Serial.println("Buzzer configurado en GPIO " + String(BUZZER_PIN) + " (apagado)");
    
    Serial.println("\nTodos los servos inicializados en 180°");
    delay(1000);
}
 
 // ═══════════════════════════════════════════════════════════════
 // FIN DE NUEVAS FUNCIONES v5.0
 // ═══════════════════════════════════════════════════════════════
 
 // ═══════════════════════════════════════════════════════════════
 // FUNCIONES DE FEEDBACK LED - Estados del sistema
 // ═══════════════════════════════════════════════════════════════
 
 /**
  * Establece el estado del LED de feedback
  */
 void setLedState(LedState newState) {
     currentLedState = newState;
     lastLedToggle = millis();
     
     switch(newState) {
         case LED_STARTUP:
             digitalWrite(LED_PIN, HIGH);
             ledOn = true;
             Serial.println("[LED FEEDBACK] Estado: STARTUP - Encendido 3s");
             break;
         case LED_CONNECTING:
             Serial.println("[LED FEEDBACK] Estado: CONNECTING - Parpadeo rápido");
             break;
         case LED_READY:
             digitalWrite(LED_PIN, HIGH);
             ledOn = true;
             Serial.println("[LED FEEDBACK] Estado: READY - Encendido fijo");
             break;
         case LED_DISPENSING:
             // Este estado se maneja directamente en executeAlarmsInParallel
             Serial.println("[LED FEEDBACK] Estado: DISPENSING - Control manual");
             break;
     }
 }
 
 /**
  * Actualiza el estado del LED según el modo actual
  * Debe llamarse continuamente desde loop()
  */
 void updateLedFeedback() {
     unsigned long currentMillis = millis();
     
     switch(currentLedState) {
         case LED_STARTUP:
             // Apagar después de 3 segundos
             if (currentMillis - lastLedToggle >= 3000) {
                 // No cambiar de estado aquí, se hace en setup()
             }
             break;
             
         case LED_CONNECTING:
             // Parpadeo rápido (200ms)
             if (currentMillis - lastLedToggle >= LED_FAST_BLINK_INTERVAL) {
                 ledOn = !ledOn;
                 digitalWrite(LED_PIN, ledOn ? HIGH : LOW);
                 lastLedToggle = currentMillis;
             }
             break;
             
         case LED_READY:
             // LED siempre encendido - no hacer nada
             break;
             
         case LED_DISPENSING:
             // Control manual durante dispensación - no interferir
             break;
     }
 }
 
 // ═══════════════════════════════════════════════════════════════
 // FIN FUNCIONES DE FEEDBACK LED
 // ═══════════════════════════════════════════════════════════════
 
 void initializeServos() {
     Serial.println("=== INICIALIZANDO SERVOS MULTI-MÓDULO ===");
     
     for (int i = 0; i < MAX_MODULES; i++) {
         servos[i].attach(servo_pins[i]);
         servos[i].write(0); // Posición inicial
         delay(200); // Pausa entre servos
         Serial.println("Servo módulo " + String(i + 1) + " configurado en pin " + String(servo_pins[i]));
     }
     
     delay(1000); // Esperar que todos lleguen a posición
     
     // Desconectar todos los servos para evitar micro movimientos
     for (int i = 0; i < MAX_MODULES; i++) {
         servos[i].detach();
     }
     
     Serial.println("Todos los servos desconectados en reposo");
 }
 
 void setupWiFiWithManager() {
     Serial.println("=== CONFIGURACIÓN WIFI AUTOMÁTICA ===");
     Serial.println("Intentando conectar con credenciales guardadas...");
     
     // ═══════════════════════════════════════════════════════════════
     // LED FEEDBACK: Activar parpadeo rápido durante conexión WiFi
     // ═══════════════════════════════════════════════════════════════
     setLedState(LED_CONNECTING);
     // ═══════════════════════════════════════════════════════════════
     
     // NO BORRAR - Permitir que el ESP32 recuerde las credenciales
     // Solo descomentar si quieres forzar reconfiguración:
     // wm.resetSettings();
     
     // Configurar WiFiManager con timeouts apropiados
     wm.setConfigPortalTimeout(180);  // 3 minutos de espera para portal
     wm.setConnectTimeout(30);        // 30 segundos timeout para conectar a AP
     wm.setConnectRetries(3);         // 3 intentos de conexión
     wm.setAPClientCheck(true);       // Verificar conectividad del cliente
     wm.setBreakAfterConfig(true);    // Salir del portal después de configurar
     
     // Configurar tiempos de espera de WiFi
     WiFi.setAutoReconnect(true);     // Reconexión automática
     
     // Personalizar portal de configuración
     wm.setTitle("Configuracion del Pastillero Autopill");
     wm.setDarkMode(false);
     // Mostrar solo el menú de configuración WiFi
     static std::vector<const char*> menuItems = {"wifi"};
     wm.setMenu(menuItems);
     // Cambiar color de botones a #c154c1
     wm.setCustomHeadElement(
         "<style>.btn,button,input[type='submit']{background:#c154c1!important;border-color:#c154c1!important} .btn:hover,button:hover,input[type='submit']:hover{opacity:.9}</style>"
         "<script>document.addEventListener('DOMContentLoaded',function(){try{\n"
         "var brand=document.querySelector('.brand'); if(brand){brand.textContent='Configuracion del Pastillero Autopill';}\n"
         "document.title='Configuracion del Pastillero Autopill';\n"
         "function replaceText(el, map){var t=(el.innerText||el.value||'').trim();if(!t)return;var low=t.toLowerCase();Object.keys(map).forEach(function(k){if(low===k.toLowerCase()){if(el.innerText!==undefined)el.innerText=map[k]; if(el.value!==undefined)el.value=map[k];}});}\n"
         "var btnMap={'Configure WiFi':'Configurar WiFi','Configure Wifi':'Configurar WiFi','Save':'Conectar','Save Credentials':'Conectar','Cancel':'Cancelar','Connect':'Conectar','Refresh':'Refrescar','Rescan':'Refrescar','Scan':'Buscar redes','Show password':'Mostrar contraseña','Hide password':'Ocultar contraseña','Show':'Mostrar','Hide':'Ocultar'};\n"
         "Array.from(document.querySelectorAll('a,button,input[type=submit],label')).forEach(function(el){replaceText(el, btnMap);});\n"
         "Array.from(document.querySelectorAll('label')).forEach(function(l){var lt=l.textContent.trim(); if(lt==='SSID')l.textContent='Red WiFi (SSID)'; if(lt==='Password')l.textContent='Contraseña';});\n"
         "var ss=document.querySelector('input[name=s]'); if(ss){ss.placeholder='Nombre de la red';}\n"
         "var pw=document.querySelector('input[name=p]'); if(pw){pw.placeholder='Contraseña de la red';}\n"
         "// Ocultar solo mensajes de alerta con textos 'AP not found' o 'not connected...' sin ocultar contenedores\n"
         "var badRe=/(ap not found|not connected)/i;\n"
         "Array.from(document.querySelectorAll('.msg,.message,.alert,[role=\"alert\"]')).forEach(function(n){try{var txt=(n.innerText||'').trim(); if(badRe.test(txt)||/not connected to .*|AP not found/i.test(txt)){n.textContent=''; n.style.visibility='hidden';}}catch(e){}});\n"
         "}catch(e){} });</script>");
     
     // Configurar nombres del portal
     String apName = "Conexion Autopill";
     String apPassword = "";  // Sin contraseña para facilitar acceso
     
     Serial.println("=========================================");
     Serial.println("PORTAL DE CONFIGURACIÓN WIFI ABIERTO");
     Serial.println("=========================================");
     Serial.println("Si NO hay credenciales guardadas:");
     Serial.println("1. Conéctate a la red: " + apName);
     Serial.println("2. Ve a: http://192.168.4.1");
     Serial.println("3. Selecciona tu red WiFi");
     Serial.println("4. Ingresa la contraseña");
     Serial.println("5. Haz clic en 'Save'");
     Serial.println("=========================================");
     Serial.println("Esperando conexión WiFi...");
     Serial.println("=========================================");
     
     // ═══════════════════════════════════════════════════════════════
     // LED FEEDBACK: Callback para modo AP
     // LIMITACIÓN: WiFiManager bloquea completamente, no podemos hacer parpadeo
     // SOLUCIÓN: Hacer 3 parpadeos rápidos como señal y dejar apagado
     // ═══════════════════════════════════════════════════════════════
     wm.setAPCallback([](WiFiManager *myWiFiManager) {
         Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
         Serial.println("║  LED FEEDBACK: AP MODE - 3 parpadeos de señal            ║");
         Serial.println("╚═══════════════════════════════════════════════════════════╝");
         
         // Hacer 3 parpadeos rápidos como señal de que entró en modo AP
         for(int i = 0; i < 3; i++) {
             digitalWrite(LED_PIN, HIGH);
             delay(150);
             digitalWrite(LED_PIN, LOW);
             delay(150);
         }
         Serial.println("[LED FEEDBACK] 3 parpadeos completados - LED apagado en modo AP");
         // Dejar LED apagado durante el portal (estado diferente a todos los demás)
     });
     
     // Callback para cuando sale del portal (timeout o conexión exitosa)
     wm.setSaveConfigCallback([]() {
         Serial.println("[LED FEEDBACK] Configuración guardada - volviendo a CONNECTING");
         setLedState(LED_CONNECTING);
     });
     // ═══════════════════════════════════════════════════════════════
     
     // AUTOCONECTAR: Intenta con credenciales guardadas, si no abre portal
     Serial.println("Intentando conectar...");
     bool connected = wm.autoConnect(apName.c_str());
     
     if (!connected) {
         Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
         Serial.println("║  ERROR: NO SE PUDO CONECTAR A LA RED WIFI                ║");
         Serial.println("╠═══════════════════════════════════════════════════════════╣");
         Serial.println("║  Posibles causas:                                        ║");
         Serial.println("║  1. Contraseña incorrecta                                ║");
         Serial.println("║  2. Red WiFi fuera de alcance                            ║");
         Serial.println("║  3. Router no responde                                   ║");
         Serial.println("║  4. Timeout del portal (3 minutos)                       ║");
         Serial.println("╠═══════════════════════════════════════════════════════════╣");
         Serial.println("║  Reiniciando en 5 segundos para reintentar...           ║");
         Serial.println("╚═══════════════════════════════════════════════════════════╝");
         delay(5000);
         ESP.restart();
     }
     
     // Si llegamos aquí, la conexión fue exitosa
     Serial.println("=== WIFI CONECTADO EXITOSAMENTE ===");
     Serial.print("Red WiFi: ");
     Serial.println(WiFi.SSID());
     Serial.print("IP local: ");
     Serial.println(WiFi.localIP());
     
     // Verificar que realmente tenemos IP
     if (WiFi.status() != WL_CONNECTED || WiFi.localIP() == IPAddress(0,0,0,0)) {
         Serial.println("ERROR: Conectado pero sin IP asignada");
         Serial.println("Reiniciando...");
         delay(3000);
         ESP.restart();
     }
     
     // Configurar mDNS para acceso fácil
     if (MDNS.begin("pastillssl")) {
         Serial.println("mDNS iniciado: http://pastillssl.local");
         MDNS.addService("http", "tcp", 80);
     } else {
         Serial.println("Error iniciando mDNS");
     }
     
     Serial.print("Gateway: ");
     Serial.println(WiFi.gatewayIP());
     Serial.print("MAC Address: ");
     Serial.println(WiFi.macAddress());
     Serial.print("Señal (RSSI): ");
     Serial.print(WiFi.RSSI());
     Serial.println(" dBm");
     
     Serial.println("=========================================");
     Serial.println("INSTRUCCIONES PARA CAMBIAR RED WIFI:");
     Serial.println("1. Mantén presionado el botón BOOT del ESP32 por 5 segundos");
     Serial.println("2. El ESP32 se reiniciará y abrirá el portal de configuración");
     Serial.println("3. Conéctate a la red '" + apName + "'");
     Serial.println("4. Ve a http://192.168.4.1 para configurar nueva red");
     Serial.println("=========================================");
 }
 
 void checkResetButton() {
     static unsigned long buttonPressStart = 0;
     static bool buttonPressed = false;
     
     bool currentButtonState = digitalRead(resetButtonPin);
     
     // Debouncing simple
     if (currentButtonState != lastButtonState) {
         lastButtonPress = millis();
     }
     
     if ((millis() - lastButtonPress) > debounceDelay) {
         if (currentButtonState == LOW) {
             if (!buttonPressed) {
                 buttonPressed = true;
                 buttonPressStart = millis();
                 Serial.println("Botón BOOT presionado - mantén 5 segundos para reset WiFi...");
             } else if (millis() - buttonPressStart > 5000) {
                 // Botón mantenido por 5 segundos - abrir portal de configuración WiFi
                 Serial.println("=== ABRIENDO PORTAL DE CONFIGURACIÓN WIFI ===");
                 Serial.println("Borrando red actual y abriendo portal...");
                 wm.resetSettings();
                 Serial.println("Reiniciando para abrir portal de selección...");
                 delay(2000);
                 ESP.restart();
             }
         } else {
             if (buttonPressed && (millis() - buttonPressStart) < 5000) {
                 Serial.println("Botón liberado antes de 5 segundos - reset cancelado");
             }
             buttonPressed = false;
         }
     }
     
     lastButtonState = currentButtonState;
 }
 
 bool registerDevice() {
     Serial.println("=== AUTO-REGISTRO MULTI-MÓDULO ===");
     
     WiFiClientSecure client;
     client.setInsecure();
     client.setTimeout(10000);
     
     HTTPClient http;
     String url = "https://" + String(server_host) + "/esp_discovery.php?api_key=" + String(api_key);
     
     http.begin(client, url);
     http.setTimeout(10000);
     http.addHeader("Content-Type", "application/json");
     http.addHeader("X-API-Key", api_key);
     
     // Crear payload de registro con soporte multi-módulo
     DynamicJsonDocument doc(1024);
     doc["device_code"] = device_code;
     doc["firmware_version"] = firmware_version;
     doc["mac_address"] = WiFi.macAddress();
     doc["ip_address"] = WiFi.localIP().toString();
     doc["modules_count"] = MAX_MODULES;
     doc["servo_pins"] = JsonArray();
     for (int i = 0; i < MAX_MODULES; i++) {
         doc["servo_pins"].add(servo_pins[i]);
     }
     doc["registration_time"] = millis();
     doc["api_key"] = api_key;
     
     String payload;
     serializeJson(doc, payload);
     
     Serial.println("Enviando registro multi-módulo...");
     Serial.println("Payload: " + payload);
     
     int httpCode = http.POST(payload);
     String response = http.getString();
     
     Serial.print("Código HTTP: ");
     Serial.println(httpCode);
     Serial.print("Respuesta: ");
     Serial.println(response);
     
     bool success = processRegistrationResponse(httpCode, response);
     
     http.end();
     return success;
 }
 
 bool processRegistrationResponse(int httpCode, String response) {
     Serial.println("=== PROCESANDO RESPUESTA DE REGISTRO ===");
     Serial.println("HTTP Code: " + String(httpCode));
     Serial.println("Response: " + response);
     
     if (response.indexOf("ESP existente validado") >= 0) {
         Serial.println("OK - ESP YA EXISTE Y ESTÁ VALIDADO");
         device_registered = true;
         device_validated = true;
         
         // Intentar extraer user_id si está en la respuesta
         DynamicJsonDocument doc(1024);
         DeserializationError error = deserializeJson(doc, response);
         
         if (error) {
             Serial.println("WARN - No es JSON válido, buscando linked_user_id manualmente...");
             // Buscar patrón manualmente
             if (response.indexOf("linked_user_id") >= 0) {
                 int startPos = response.indexOf("linked_user_id") + 15;
                 int endPos = response.indexOf("\"", startPos + 2);
                 if (endPos > startPos) {
                     String extractedId = response.substring(startPos + 2, endPos);
                     if (extractedId.length() > 0 && extractedId != "null") {
                         linked_user_id = extractedId;
                         Serial.println("REGISTRO - ID extraído manualmente: " + linked_user_id);
                     }
                 }
             }
         } else {
             if (doc.containsKey("linked_user_id") && !doc["linked_user_id"].isNull()) {
                 linked_user_id = doc["linked_user_id"].as<String>();
                 Serial.println("REGISTRO - ESP vinculado al usuario ID: " + linked_user_id);
             }
         }
         return true;
     }
     
     if (httpCode == 200) {
         DynamicJsonDocument responseDoc(1024);
         DeserializationError error = deserializeJson(responseDoc, response);
         
         if (error) {
             Serial.println("WARN - Error parsing JSON en registro: " + String(error.c_str()));
             // Asumir éxito si al menos got HTTP 200
             device_registered = true;
             device_validated = true;
             Serial.println("REGISTRO - Asumiendo éxito por HTTP 200");
             return true;
         }
         
         if (responseDoc["success"] == true) {
             device_registered = true;
             device_validated = responseDoc["can_link"] | false;
             
             Serial.println("SUCCESS field found: true");
             
             if (responseDoc.containsKey("linked_user_id") && !responseDoc["linked_user_id"].isNull()) {
                 linked_user_id = responseDoc["linked_user_id"].as<String>();
                 Serial.println("REGISTRO - ESP vinculado al usuario ID: " + linked_user_id);
             } else {
                 linked_user_id = "";
                 Serial.println("INFO - ESP no está vinculado a ningún usuario aún");
             }
             
             Serial.println("OK - DISPOSITIVO REGISTRADO EXITOSAMENTE");
             return true;
         } else {
             Serial.println("SUCCESS field: " + responseDoc["success"].as<String>());
         }
     }
     
     Serial.println("REGISTRO FALLIDO - Detalles arriba");
     return false;
 }
 
 void sendHeartbeat() {
     if (!device_registered) {
         Serial.println("HEARTBEAT - Dispositivo no registrado, intentando registro...");
         registerDevice();
         return;
     }
     
     WiFiClientSecure client;
     client.setInsecure();
     client.setTimeout(8000);
     
     HTTPClient http;
     String url = "https://" + String(server_host) + "/heartbeat.php?code=" + device_code + "&status=active&api_key=" + String(api_key);
     
     http.begin(client, url);
     http.setTimeout(8000);
     http.addHeader("Content-Type", "application/json");
     http.addHeader("X-API-Key", api_key);
     http.addHeader("User-Agent", "ESP32-PastillSSL/5.0");
     
     int httpCode = http.GET();
     String response = http.getString();
     
     if (httpCode == 200) {
         Serial.println("INFO - Heartbeat enviado OK");
         
         // SIEMPRE intentar obtener user_id del heartbeat para mantener sincronizado
         DynamicJsonDocument doc(1024);
         DeserializationError error = deserializeJson(doc, response);
         
         if (error) {
             Serial.println("HEARTBEAT - Respuesta no es JSON: " + response);
             // Buscar linked_user_id manualmente
             if (response.indexOf("linked_user_id") >= 0) {
                 int startPos = response.indexOf("linked_user_id") + 15;
                 int endPos = response.indexOf("\"", startPos + 2);
                 if (endPos > startPos) {
                     String extractedId = response.substring(startPos + 2, endPos);
                     if (extractedId.length() > 0 && extractedId != "null") {
                         if (linked_user_id != extractedId) {
                             linked_user_id = extractedId;
                             Serial.println("HEARTBEAT - Usuario vinculado detectado: " + linked_user_id);
                         }
                     }
                 }
             }
         } else {
             // JSON válido
             if (doc.containsKey("linked_user_id")) {
                 if (doc["linked_user_id"].isNull()) {
                     if (!linked_user_id.isEmpty()) {
                         Serial.println("HEARTBEAT - Usuario desvinculado detectado");
                         Serial.println("HEARTBEAT - Limpiando alarmas en memoria...");
                         linked_user_id = "";
                         // Limpiar alarmas pendientes
                         pending_alarm_count = 0;
                         for (int i = 0; i < MAX_PENDING_ALARMS; i++) {
                             pending_alarms[i].active = false;
                         }
                         // Limpiar alarmas ejecutadas
                         executed_alarm_count = 0;
                         Serial.println("HEARTBEAT - ESP desvinculado y alarmas limpiadas");
                     }
                 } else {
                     String heartbeatUserId = doc["linked_user_id"].as<String>();
                     if (linked_user_id != heartbeatUserId) {
                         linked_user_id = heartbeatUserId;
                         Serial.println("HEARTBEAT - Usuario vinculado actualizado: " + linked_user_id);
                         // Limpiar alarmas anteriores al cambiar de usuario
                         pending_alarm_count = 0;
                         executed_alarm_count = 0;
                         Serial.println("HEARTBEAT - Alarmas limpiadas por cambio de usuario");
                     }
                 }
             }
         }
     } else {
         Serial.println("WARN - Error en heartbeat HTTP " + String(httpCode) + ": " + response);
     }
     
     http.end();
 }
 
 void checkAllAlarms() {
     String currentTime = getCurrentTime();
     
     if (linked_user_id.isEmpty()) {
         static unsigned long lastWarning = 0;
         if (millis() - lastWarning > 60000) { // Avisar solo cada 60 segundos
             Serial.println("ESP no vinculado - Usa vincular_esp.php para vincular con un usuario");
             lastWarning = millis();
         }
         return;
     }
     
     Serial.println("\n=== VERIFICACION DE ALARMAS ===");
     Serial.println("HORA: " + currentTime + " | Usuario: " + linked_user_id);
     
     WiFiClientSecure client;
     client.setInsecure();
     client.setTimeout(15000);
     
     HTTPClient http;
     // URL para obtener TODAS las alarmas del usuario (con manejo mejorado de output)
     String url = "https://" + String(server_host) + "/get_all_user_alarms.php?user_id=" + linked_user_id + "&api_key=" + String(api_key);
     
     Serial.println("DEBUG - URL: " + url);
     
     http.begin(client, url);
     http.setTimeout(15000);
     http.addHeader("X-API-Key", api_key);
     http.addHeader("User-Agent", "ESP32-PastillSSL/5.0");
     
     int httpCode = http.GET();
     String response = http.getString();
     
     Serial.println("=== DEBUG RESPUESTA SERVIDOR ===");
     Serial.println("Código HTTP: " + String(httpCode));
     Serial.println("Respuesta: " + response);
     Serial.println("=================================");
     
     if (httpCode == 200) {
         processAlarmResponse(response, currentTime);
     } else if (httpCode == 500 && response.indexOf("get_all_user_alarms") >= 0) {
         // El servidor devuelve 500 pero con datos válidos - procesarlos de todas formas
         Serial.println("WARN - Servidor devuelve HTTP 500 pero contiene datos - Procesando...");
         processAlarmResponse(response, currentTime);
     } else {
         Serial.println("ERROR - Error obteniendo alarmas HTTP " + String(httpCode));
         Serial.println("Respuesta no contiene datos procesables");
     }
     
     http.end();
 }
 
 void processAlarmResponse(String response, String currentTime) {
     // MOSTRAR PRIMERO LA RESPUESTA RAW COMPLETA
     Serial.println("\n╔═══════════════════════════════════════════════════════════");
     Serial.println("║ RESPUESTA RAW DEL SERVIDOR (primeros 500 chars):");
     Serial.println("╠═══════════════════════════════════════════════════════════");
     Serial.println(response.substring(0, min(500, (int)response.length())));
     if (response.length() > 500) {
         Serial.println("... (" + String(response.length() - 500) + " caracteres más)");
     }
     Serial.println("╚═══════════════════════════════════════════════════════════\n");
     
     // LIMPIEZA AGRESIVA: Buscar el inicio del JSON
     int jsonStart = response.indexOf('{');
     int jsonArrayStart = response.indexOf('[');
     
     // Si encuentra array antes que objeto, puede ser un error
     if (jsonArrayStart >= 0 && jsonArrayStart < jsonStart) {
         Serial.println("[WARN] ADVERTENCIA: Se encontro '[' antes que '{'");
         Serial.println("   Posicion '[': " + String(jsonArrayStart));
         Serial.println("   Posicion '{': " + String(jsonStart));
         Serial.println("   Esto sugiere que hay output de logs antes del JSON");
     }
     
     if (jsonStart > 0) {
         Serial.println("ADVERTENCIA: Hay " + String(jsonStart) + " bytes ANTES del JSON");
         Serial.println("Contenido eliminado (primeros 200 chars):");
         Serial.println("'" + response.substring(0, min(200, jsonStart)) + "'");
         Serial.println("\nExtrayendo JSON limpio desde posición " + String(jsonStart) + "...");
         response = response.substring(jsonStart);
     } else if (jsonStart < 0) {
         Serial.println("ERROR CRITICO: No se encontro '{' en la respuesta");
         Serial.println("La respuesta NO contiene JSON valido");
         parseDebugLogs(response, currentTime);
         return;
     }
     
     DynamicJsonDocument doc(2048);
     DeserializationError error = deserializeJson(doc, response);
     
     if (error) {
         Serial.println("╔═══════════════════════════════════════════════════════════");
         Serial.println("║ ERROR: No se pudo parsear JSON");
         Serial.println("╠═══════════════════════════════════════════════════════════");
         Serial.println("Error de deserialización: " + String(error.c_str()));
         Serial.println("Longitud de respuesta: " + String(response.length()) + " caracteres");
         Serial.println("");
         Serial.println("CONTENIDO COMPLETO DE LA RESPUESTA:");
         Serial.println("════════════════════════════════════════════════════════════");
         Serial.println(response);
         Serial.println("════════════════════════════════════════════════════════════");
         Serial.println("");
         Serial.println("ANÁLISIS DE CARACTERES:");
         Serial.println("Primer carácter: '" + String((char)response.charAt(0)) + "' (código: " + String((int)response.charAt(0)) + ")");
         Serial.println("Segundo carácter: '" + String((char)response.charAt(1)) + "' (código: " + String((int)response.charAt(1)) + ")");
         Serial.println("Tercer carácter: '" + String((char)response.charAt(2)) + "' (código: " + String((int)response.charAt(2)) + ")");
         
         // Buscar si hay algún { en la respuesta
         int jsonPos = response.indexOf('{');
         if (jsonPos >= 0) {
             Serial.println("\nEncontrado '{' en posición: " + String(jsonPos));
             if (jsonPos > 0) {
                 Serial.println("HAY " + String(jsonPos) + " CARACTERES ANTES DEL JSON:");
                 for (int i = 0; i < jsonPos && i < 50; i++) {
                     Serial.print("Pos " + String(i) + ": '" + String((char)response.charAt(i)) + "' [" + String((int)response.charAt(i)) + "] ");
                 }
                 Serial.println("");
             }
         } else {
             Serial.println("\nNO SE ENCONTRÓ NINGÚN '{' - No es JSON válido");
         }
         Serial.println("╚═══════════════════════════════════════════════════════════\n");
         
         // Intentar con parseDebugLogs (aunque está desactivada, mostrará el mensaje)
         parseDebugLogs(response, currentTime);
         return;
     }
     
     // DEBUG: Mostrar el JSON completo parseado
     Serial.println("╔═══════════════════════════════════════════════════════════");
     Serial.println("║ JSON PARSEADO CORRECTAMENTE:");
     Serial.println("╠═══════════════════════════════════════════════════════════");
     String jsonOutput;
     serializeJsonPretty(doc, jsonOutput);
     Serial.println(jsonOutput);
     Serial.println("╚═══════════════════════════════════════════════════════════\n");
     
     int alarmCount = doc["alarm_count"] | 0;
     Serial.println("ALARMAS ENCONTRADAS: " + String(alarmCount));
     
     // IMPORTANTE: No limpiar alarmas pendientes si hay una ejecución en curso
     if (is_executing_alarms) {
         Serial.println("[WARN] Ejecución en curso - NO se limpiará el array de alarmas pendientes");
         Serial.println("[INFO] Se omite el reprocesamiento para evitar interferencia");
         return; // Salir sin modificar nada
     }
     
     // Limpiar alarmas pendientes anteriores
     pending_alarm_count = 0;
     
     if (alarmCount > 0) {
         JsonArray alarms = doc["alarms"];
         
         // Procesar TODAS las alarmas y agregar las que sean elegibles
         for (JsonVariant alarm : alarms) {
             int id = alarm["id_alarma"] | 0;
             String hora = alarm["hora_alarma"] | "";
             String nombre = alarm["nombre_alarma"] | "Sin nombre";
             String diasSemana = alarm["dias_semana"] | "1111111";
             bool activa = alarm["activa_hoy"] | false;
             
             Serial.println("\n╔══════════════════════════════════════════════");
             Serial.println("║ PROCESANDO ALARMA ID: " + String(id));
             Serial.println("╠══════════════════════════════════════════════");
             Serial.println("║ Nombre: '" + nombre + "'");
             Serial.println("║ Hora: " + hora);
             
             // ═══════════════════════════════════════════════════
             // DETECCIÓN DE MÓDULO - MÉTODO SUPER SIMPLE Y DIRECTO
             // ═══════════════════════════════════════════════════
             int moduloDetectado = 1; // Por defecto módulo 1
             
             Serial.println("║");
             Serial.println("║ === EXTRAYENDO NÚMERO DE MÓDULO ===");
             
             // MÉTODO 1: Buscar cualquier dígito del 1-5 en el nombre
             bool moduloEncontrado = false;
             for (int i = 1; i <= MAX_MODULES; i++) {
                 char digitChar = '0' + i; // Convertir i a carácter '1', '2', '3', etc.
                 if (nombre.indexOf(digitChar) >= 0) {
                     moduloDetectado = i;
                     moduloEncontrado = true;
                     Serial.println("║ [OK] Digito '" + String(digitChar) + "' encontrado en nombre");
                     Serial.println("║ [OK] MODULO EXTRAIDO DEL NOMBRE: " + String(moduloDetectado));
                     break; // Usar el primer dígito encontrado
                 }
             }
             
             if (!moduloEncontrado) {
                 Serial.println("║ [WARN] No se encontro digito 1-5 en el nombre");
                 Serial.println("║ [OK] Usando modulo por defecto: 1");
             }
             
             // MÉTODO 2 (VERIFICACIÓN): Intentar leer del JSON si existe
             if (alarm["modulo_detectado"]) {
                 JsonVariant modVar = alarm["modulo_detectado"];
                 int moduloJSON = 0;
                 
                 if (modVar.is<int>()) {
                     moduloJSON = modVar.as<int>();
                 } else if (modVar.is<const char*>()) {
                     moduloJSON = String(modVar.as<const char*>()).toInt();
                 }
                 
                 if (moduloJSON >= 1 && moduloJSON <= MAX_MODULES) {
                     Serial.println("║");
                     Serial.println("║ [VERIFICACIÓN JSON] modulo_detectado = " + String(moduloJSON));
                     
                     if (moduloJSON != moduloDetectado) {
                         Serial.println("║ [WARN] CONFLICTO: Nombre indica " + String(moduloDetectado) + " pero JSON indica " + String(moduloJSON));
                         Serial.println("║ -> Usando valor del JSON: " + String(moduloJSON));
                         moduloDetectado = moduloJSON; // Preferir JSON si hay conflicto
                     } else {
                         Serial.println("║ [OK] JSON coincide con el nombre");
                     }
                 }
             }
             
             Serial.println("║");
             Serial.println("╠══════════════════════════════════════════════");
             Serial.println("║ >>> MODULO FINAL ASIGNADO: " + String(moduloDetectado) + " <<<");
             Serial.println("║ >>> SERVO PIN: " + String(servo_pins[moduloDetectado - 1]) + " <<<");
             Serial.println("╚══════════════════════════════════════════════");
             
             // Solo considerar alarmas activas hoy
             if (!activa || !isDayActive(diasSemana)) {
                 Serial.println("    (Alarma no activa hoy - DESCARTADA)\n");
                 continue;
             }
             
             // Calcular minutos hasta esta alarma
             int minutesUntil = getMinutesUntilAlarmWithTolerance(hora);
             
             Serial.println("    MinutosUntil: " + String(minutesUntil));
             
             // Verificar si está dentro de la ventana de ejecución o es futura
             bool shouldAddToPending = false;
             
             if (minutesUntil == 0 && isWithin10SecondsWindow(hora)) {
                 // Estamos en el minuto correcto Y dentro de la ventana de 0 a +10 segundos
                 shouldAddToPending = true;
                 Serial.println("    !!! DENTRO DE VENTANA (0 a +10 seg) - LISTA PARA EJECUTAR !!!");
             } else if (minutesUntil > 0 && minutesUntil <= 60) {
                 // Alarma futura en la próxima hora - mantener como candidata
                 shouldAddToPending = true;
                 Serial.println("    Alarma futura - agregando a lista de pendientes");
             }
             
             // Agregar a la lista de alarmas pendientes (VERIFICAR QUE NO EXISTA YA)
             if (shouldAddToPending && pending_alarm_count < MAX_PENDING_ALARMS) {
                 // IMPORTANTE: Verificar si esta alarma ya existe en el array
                 bool alreadyExists = false;
                 for (int j = 0; j < pending_alarm_count; j++) {
                     if (pending_alarms[j].id == id) {
                         alreadyExists = true;
                         Serial.println("    [SKIP] Alarma ID " + String(id) + " ya existe en pending_alarms[" + String(j) + "]");
                         break;
                     }
                 }
                 
                 // Solo agregar si NO existe previamente
                 if (!alreadyExists) {
                     pending_alarms[pending_alarm_count].id = id;
                     pending_alarms[pending_alarm_count].time = hora;
                     pending_alarms[pending_alarm_count].name = nombre;
                     pending_alarms[pending_alarm_count].module = moduloDetectado;
                     pending_alarms[pending_alarm_count].active = true;
                     pending_alarm_count++;
                     
                     Serial.println("\n    ==========================================");
                     Serial.println("    = AGREGADA A ALARMAS PENDIENTES #" + String(pending_alarm_count) + " =");
                     Serial.println("    ==========================================");
                     Serial.println("    Modulo: " + String(moduloDetectado));
                     Serial.println("    Faltan: " + String(minutesUntil) + " min\n");
                 }
             }
         }
         
         // Mostrar resumen de alarmas pendientes
         if (pending_alarm_count > 0) {
             Serial.println("\n╔═══════════════════════════════════════════════════════");
             Serial.println("║ RESUMEN DE ALARMAS PENDIENTES: " + String(pending_alarm_count));
             Serial.println("╠═══════════════════════════════════════════════════════");
             for (int i = 0; i < pending_alarm_count; i++) {
                 Serial.println("║ [" + String(i + 1) + "] ID: " + String(pending_alarms[i].id) + 
                              " | Hora: " + pending_alarms[i].time + 
                              " | Módulo: " + String(pending_alarms[i].module) +
                              " | " + pending_alarms[i].name);
             }
             Serial.println("╚═══════════════════════════════════════════════════════\n");
         } else {
             Serial.println("No hay alarmas pendientes");
         }
     } else {
         Serial.println("Sin alarmas para el usuario");
     }
 }
 
 bool isDayActive(String diasSemana) {
     if (diasSemana.length() != 7) {
         return true; // Si formato inválido, asumir activo
     }
 
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) {
         return true; // Si no hay tiempo, asumir activo
     }
     
     // tm_wday: 0=domingo, 1=lunes, ..., 6=sábado
     // diasSemana: posición 0=lunes, 1=martes, ..., 6=domingo
     int dayIndex;
     if (timeinfo.tm_wday == 0) {
         dayIndex = 6; // Domingo
     } else {
         dayIndex = timeinfo.tm_wday - 1; // Lunes=0, Martes=1, etc.
     }
     
     return diasSemana.charAt(dayIndex) == '1';
 }
 
 int detectModuleFromName(String alarmName) {
     String name = alarmName;
     
     Serial.println("    ┌─ detectModuleFromName() ──────────");
     Serial.println("    │ Input: '" + alarmName + "'");
     
     // Convertir a minúsculas para búsqueda case-insensitive
     name.toLowerCase();
     Serial.println("    │ Lower: '" + name + "'");
     
     // Eliminar espacios extra
     name.trim();
     
     // Buscar número de módulo en el nombre con diferentes patrones
     for (int i = 1; i <= MAX_MODULES; i++) {
         String numStr = String(i);
         
         // Patron 1: "modulo X" o "módulo X" (con espacio)
         if (name.indexOf("modulo " + numStr) >= 0 || name.indexOf("módulo " + numStr) >= 0) {
             Serial.println("    | [OK] Patron 'modulo " + numStr + "' encontrado");
             Serial.println("    L- RETORNA: " + numStr);
             return i;
         }
         
         // Patron 2: "moduloX" o "móduloX" (sin espacio)
         if (name.indexOf("modulo" + numStr) >= 0 || name.indexOf("módulo" + numStr) >= 0) {
             Serial.println("    | [OK] Patron 'modulo" + numStr + "' encontrado");
             Serial.println("    L- RETORNA: " + numStr);
             return i;
         }
         
         // Patron 3: "module X"
         if (name.indexOf("module " + numStr) >= 0) {
             Serial.println("    | [OK] Patron 'module " + numStr + "' encontrado");
             Serial.println("    L- RETORNA: " + numStr);
             return i;
         }
         
         // Patron 4: "mod X"
         if (name.indexOf("mod " + numStr) >= 0) {
             Serial.println("    | [OK] Patron 'mod " + numStr + "' encontrado");
             Serial.println("    L- RETORNA: " + numStr);
             return i;
         }
         
         // Patron 5: Termina con " X"
         if (name.endsWith(" " + numStr)) {
             Serial.println("    | [OK] Termina con ' " + numStr + "'");
             Serial.println("    L- RETORNA: " + numStr);
             return i;
         }
         
         // Patron 6: Es exactamente "X"
         if (name == numStr) {
             Serial.println("    | [OK] Es exactamente '" + numStr + "'");
             Serial.println("    L- RETORNA: " + numStr);
             return i;
         }
     }
     
     // Si no se encuentra ningún patrón, usar módulo 1 por defecto
     Serial.println("    | [X] NO se encontro ningun patron");
     Serial.println("    L- RETORNA DEFAULT: 1");
     return 1;
 }
 
 bool isAlarmAlreadyExecuted(int alarmId) {
     if (!time_synchronized) return false;
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) return false;
     
     // Crear timestamp basado en fecha y hora actual (sin segundos)
     unsigned long currentTimeKey = (timeinfo.tm_year + 1900) * 100000000UL + 
                                   (timeinfo.tm_mon + 1) * 1000000UL + 
                                   timeinfo.tm_mday * 10000UL + 
                                   timeinfo.tm_hour * 100UL + 
                                   timeinfo.tm_min;
     
     for (int i = 0; i < executed_alarm_count; i++) {
         if (executed_alarms[i].id == alarmId) {
             // Si ya se ejecutó en este mismo minuto (año+mes+día+hora+minuto), no ejecutar de nuevo
             if (executed_alarms[i].timestamp == currentTimeKey) {
                 Serial.println("SKIP - Alarma " + String(alarmId) + " ya ejecutada en este minuto");
                 return true;
             }
         }
     }
     
     return false;
 }
 
 void markAlarmAsExecuted(int alarmId) {
     if (!time_synchronized) return;
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) return;
     
     // Crear timestamp basado en fecha y hora actual (sin segundos)
     unsigned long currentTimeKey = (timeinfo.tm_year + 1900) * 100000000UL + 
                                   (timeinfo.tm_mon + 1) * 1000000UL + 
                                   timeinfo.tm_mday * 10000UL + 
                                   timeinfo.tm_hour * 100UL + 
                                   timeinfo.tm_min;
     
     // Buscar si ya existe esta alarma y actualizar su timestamp
     for (int i = 0; i < executed_alarm_count; i++) {
         if (executed_alarms[i].id == alarmId) {
             executed_alarms[i].timestamp = currentTimeKey;
             Serial.println("UPDATE - Actualizado timestamp para alarma " + String(alarmId));
             return;
         }
     }
     
     // Si no existe, agregar nueva entrada
     if (executed_alarm_count >= MAX_EXECUTED_ALARMS) {
         executed_alarm_count = 0; // Reiniciar si está lleno
     }
     
     executed_alarms[executed_alarm_count].id = alarmId;
     executed_alarms[executed_alarm_count].timestamp = currentTimeKey;
     executed_alarm_count++;
     
     Serial.println("MARK - Alarma " + String(alarmId) + " marcada como ejecutada en " + 
                    String(timeinfo.tm_hour) + ":" + String(timeinfo.tm_min));
 }
 
 void reportAlarmExecution(int alarmId, bool success, int moduleNum) {
     Serial.println("=============================");
     Serial.println(">>> REPORTANDO AL SERVIDOR <<<");
     Serial.println("Alarm ID: " + String(alarmId));
     Serial.println("Success: " + String(success ? "SI" : "NO"));
     Serial.println("Module: " + String(moduleNum));
     Serial.println("Device: " + device_code);
     Serial.println("=============================");
     
     WiFiClientSecure client;
     client.setInsecure();
     
     HTTPClient http;
     String url = "https://" + String(server_host) + "/report_alarm_execution.php?api_key=" + String(api_key);
     
     Serial.println("URL: " + url);
     
     http.begin(client, url);
     http.addHeader("Content-Type", "application/json");
     http.addHeader("X-API-Key", api_key);
     
     DynamicJsonDocument doc(512);
     doc["device_code"] = device_code;
     doc["alarm_id"] = alarmId;
     doc["executed"] = success;
     doc["module_num"] = moduleNum;
     doc["timestamp"] = millis();
     doc["execution_time"] = getCurrentTime();
     
     String payload;
     serializeJson(doc, payload);
     
     Serial.println("Payload JSON:");
     Serial.println(payload);
     Serial.println("Enviando POST...");
     
     int httpCode = http.POST(payload);
     
     Serial.println("HTTP Code: " + String(httpCode));
     
     if (httpCode == 200) {
         String response = http.getString();
         Serial.println("OK - Ejecución reportada al servidor");
         Serial.println("Respuesta del servidor:");
         Serial.println(response);
     } else if (httpCode > 0) {
         String response = http.getString();
         Serial.println("WARN - Error reportando ejecución");
         Serial.println("Código HTTP: " + String(httpCode));
         Serial.println("Respuesta:");
         Serial.println(response);
     } else {
         Serial.println("ERROR - No se pudo conectar al servidor");
         Serial.println("Error: " + http.errorToString(httpCode));
     }
     
     http.end();
     Serial.println("=============================");
 }
 
 /**
  * Ejecuta múltiples alarmas en PARALELO con LED y Buzzer
  */
 void executeAlarmsInParallel(int alarmIndices[], int count) {
     if (count == 0) return;
     
     // ═══════════════════════════════════════════════════════════════
     // DISPENSACIÓN PARALELA - LED y Buzzer se activan por módulo
     // ═══════════════════════════════════════════════════════════════
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  *** INICIANDO DISPENSACIÓN PARALELA ***                  ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝\n");
     
     // Arrays para trackear información de cada módulo
     int moduleNumbers[MAX_MODULES];
     int servoIndices[MAX_MODULES];
     int alarmIds[MAX_MODULES];
     String alarmNames[MAX_MODULES];
     int validModules = 0;
     
     // FASE 1: Preparar todos los módulos y validar
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  FASE 1: PREPARANDO " + String(count) + " MODULO(S) PARA EJECUCION PARALELA    ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
     
     for (int i = 0; i < count; i++) {
         int alarmIndex = alarmIndices[i];
         PendingAlarm &alarm = pending_alarms[alarmIndex];
         
         int moduleNum = alarm.module;
         
         // Validar módulo
         if (moduleNum < 1 || moduleNum > MAX_MODULES) {
             Serial.println("[ERROR] Modulo " + String(moduleNum) + " invalido - OMITIDO");
             continue;
         }
         
         // Guardar información
         moduleNumbers[validModules] = moduleNum;
         servoIndices[validModules] = moduleNum - 1; // Array index (0-4)
         alarmIds[validModules] = alarm.id;
         alarmNames[validModules] = alarm.name;
         validModules++;
         
         Serial.println("  [" + String(i+1) + "] " + alarm.name);
         Serial.println("      Modulo: " + String(moduleNum) + " | Pin: " + String(servo_pins[moduleNum - 1]));
         Serial.println("      ID Alarma: " + String(alarm.id));
     }
     
     if (validModules == 0) {
         Serial.println("[ERROR] No hay modulos validos para ejecutar");
         return;
     }
     
     Serial.println("\nModulos validados: " + String(validModules));
     
     // FASE 2: Conectar TODOS los servos
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  FASE 2: CONECTANDO " + String(validModules) + " SERVO(S) SIMULTANEAMENTE          ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
     
     for (int i = 0; i < validModules; i++) {
         int moduleNum = moduleNumbers[i];
         int servoIdx = servoIndices[i];
         int pin = servo_pins[servoIdx];
         
         Serial.println("  Conectando MODULO " + String(moduleNum) + " en pin " + String(pin) + "...");
         servos[servoIdx].attach(pin);
     }
     delay(200); // Tiempo para que todos los servos se inicialicen
     
     // FASE 3: Mover TODOS los servos a posición inicial (180°) SIMULTANEAMENTE
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  FASE 3: POSICION INICIAL (180 grados) - TODOS JUNTOS        ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
     
     for (int i = 0; i < validModules; i++) {
         int moduleNum = moduleNumbers[i];
         int servoIdx = servoIndices[i];
         Serial.println("  MODULO " + String(moduleNum) + " -> 180 grados");
         servos[servoIdx].write(180);  // Start at 180°
     }
     delay(800);
     
     // FASE 4: DISPENSING - Move all servos to 0° simultaneously
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  FASE 4: *** DISPENSANDO (0 grados) - TODOS JUNTOS ***   ║");
     Serial.println("║          [LED ENCENDIDO] [BUZZER SONANDO]                  ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
     
     // ═══════════════════════════════════════════════════════════════
     // LED FEEDBACK: Cambiar a modo DISPENSING durante la dispensación
     // ═══════════════════════════════════════════════════════════════
     currentLedState = LED_DISPENSING;  // Cambiar a modo dispensación (control manual)
     // ═══════════════════════════════════════════════════════════════
     
     // ACTIVAR LED Y BUZZER (como en el test)
     digitalWrite(LED_PIN, HIGH);
     ledcWriteTone(BUZZER_PIN, 1300);
     
     for (int i = 0; i < validModules; i++) {
         int moduleNum = moduleNumbers[i];
         int servoIdx = servoIndices[i];
         Serial.println("  MODULO " + String(moduleNum) + " -> 0 grados (DISPENSANDO)");
         servos[servoIdx].write(0);    // Go to 0°
     }
     Serial.println("  → LED ON + Buzzer ON + Servos a 0°");
     delay(1500); // Usar mismo tiempo que el test
     
     // FASE 5: Volver a posición inicial - TODOS JUNTOS
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  FASE 5: RETORNO A 180 grados - TODOS JUNTOS                 ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
     
     // APAGAR LED Y BUZZER (como en el test)
     digitalWrite(LED_PIN, LOW);
     ledcWrite(BUZZER_PIN, 0);
     
     for (int i = 0; i < validModules; i++) {
         int moduleNum = moduleNumbers[i];
         int servoIdx = servoIndices[i];
         Serial.println("  MODULO " + String(moduleNum) + " -> 180 grados");
         servos[servoIdx].write(180);  // Return to 180°
     }
     Serial.println("  → LED OFF + Buzzer OFF + Servos a 180°");
     delay(1500); // Usar mismo tiempo que el test
     
     // FASE 6: Desconectar TODOS los servos
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  FASE 6: DESCONECTANDO SERVOS                              ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
     
     for (int i = 0; i < validModules; i++) {
         int moduleNum = moduleNumbers[i];
         int servoIdx = servoIndices[i];
         Serial.println("  Desconectando MODULO " + String(moduleNum));
         servos[servoIdx].detach();
     }
     
     // FASE 7: Marcar como ejecutadas y reportar al servidor
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  FASE 7: FINALIZACION Y REPORTE AL SERVIDOR                ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝");
     
     for (int i = 0; i < validModules; i++) {
         int moduleNum = moduleNumbers[i];
         int alarmId = alarmIds[i];
         String alarmName = alarmNames[i];
         
         Serial.println("  [" + String(i+1) + "/" + String(validModules) + "] Reportando alarma " + String(alarmId) + " (Modulo " + String(moduleNum) + ")");
         
         // Reportar al servidor
         reportAlarmExecution(alarmId, true, moduleNum);
         
         // Marcar como ejecutada
         markAlarmAsExecuted(alarmId);
         
         // Desactivar la alarma en el array
         int alarmIdx = alarmIndices[i];
         pending_alarms[alarmIdx].active = false;
     }
     
     // ═══════════════════════════════════════════════════════════════
     // LED FEEDBACK: Restaurar estado READY después de dispensar
     // SIEMPRE volver a LED_READY (encendido fijo) después de dispensar
     // ═══════════════════════════════════════════════════════════════
     setLedState(LED_READY);  // Forzar estado READY (LED encendido fijo)
     Serial.println("[LED FEEDBACK] LED restaurado a READY (encendido fijo)");
     // ═══════════════════════════════════════════════════════════════
     
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  EJECUCION PARALELA COMPLETADA EXITOSAMENTE                ║");
     Serial.println("║  " + String(validModules) + " MODULO(S) DISPENSADOS SIMULTANEAMENTE               ║");
     Serial.println("\n╔═══════════════════════════════════════════════════════════╗");
     Serial.println("║  *** DISPENSACIÓN PARALELA COMPLETADA ***                 ║");
     Serial.println("║  LED y Buzzer se apagaron después de cada módulo          ║");
     Serial.println("╚═══════════════════════════════════════════════════════════╝\n");
     
     // NOTA: LED y Buzzer se controlan individualmente en cada módulo
 }
 
 /**
  * Verifica y ejecuta la próxima alarma cuando llegue su momento
  * VERSIÓN MEJORADA: Ejecución en PARALELO de múltiples alarmas simultáneas
  */
 void checkNextAlarm() {
     // BLOQUEO: No permitir ejecuciones concurrentes
     if (is_executing_alarms) {
         Serial.println("[WARN] Ejecución en curso - bloqueando nueva verificación");
         return;
     }
     
     // NUEVO: Revisar TODAS las alarmas pendientes
     if (pending_alarm_count == 0) {
         return; // No hay alarmas pendientes
     }
     
     // Forzar sincronización antes de verificar
     forceTimeSync();
     
     String currentTime = getCurrentTime();
     
     Serial.println("╔═════════════════════════════════════════════════════════════╗");
     Serial.println("║  VERIFICANDO " + String(pending_alarm_count) + " ALARMAS PENDIENTES (VENTANA 0 a +10s)     ║");
     Serial.println("║  Hora actual: " + currentTime + "                                ║");
     Serial.println("╚═════════════════════════════════════════════════════════════╝");
     
     // PASO 1: Identificar qué alarmas deben ejecutarse AHORA
     int alarmsToExecute[MAX_PENDING_ALARMS];
     int executeCount = 0;
     
     for (int i = 0; i < pending_alarm_count; i++) {
         PendingAlarm &alarm = pending_alarms[i];
         
         if (!alarm.active) {
             continue; // Saltar alarmas desactivadas
         }
         
         // Verificar si está dentro de la ventana de 0 a +10 segundos
         bool shouldExecuteNow = isWithin10SecondsWindow(alarm.time);
         int minutesToAlarm = getMinutesUntilAlarmWithTolerance(alarm.time);
         
         Serial.println("  [" + String(i+1) + "/" + String(pending_alarm_count) + "] " + alarm.name + " (" + alarm.time + ")");
         Serial.println("      Modulo: " + String(alarm.module) + " | Ventana (0 a +10s): " + (shouldExecuteNow ? "SI [OK]" : "NO [X]"));
         
         // DEBUG ADICIONAL: Mostrar diferencia en segundos
         struct tm timeinfo;
         if (getLocalTime(&timeinfo)) {
             int alarmHour = alarm.time.substring(0, 2).toInt();
             int alarmMinute = alarm.time.substring(3, 5).toInt();
             int alarmSecond = alarm.time.substring(6, 8).toInt();
             
             long currentSeconds = timeinfo.tm_hour * 3600 + timeinfo.tm_min * 60 + timeinfo.tm_sec;
             long alarmSeconds = alarmHour * 3600 + alarmMinute * 60 + alarmSecond;
             long diffSeconds = currentSeconds - alarmSeconds;
             
             Serial.println("      DEBUG: Diferencia = " + String(diffSeconds) + " segundos (actual - alarma)");
         }
         
         if (shouldExecuteNow && !isAlarmAlreadyExecuted(alarm.id)) {
             // Agregar a la lista de ejecución
             alarmsToExecute[executeCount] = i;
             executeCount++;
             Serial.println("      >>> MARCADA PARA EJECUCION PARALELA <<<");
         } else if (shouldExecuteNow && isAlarmAlreadyExecuted(alarm.id)) {
             Serial.println("      [WARN] SKIP - Alarma " + String(alarm.id) + " ya ejecutada en este minuto");
         } else if (isTimeForPreAlert(alarm.time)) {
             Serial.println("      [ALERTA] PRE-ALERTA: Faltan ~30seg (Modulo " + String(alarm.module) + ")");
         }
     }
     
     // PASO 2: Si hay alarmas para ejecutar, hacerlo en PARALELO
     if (executeCount > 0) {
         Serial.println("");
         Serial.println("╔═══════════════════════════════════════════════════════════╗");
         Serial.println("║  EJECUCION EN PARALELO DE " + String(executeCount) + " ALARMA(S) SIMULTANEA(S)         ║");
         Serial.println("╚═══════════════════════════════════════════════════════════╝");
         
         // Activar bandera de ejecución
         is_executing_alarms = true;
         
         // Ejecutar todas las alarmas en paralelo
         executeAlarmsInParallel(alarmsToExecute, executeCount);
         
         // Desactivar bandera de ejecución

         is_executing_alarms = false;
     }
     
 }
 
 int getMinutesUntilAlarm(String alarmTime) {
     if (!time_synchronized) return -1;
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) return -1;
     
     // Parsear hora de la alarma (formato "HH:MM")
     int alarmHour = alarmTime.substring(0, 2).toInt();
     int alarmMinute = alarmTime.substring(3, 5).toInt();
     
     // Tiempo actual en minutos desde medianoche
     int currentMinutes = timeinfo.tm_hour * 60 + timeinfo.tm_min;
     
     // Tiempo de alarma en minutos desde medianoche
     int alarmMinutes = alarmHour * 60 + alarmMinute;
     
     // Calcular diferencia
     int diff = alarmMinutes - currentMinutes;
     
     // Si la diferencia es negativa, la alarma ya pasó hoy
     if (diff < 0) {
         return -1; // Ya pasó
     }
     
     return diff; // Minutos restantes
 }
 
 int getMinutesUntilAlarmWithTolerance(String alarmTime) {
     if (!time_synchronized) return -999;
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) return -999;
     
     // Parsear hora de la alarma (formato "HH:MM" o "HH:MM:SS")
     int alarmHour = 0, alarmMinute = 0, alarmSecond = 0;
     
     if (alarmTime.length() >= 5) {
         alarmHour = alarmTime.substring(0, 2).toInt();
         alarmMinute = alarmTime.substring(3, 5).toInt();
         
         // Si hay segundos, parsearlos también
         if (alarmTime.length() >= 8 && alarmTime.charAt(5) == ':') {
             alarmSecond = alarmTime.substring(6, 8).toInt();
         }
     } else {
         Serial.println("ERROR - Formato de hora invalido: " + alarmTime);
         return -999;
     }
     
     // Tiempo actual en segundos desde medianoche
     long currentSeconds = timeinfo.tm_hour * 3600 + timeinfo.tm_min * 60 + timeinfo.tm_sec;
     // Tiempo de alarma en segundos desde medianoche
     long alarmSeconds = alarmHour * 3600 + alarmMinute * 60 + alarmSecond;
     
     // Calcular diferencia en segundos, luego convertir a minutos
     long diffSeconds = alarmSeconds - currentSeconds;
     int diffMinutes = diffSeconds / 60;
     
     // Si la diferencia es menor a 30 segundos, considerarla como 0 minutos
     if (abs(diffSeconds) < 30) {
         diffMinutes = 0;
     }
     
     Serial.println("DEBUG - Hora actual: " + String(timeinfo.tm_hour) + ":" + String(timeinfo.tm_min) + ":" + String(timeinfo.tm_sec));
     Serial.println("DEBUG - Hora alarma: " + alarmTime + " -> Diferencia: " + String(diffMinutes) + " min");
     
     return diffMinutes; // Puede ser negativo para alarmas que pasaron recientemente
 }
 
 void checkNotifications() {
     WiFiClientSecure client;
     client.setInsecure();
     
     HTTPClient http;
     String url = "https://" + String(server_host) + "/get_notifications_esp.php?code=" + device_code + "&api_key=" + api_key;
     
     http.begin(client, url);
     http.addHeader("Content-Type", "application/json");
     
     int httpCode = http.GET();
     
     if (httpCode == 200) {
         String response = http.getString();
         
         DynamicJsonDocument doc(2048);
         DeserializationError error = deserializeJson(doc, response);
         
         if (!error) {
             JsonArray notifications = doc["notifications"];
             int notificationCount = doc["count"];
             
             if (notificationCount > 0) {
                 Serial.println("NOTIF - Recibidas " + String(notificationCount) + " notificaciones");
                 
                 for (JsonVariant notification : notifications) {
                     String type = notification["type"];
                     String message = notification["message"];
                     
                     Serial.println("  NOTIF - " + type + ": " + message);
                     
                     if (type == "new_alarm" || type == "alarm_updated") {
                         Serial.println("FAST - Nueva alarma detectada, verificando inmediatamente...");
                         checkAllAlarms();
                     }
                 }
             }
         }
     }
     
     http.end();
 }
 
 void checkTimeSync() {
     struct tm timeinfo;
     
     if (!getLocalTime(&timeinfo)) {
         Serial.println("WARN - Perdida de sincronizacion NTP - Reintentando...");
         time_synchronized = false;
         
         // Intentar sincronización rápida sin todo el proceso completo
         Serial.println("Intentando resincronizacion rapida...");
         configTime(gmtOffset_sec, daylightOffset_sec, "pool.ntp.org", "time.google.com");
         
         // Esperar hasta 5 segundos para resincronización
         for (int i = 0; i < 10; i++) {
             delay(500);
             if (getLocalTime(&timeinfo)) {
                 int year = timeinfo.tm_year + 1900;
                 if (year >= 2020 && year <= 2030) {
                     time_synchronized = true;
                     Serial.println("OK - Resincronizacion rapida exitosa");
                     Serial.printf("Tiempo: %02d/%02d/%04d %02d:%02d:%02d\n",
                         timeinfo.tm_mday, timeinfo.tm_mon + 1, year,
                         timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec);
                     return;
                 }
             }
         }
         
         // Si falla la resincronización rápida, hacer setup completo
         Serial.println("Resincronizacion rapida fallida - Ejecutando setup completo...");
         setupTime();
         return;
     }
     
     int currentYear = timeinfo.tm_year + 1900;
     if (currentYear < 2020 || currentYear > 2030) {
         Serial.println("WARN - Tiempo invalido detectado (año " + String(currentYear) + ")");
         time_synchronized = false;
         setupTime();
         return;
     }
     
     if (!time_synchronized) {
         time_synchronized = true;
         Serial.println("OK - Sincronizacion NTP restaurada");
     }
     
     Serial.printf("TIME - Verificación OK - %02d/%02d/%04d %02d:%02d:%02d\n",
         timeinfo.tm_mday, timeinfo.tm_mon + 1, currentYear,
         timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec);
 }
 
 void checkLinkageStatus() {
     if (!device_registered) {
         Serial.println("LINKAGE - SKIP: Dispositivo no registrado");
         return;
     }
 
     Serial.println("=== VERIFICANDO VINCULACIÓN DETALLADA ===");
     Serial.println("Device Code: " + device_code);
     Serial.println("Server: " + String(server_host));
     
     WiFiClientSecure client;
     client.setInsecure();
     client.setTimeout(10000);
     
     HTTPClient http;
     String url = "https://" + String(server_host) + "/esp_discovery.php?api_key=" + String(api_key) + "&check_linkage=1&device_code=" + device_code;
     
     Serial.println("URL completa: " + url);
     
     http.begin(client, url);
     http.setTimeout(10000);
     http.addHeader("X-API-Key", api_key);
     http.addHeader("User-Agent", "ESP32-PastillSSL/5.0");
     
     Serial.println("Enviando petición de vinculación...");
     int httpCode = http.GET();
     String response = http.getString();
     
     Serial.println("=== RESPUESTA DEL SERVIDOR ===");
     Serial.println("HTTP Code: " + String(httpCode));
     Serial.println("Response Length: " + String(response.length()));
     Serial.println("Raw Response: " + response);
     Serial.println("===============================");
     
     if (httpCode == 200) {
         DynamicJsonDocument doc(1024);
         DeserializationError error = deserializeJson(doc, response);
         
         if (error) {
             Serial.println("ERROR - JSON Parse Error: " + String(error.c_str()));
             Serial.println("Respuesta no es JSON válido, intentando buscar patrones...");
             
             // Buscar patrones en texto plano
             if (response.indexOf("linked_user_id") >= 0) {
                 Serial.println("Encontrado 'linked_user_id' en la respuesta");
                 // Intentar extraer ID manualmente
                 int startPos = response.indexOf("linked_user_id") + 15;
                 int endPos = response.indexOf("\"", startPos + 2);
                 if (endPos > startPos) {
                     String extractedId = response.substring(startPos + 2, endPos);
                     if (extractedId.length() > 0 && extractedId != "null") {
                         linked_user_id = extractedId;
                         Serial.println("LINKAGE - ID extraído manualmente: " + linked_user_id);
                     }
                 }
             }
         } else {
             Serial.println("JSON parseado correctamente");
             
             // Mostrar todo el contenido del JSON para debugging
             String jsonString;
             serializeJson(doc, jsonString);
             Serial.println("JSON Content: " + jsonString);
             
             if (doc.containsKey("linked_user_id")) {
                 Serial.println("Campo 'linked_user_id' encontrado");
                 
                 if (doc["linked_user_id"].isNull()) {
                     Serial.println("linked_user_id es NULL - ESP no vinculado");
                     if (!linked_user_id.isEmpty()) {
                         Serial.println("LINKAGE - Usuario desvinculado");
                         Serial.println("LINKAGE - Limpiando alarmas en memoria...");
                         linked_user_id = "";
                         // Limpiar alarmas pendientes
                         pending_alarm_count = 0;
                         for (int i = 0; i < MAX_PENDING_ALARMS; i++) {
                             pending_alarms[i].active = false;
                         }
                         // Limpiar alarmas ejecutadas
                         executed_alarm_count = 0;
                         Serial.println("LINKAGE - Alarmas limpiadas");
                     }
                 } else {
                     String newUserId = doc["linked_user_id"].as<String>();
                     Serial.println("linked_user_id value: '" + newUserId + "'");
                     
                     if (newUserId != linked_user_id) {
                         // Limpiar alarmas del usuario anterior
                         pending_alarm_count = 0;
                         executed_alarm_count = 0;
                         
                         linked_user_id = newUserId;
                         Serial.println("SUCCESS - ¡ESP vinculado al usuario ID: " + linked_user_id + "!");
                         Serial.println("LINKAGE - Ahora puede recibir alarmas de todos los módulos");
                         Serial.println("LINKAGE - Alarmas anteriores limpiadas");
                     } else if (!linked_user_id.isEmpty()) {
                         Serial.println("LINKAGE - Confirmado - vinculado al usuario: " + linked_user_id);
                     }
                 }
             } else {
                 Serial.println("ERROR - Campo 'linked_user_id' no encontrado en JSON");
                 Serial.println("Campos disponibles en JSON:");
                 for (JsonPair kv : doc.as<JsonObject>()) {
                     Serial.println("  - " + String(kv.key().c_str()) + " = " + kv.value().as<String>());
                 }
             }
         }
     } else {
         Serial.println("ERROR - HTTP Request Failed: " + String(httpCode));
         if (response.length() > 0) {
             Serial.println("Error Response: " + response);
         }
     }
     
     Serial.println("Estado actual: linked_user_id = '" + linked_user_id + "'");
     Serial.println("========================================");
     
     http.end();
 }
 
 void forceCheckLinkage() {
     Serial.println("=== VERIFICACIÓN FORZADA DE VINCULACIÓN ===");
     Serial.println("Probando múltiples endpoints para detectar vinculación...");
     
     // Método 1: esp_discovery.php
     WiFiClientSecure client1;
     client1.setInsecure();
     HTTPClient http1;
     String url1 = "https://" + String(server_host) + "/esp_discovery.php?device_code=" + device_code + "&api_key=" + String(api_key);
     
     Serial.println("Método 1 - URL: " + url1);
     http1.begin(client1, url1);
     http1.addHeader("X-API-Key", api_key);
     
     int code1 = http1.GET();
     String resp1 = http1.getString();
     Serial.println("Método 1 - HTTP: " + String(code1) + " Response: " + resp1);
     http1.end();
     
     // Método 2: vincular_esp.php con consulta
     WiFiClientSecure client2;
     client2.setInsecure();
     HTTPClient http2;
     String url2 = "https://" + String(server_host) + "/vincular_esp.php?action=check&esp_code=" + device_code + "&api_key=" + String(api_key);
     
     Serial.println("Método 2 - URL: " + url2);
     http2.begin(client2, url2);
     http2.addHeader("X-API-Key", api_key);
     
     int code2 = http2.GET();
     String resp2 = http2.getString();
     Serial.println("Método 2 - HTTP: " + String(code2) + " Response: " + resp2);
     http2.end();
     
     // Método 3: get_all_user_alarms.php para ver si reconoce el ESP
     WiFiClientSecure client3;
     client3.setInsecure();
     HTTPClient http3;
     String url3 = "https://" + String(server_host) + "/get_esp_user.php?device_code=" + device_code + "&api_key=" + String(api_key);
     
     Serial.println("Método 3 - URL: " + url3);
     http3.begin(client3, url3);
     http3.addHeader("X-API-Key", api_key);
     
     int code3 = http3.GET();
     String resp3 = http3.getString();
     Serial.println("Método 3 - HTTP: " + String(code3) + " Response: " + resp3);
     http3.end();
     
     Serial.println("=== ANÁLISIS DE RESPUESTAS ===");
     
     // Analizar todas las respuestas buscando user_id
     String responses[] = {resp1, resp2, resp3};
     String methods[] = {"esp_discovery", "vincular_esp", "get_esp_user"};
     
     for (int i = 0; i < 3; i++) {
         Serial.println("Analizando " + methods[i] + "...");
         
         if (responses[i].indexOf("linked_user_id") >= 0 || responses[i].indexOf("user_id") >= 0) {
             Serial.println("  Contiene información de usuario!");
             
             // Buscar linked_user_id
             int pos = responses[i].indexOf("linked_user_id");
             if (pos >= 0) {
                 int startPos = responses[i].indexOf(":", pos) + 1;
                 int endPos = responses[i].indexOf(",", startPos);
                 if (endPos == -1) endPos = responses[i].indexOf("}", startPos);
                 
                 String extractedId = responses[i].substring(startPos, endPos);
                 extractedId.trim();
                 extractedId.replace("\"", "");
                 extractedId.replace(" ", "");
                 
                 if (extractedId.length() > 0 && extractedId != "null") {
                     linked_user_id = extractedId;
                     Serial.println("  ENCONTRADO linked_user_id: " + linked_user_id);
                     Serial.println("¡VINCULACIÓN DETECTADA VIA " + methods[i] + "!");
                     return;
                 }
             }
             
             // Buscar user_id alternativo
             pos = responses[i].indexOf("user_id");
             if (pos >= 0) {
                 int startPos = responses[i].indexOf(":", pos) + 1;
                 int endPos = responses[i].indexOf(",", startPos);
                 if (endPos == -1) endPos = responses[i].indexOf("}", startPos);
                 
                 String extractedId = responses[i].substring(startPos, endPos);
                 extractedId.trim();
                 extractedId.replace("\"", "");
                 extractedId.replace(" ", "");
                 
                 if (extractedId.length() > 0 && extractedId != "null") {
                     linked_user_id = extractedId;
                     Serial.println("  ENCONTRADO user_id: " + linked_user_id);
                     Serial.println("¡VINCULACIÓN DETECTADA VIA " + methods[i] + "!");
                     return;
                 }
             }
         } else {
             Serial.println("  No contiene información de usuario");
         }
     }
     
     Serial.println("RESULTADO: No se detectó vinculación en ningún método");
     Serial.println("========================================");
 }
 
 void setupTime() {
     Serial.println("=== SINCRONIZACION NTP MEJORADA ===");
     
     // Verificar conectividad básica antes de intentar NTP
     if (WiFi.status() != WL_CONNECTED) {
         Serial.println("ERROR - WiFi desconectado, no se puede sincronizar NTP");
         time_synchronized = false;
         return;
     }
     
     // Mostrar información de red
     Serial.println("WiFi conectado - IP: " + WiFi.localIP().toString());
     Serial.println("Gateway: " + WiFi.gatewayIP().toString());
     Serial.println("DNS: " + WiFi.dnsIP().toString());
     
     Serial.println("Verificando conectividad a internet...");
     
     // Probar conectividad antes de intentar NTP
     bool hasInternet = testInternetConnectivity();
     if (!hasInternet) {
         Serial.println("Advertencia: Sin conectividad a internet detectada");
         Serial.println("Intentando NTP de todas formas...");
     } else {
         Serial.println("Conectividad confirmada - Procediendo con NTP");
     }
     
     Serial.println("Iniciando sincronizacion NTP con " + String(ntpServerCount) + " servidores...");
     
     for (int serverIndex = 0; serverIndex < ntpServerCount; serverIndex++) {
         const char* currentServer = ntpServers[serverIndex];
         Serial.println("Probando servidor [" + String(serverIndex + 1) + "/" + String(ntpServerCount) + "]: " + String(currentServer));
         
         // Configurar servidor NTP con timeout balanceado
         configTime(gmtOffset_sec, daylightOffset_sec, currentServer);
         
         // Parámetros balanceados: suficiente tiempo pero no excesivo
         int maxAttempts = 12;  // Balance entre velocidad y confiabilidad
         int delayMs = 1200;    // Balance entre velocidad y confiabilidad
         
         Serial.print("Esperando sincronización");
         int attempts = 0;
         struct tm timeinfo;
         
         // Intentar obtener tiempo con timeout más agresivo
         while (!getLocalTime(&timeinfo) && attempts < maxAttempts) {
             Serial.print(".");
             delay(delayMs);
             attempts++;
             
             // Verificar WiFi durante el proceso
             if (WiFi.status() != WL_CONNECTED) {
                 Serial.println(" WIFI_LOST");
                 break;
             }
         }
         
         // Verificar si obtuvimos tiempo válido
         if (getLocalTime(&timeinfo)) {
             int currentYear = timeinfo.tm_year + 1900;
             
             // Validar que el tiempo sea razonable (año 2024-2030)
             if (currentYear >= 2024 && currentYear <= 2030) {
                 time_synchronized = true;
                 Serial.println(" EXITO!");
                 Serial.println("Servidor NTP exitoso: " + String(currentServer));
                 Serial.printf("Tiempo Buenos Aires: %02d/%02d/%04d %02d:%02d:%02d (GMT-3)\n",
                     timeinfo.tm_mday, timeinfo.tm_mon + 1, currentYear,
                     timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec);
                 Serial.println("Sincronizacion NTP completada correctamente");
                 return;
             } else {
                 Serial.println(" TIEMPO_INVALIDO (año " + String(currentYear) + ")");
                 time_synchronized = false;
             }
         } else {
             Serial.println(" TIMEOUT");
         }
         
         // Pausa entre servidores para permitir reset de conexión
         if (serverIndex < ntpServerCount - 1) {
             Serial.println("Esperando 1 segundo antes del siguiente servidor...");
             delay(1000);
         }
     }
     
     // Si llegamos aquí, ningún servidor funcionó
     Serial.println("ERROR CRITICO - No se pudo sincronizar con ningun servidor NTP");
     Serial.println("INTENTANDO SINCRONIZACION DE EMERGENCIA...");
     
     // Múltiples intentos de emergencia con diferentes configuraciones
     Serial.println("Probando configuraciones de emergencia...");
     
     const char* emergencyServers[][2] = {
         {"pool.ntp.org", "time.google.com"},
         {"time.cloudflare.com", "time.nist.gov"},
         {"0.pool.ntp.org", "1.pool.ntp.org"}
     };
     
     for (int attempt = 0; attempt < 3; attempt++) {
         Serial.println("Intento emergencia " + String(attempt + 1) + "/3 con servidores: " +
                       String(emergencyServers[attempt][0]) + ", " + String(emergencyServers[attempt][1]));
                       
         configTime(gmtOffset_sec, daylightOffset_sec, 
                   emergencyServers[attempt][0], emergencyServers[attempt][1]);
         
         // Esperar hasta 5 segundos por cada intento de emergencia
         for (int wait = 0; wait < 10; wait++) {
             delay(500);
             struct tm timeinfo;
             if (getLocalTime(&timeinfo)) {
                 int currentYear = timeinfo.tm_year + 1900;
                 if (currentYear >= 2020 && currentYear <= 2035) {
                     time_synchronized = true;
                     Serial.println("SINCRONIZACION DE EMERGENCIA EXITOSA!");
                     Serial.printf("Tiempo obtenido: %02d/%02d/%04d %02d:%02d:%02d (GMT-3)\n",
                         timeinfo.tm_mday, timeinfo.tm_mon + 1, currentYear,
                         timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec);
                     Serial.println("Servidor exitoso: " + String(emergencyServers[attempt][0]));
                     return;
                 }
             }
         }
         Serial.println("Intento " + String(attempt + 1) + " fallido, probando siguiente...");
     }
     
     Serial.println("El dispositivo funcionara sin tiempo preciso");
     time_synchronized = false;
     
     // Información de troubleshooting
     Serial.println("TROUBLESHOOTING:");
     Serial.println("1. Verificar conexion WiFi");
     Serial.println("2. Verificar firewall del router");
     Serial.println("3. Verificar configuracion DNS");
     Serial.println("4. Probar reiniciar el ESP32");
 }
 
 String getCurrentTime() {
     struct tm timeinfo;
     
     // Intentar obtener tiempo hasta 3 veces antes de fallar
     bool timeObtained = false;
     for (int retry = 0; retry < 3; retry++) {
         if (getLocalTime(&timeinfo)) {
             timeObtained = true;
             break;
         }
         delay(100); // Pequeña pausa entre reintentos
     }
     
     if (!timeObtained) {
         // Solo marcar como no sincronizado después de múltiples fallos
         static int failCount = 0;
         failCount++;
         
         if (failCount >= 5) { // Solo después de 5 fallos consecutivos
             if (time_synchronized) {
                 Serial.println("WARN - Perdida persistente de tiempo detectada");
                 time_synchronized = false;
             }
             failCount = 0; // Reset contador
         }
         
         return "??:??:??";
     }
     
     int currentYear = timeinfo.tm_year + 1900;
     if (currentYear < 2020 || currentYear > 2030) {
         static unsigned long lastInvalidWarning = 0;
         if (millis() - lastInvalidWarning > 30000) // Solo avisar cada 30 segundos
         {
             Serial.println("WARN - Tiempo invalido - año: " + String(currentYear));
             lastInvalidWarning = millis();
         }
         time_synchronized = false;
         return "!!" + String(timeinfo.tm_hour) + ":" + String(timeinfo.tm_min) + ":" + String(timeinfo.tm_sec);
     }
     
     // Tiempo válido obtenido
     if (!time_synchronized) {
         time_synchronized = true;
         Serial.println("INFO - Tiempo sincronizado correctamente");
     }
     
     char timeString[10];
     sprintf(timeString, "%02d:%02d:%02d", timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec);
     return String(timeString);
 }
 
 bool isWithin10SecondsWindow(String alarmTime) {
     if (!time_synchronized) return false;
     
     // Parsear la hora de la alarma (formato HH:MM:SS)
     int alarmHour = alarmTime.substring(0, 2).toInt();
     int alarmMinute = alarmTime.substring(3, 5).toInt();
     int alarmSecond = alarmTime.substring(6, 8).toInt();
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) {
         return false;
     }
     
     // Convertir ambos horarios a segundos desde medianoche
     long currentSeconds = timeinfo.tm_hour * 3600 + timeinfo.tm_min * 60 + timeinfo.tm_sec;
     long alarmSeconds = alarmHour * 3600 + alarmMinute * 60 + alarmSecond;
     
     // Calcular la diferencia en segundos (puede ser positiva o negativa)
     long timeDiff = currentSeconds - alarmSeconds;
     
     // Manejar cambio de día si es necesario
     if (timeDiff > 43200) timeDiff -= 86400;  // Si > 12 horas, restar 24 horas
     if (timeDiff < -43200) timeDiff += 86400; // Si < -12 horas, sumar 24 horas
     
     // MODIFICADO: Solo ejecutar cuando estamos EN o DESPUÉS del horario (0 a +10 segundos)
     // Esto evita ejecutar 5 segundos ANTES y luego 5 segundos DESPUÉS
     // Ahora solo ejecuta UNA VEZ cuando llega o pasa el horario exacto
     return (timeDiff >= 0 && timeDiff <= 10);
 }
 
 bool hasAlarmWithin10Seconds() {
     for (int i = 0; i < pending_alarm_count; i++) {
         if (pending_alarms[i].active && isWithin10SecondsWindow(pending_alarms[i].time)) {
             return true;
         }
     }
     return false;
 }
 
 void showFinalStatus() {
     Serial.println("=== ESTADO FINAL DEL DISPOSITIVO ===");
     Serial.println("Versión: " + firmware_version + " (con LED y Buzzer)");
     Serial.println("WiFi: OK - Conectado a '" + WiFi.SSID() + "' (" + WiFi.localIP().toString() + ")");
     Serial.println("Señal WiFi: " + String(WiFi.RSSI()) + " dBm");
     Serial.println("mDNS: http://pastillssl.local (si está disponible)");
     Serial.println("Tiempo NTP: " + String(time_synchronized ? "OK - Registrado" : "ERROR - No registrado"));
     Serial.println("Registro: " + String(device_registered ? "OK - Registrado" : "ERROR - No registrado"));
     Serial.println("Validado: " + String(device_validated ? "OK - Validado" : "WARN - No validado"));
     Serial.println("Usuario vinculado: " + String(!linked_user_id.isEmpty() ? "ID " + linked_user_id : "No vinculado"));
     Serial.println("Módulos soportados: " + String(MAX_MODULES));
     Serial.print("Pines de servo: ");
     for (int i = 0; i < MAX_MODULES; i++) {
         Serial.print(String(servo_pins[i]));
         if (i < MAX_MODULES - 1) Serial.print(", ");
     }
     Serial.println();
     Serial.println("LED: GPIO " + String(LED_PIN) + " | Buzzer: GPIO " + String(BUZZER_PIN));
     Serial.println("CAMBIO DE RED: Mantén botón BOOT 5 seg para abrir portal WiFi");
     Serial.println("=====================================");
 }
 
 void displayCurrentTime() {
     // Forzar sincronización antes de mostrar hora
     forceTimeSync();
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) {
         Serial.println("Sin sincronizacion NTP - Reintentando...");
         return;
     }
     
     char timeStr[10];
     sprintf(timeStr, "%02d:%02d:%02d", timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec);
     
     String status = linked_user_id.isEmpty() ? " | No vinculado" : " | Usuario: " + linked_user_id;
     Serial.println("HORA PRECISA: " + String(timeStr) + " (Buenos Aires)" + status);
     
     // Mostrar información de alarmas pendientes
     if (pending_alarm_count > 0) {
         Serial.println("╔══════════════════════════════════════════════════════════╗");
         Serial.println("║  ALARMAS PENDIENTES: " + String(pending_alarm_count) + "                                   ║");
         Serial.println("╚══════════════════════════════════════════════════════════╝");
         
         for (int i = 0; i < pending_alarm_count; i++) {
             if (pending_alarms[i].active) {
                 int minutesLeft = getMinutesUntilAlarm(pending_alarms[i].time);
                 Serial.print("  [" + String(i+1) + "] " + pending_alarms[i].name);
                 Serial.print(" a las " + pending_alarms[i].time);
                 Serial.println(" (Módulo " + String(pending_alarms[i].module) + ")");
                 
                 if (minutesLeft >= 0) {
                     if (minutesLeft == 0) {
                         Serial.println("      [AHORA] ES AHORA! Ejecutando...");
                     } else if (minutesLeft <= 5) {
                         Serial.println("      [PRONTO] Faltan " + String(minutesLeft) + " minuto(s) - Muy pronto!");
                     } else {
                         Serial.println("      [INFO] Faltan " + String(minutesLeft) + " minuto(s)");
                     }
                 }
             }
         }
         Serial.println("");
     } else {
         Serial.println("Sin alarmas proximas configuradas");
     }
 }
 
 void forceTimeSync() {
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) {
         // Si no se puede obtener el tiempo, intentar reconfigurar NTP rápidamente
         configTime(gmtOffset_sec, daylightOffset_sec, ntpServers[0]);
         delay(100);
         getLocalTime(&timeinfo);
     }
 }
 
 bool isExactTimeMatch(String alarmTime) {
     if (!time_synchronized) {
         Serial.println("Sin sincronizacion - no se puede comparar");
         return false;
     }
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) {
         Serial.println("Error obteniendo tiempo local");
         return false;
     }
     
     int alarmHour, alarmMinute;
     if (sscanf(alarmTime.c_str(), "%d:%d", &alarmHour, &alarmMinute) < 2) {
         Serial.println("Formato de hora invalido: " + alarmTime);
         return false;
     }
     
     // Comparación exacta 1 a 1: hora y minuto
     bool exactMatch = (timeinfo.tm_hour == alarmHour && timeinfo.tm_min == alarmMinute);
     
     if (exactMatch) {
         Serial.println("COINCIDENCIA EXACTA: Actual(" + String(timeinfo.tm_hour) + ":" + 
                       String(timeinfo.tm_min) + ") = Alarma(" + alarmTime + ")");
     }
     
     return exactMatch;
 }
 
 bool isTimeForPreAlert(String alarmTime) {
     if (!time_synchronized) return false;
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) return false;
     
     int alarmHour, alarmMinute;
     if (sscanf(alarmTime.c_str(), "%d:%d", &alarmHour, &alarmMinute) < 2) return false;
     
     // Calcular segundos actuales desde medianoche
     long currentSeconds = timeinfo.tm_hour * 3600 + timeinfo.tm_min * 60 + timeinfo.tm_sec;
     long alarmSeconds = alarmHour * 3600 + alarmMinute * 60; // Alarma en punto (segundos = 0)
     
     // Verificar si faltan exactamente 30 segundos
     long diff = alarmSeconds - currentSeconds;
     
     // Pre-alerta si faltan entre 25 y 35 segundos (ventana de 10 segundos para asegurar captura)
     if (diff >= 25 && diff <= 35) {
         Serial.println("FALTAN " + String(diff) + " segundos para " + alarmTime);
         return true;
     }
     
     return false;
 }
 
 void parseDebugLogs(String response, String currentTime) {
     Serial.println("=== parseDebugLogs DESACTIVADA - Sistema migrado a pending_alarms[] ===");
     Serial.println("NOTA: Esta función era parte del sistema antiguo de alarma única");
     Serial.println("El nuevo sistema usa processAlarmResponse() con array de alarmas pendientes");
 }
 
 bool testInternetConnectivity() {
     Serial.println("Probando conectividad a internet...");
     
     WiFiClient client;
     client.setTimeout(3000); // Timeout de 3 segundos
     
     // Probar múltiples servidores conocidos
     const char* testServers[] = {"8.8.8.8", "1.1.1.1", "208.67.222.222"}; // Google, Cloudflare, OpenDNS
     const int ports[] = {53, 53, 53};
     
     for (int i = 0; i < 3; i++) {
         Serial.println("Probando " + String(testServers[i]) + ":" + String(ports[i]) + "...");
         
         if (client.connect(testServers[i], ports[i])) {
             Serial.println("Conectividad OK - Internet accesible via " + String(testServers[i]));
             client.stop();
             return true;
         }
         delay(500); // Pequeña pausa entre tests
     }
     
     Serial.println("Sin conectividad - Todos los servidores fallaron");
     Serial.println("Posibles causas: Red desconectada, firewall, DNS bloqueado");
     return false;
 }
 
 bool isWithin10SecondsOfAlarm(String alarmTime) {
     if (!time_synchronized) return false;
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) return false;
     
     int alarmHour, alarmMinute;
     if (sscanf(alarmTime.c_str(), "%d:%d", &alarmHour, &alarmMinute) < 2) return false;
     
     // Calcular segundos actuales desde medianoche
     long currentSeconds = timeinfo.tm_hour * 3600 + timeinfo.tm_min * 60 + timeinfo.tm_sec;
     long alarmSeconds = alarmHour * 3600 + alarmMinute * 60; // Alarma en punto (segundos = 0)
     
     // Calcular diferencia en segundos
     long diff = alarmSeconds - currentSeconds;
     
     // Si faltan entre 0 y 10 segundos
     if (diff >= 0 && diff <= 10) {
         return true;
     }
     
     return false;
 }
 
 void intensiveAlarmCheck() {
     if (pending_alarm_count == 0) return;
     
     // Forzar sincronización de tiempo para máxima precisión
     forceTimeSync();
     
     String currentTime = getCurrentTime();
     
     // Obtener tiempo actual preciso
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) {
         Serial.println("ERROR - No se pudo obtener tiempo en verificacion intensiva");
         return;
     }
     
     Serial.println("INTENSIVO - Verificando " + String(pending_alarm_count) + " alarmas pendientes");
     
     // Revisar cada alarma pendiente
     for (int i = 0; i < pending_alarm_count; i++) {
         if (!pending_alarms[i].active) continue;
         
         // Calcular segundos restantes hasta la alarma
         int alarmHour, alarmMinute;
         if (sscanf(pending_alarms[i].time.c_str(), "%d:%d", &alarmHour, &alarmMinute) < 2) continue;
         
         long currentSeconds = timeinfo.tm_hour * 3600 + timeinfo.tm_min * 60 + timeinfo.tm_sec;
         long alarmSeconds = alarmHour * 3600 + alarmMinute * 60;
         long secondsLeft = alarmSeconds - currentSeconds;
         
         if (secondsLeft >= -10 && secondsLeft <= 30) {
             Serial.println("  [" + String(i+1) + "] " + String(secondsLeft) + " seg para " + 
                           pending_alarms[i].name + " (Módulo " + String(pending_alarms[i].module) + ")");
         }
     }
 }
 
 void processIndividualAlarm(JsonVariant alarm, String currentTime) {
     int alarmId = alarm["id_alarma"] | 0;
     String alarmTime = alarm["hora_alarma"] | "";
     String alarmName = alarm["nombre_alarma"] | "Sin nombre";
     String diasSemana = alarm["dias_semana"] | "1111111";
     int espId = alarm["id_esp_alarma"] | 0;
     
     Serial.println("=== PROCESANDO ALARMA INDIVIDUAL ===");
     Serial.println("ID: " + String(alarmId) + " - Hora: " + alarmTime + " - Nombre: " + alarmName);
     
     // Verificar si es día activo
     if (!isDayActive(diasSemana)) {
         Serial.println("SKIP - Hoy no está activo según días_semana");
         return;
     }
     
     // MÉTODO 1: Intentar leer del JSON
     int targetModule = 0;
     bool moduloDesdeJSON = false;
     
     if (alarm.containsKey("modulo_detectado")) {
         targetModule = alarm["modulo_detectado"];
         if (targetModule >= 1 && targetModule <= MAX_MODULES) {
             moduloDesdeJSON = true;
             Serial.println("[OK] Modulo obtenido del JSON: " + String(targetModule));
         }
     }
     
     // MÉTODO 2: Si no hay en JSON, detectar del nombre
     if (!moduloDesdeJSON) {
         targetModule = detectModuleFromName(alarmName);
         Serial.println("[OK] Modulo detectado del nombre: " + String(targetModule));
     }
     
     Serial.println(">>> MODULO FINAL: " + String(targetModule) + " <<<");
     
     // NUEVA LÓGICA: Comparación exacta 1 a 1
     // Forzar actualización de tiempo antes de comparar
     forceTimeSync();
     
     // DESACTIVADO - Ahora solo se ejecuta via sistema de ventana ±10 segundos
     // Sistema anterior de comparacion exacta deshabilitado para evitar ejecuciones multiples
     
     // Verificar si faltan 30 segundos (pre-alerta) - sistema independiente
     if (isTimeForPreAlert(alarmTime)) {
         Serial.println("PRE-ALERTA - Alarma " + String(alarmId) + " en 30 segundos (" + alarmTime + ") - " + alarmName);
         Serial.println("Preparando módulo " + String(targetModule) + " para dispensación...");
     }
 }
 
 int getAlarmStatus(String alarmTime) {
     if (!time_synchronized) return -1;
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) return -1;
     
     int alarmHour, alarmMinute;
     if (sscanf(alarmTime.c_str(), "%d:%d", &alarmHour, &alarmMinute) < 2) return -1;
     
     // Calcular minutos actuales desde medianoche
     int currentMinutes = timeinfo.tm_hour * 60 + timeinfo.tm_min;
     int alarmMinutes = alarmHour * 60 + alarmMinute;
     
     // Calcular diferencia
     int diff = alarmMinutes - currentMinutes;
     
     // Si la diferencia es negativa, la alarma ya pasó hoy
     if (diff < 0) return -1;
     
     return diff; // 0 = ahora, 1 = en 1 minuto, etc.
 }
 
 void executeAlarmForModule(int alarmId, String alarmName, int moduleNum) {
     if (moduleNum < 1 || moduleNum > MAX_MODULES) {
         Serial.println("[ERROR] Módulo inválido: " + String(moduleNum));
         return;
     }
     
     if (isAlarmAlreadyExecuted(alarmId)) {
         Serial.println("Alarma " + String(alarmId) + " ya ejecutada - bloqueando duplicado");
         return;
     }
     
     int moduleIndex = moduleNum - 1;
     
     Serial.println("\n*** EJECUTANDO ALARMA ***");
     Serial.println("Medicamento: " + alarmName);
     Serial.println("Módulo: " + String(moduleNum) + " | Pin: " + String(servo_pins[moduleIndex]));
     Serial.println("Hora: " + getCurrentTime());
     
     Serial.println("\n=== SECUENCIA DE DISPENSACIÓN ===");
     
     // ACTIVAR TODO JUNTO: LED + BUZZER + SERVO (igual que el test)
     digitalWrite(LED_PIN, HIGH);
     ledcWriteTone(BUZZER_PIN, 1300);
     servos[moduleIndex].write(180);
     Serial.println("  → LED ON + Buzzer ON + Servo a 180°");
     
     delay(1500);
     
     // APAGAR TODO: LED + BUZZER + SERVO a 0 (igual que el test)
     digitalWrite(LED_PIN, LOW);
     ledcWrite(BUZZER_PIN, 0);
     servos[moduleIndex].write(0);
     Serial.println("  → LED OFF + Buzzer OFF + Servo a 0°");
     
     delay(1500);
     
     Serial.println("✓ Dispensación completada - Módulo " + String(moduleNum));
     
     // Reportar al servidor
     reportAlarmExecution(alarmId, true, moduleNum);
     markAlarmAsExecuted(alarmId);
     
     Serial.println("✓ Alarma reportada y marcada como ejecutada\n");
 }
 
 bool isTimeForAlarm(String alarmTime) {
     if (!time_synchronized) {
         Serial.println("WARN - Tiempo no sincronizado");
         return false;
     }
     
     struct tm timeinfo;
     if (!getLocalTime(&timeinfo)) {
         Serial.println("WARN - Error obteniendo tiempo local");
         return false;
     }
     
     int alarmHour, alarmMinute, alarmSecond = 0;
     
     if (sscanf(alarmTime.c_str(), "%d:%d:%d", &alarmHour, &alarmMinute, &alarmSecond) >= 2) {
         bool isHourMatch = (timeinfo.tm_hour == alarmHour);
         bool isMinuteMatch = (timeinfo.tm_min == alarmMinute);
         
         // NUEVA LÓGICA: Solo verificar hora y minuto, sin importar los segundos
         if (isHourMatch && isMinuteMatch) {
             Serial.println("TIEMPO COINCIDE - Hora: " + String(timeinfo.tm_hour) + ":" + 
                          String(timeinfo.tm_min) + " vs Alarma: " + alarmTime);
             return true;
         }
         
         Serial.println("DEBUG - Comparación tiempo: Actual(" + String(timeinfo.tm_hour) + 
                       ":" + String(timeinfo.tm_min) + ":" + String(timeinfo.tm_sec) + 
                       ") vs Alarma(" + alarmTime + ")");
     }
     
     return false;
 }