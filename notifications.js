// Sistema de Notificaciones para Autopill
// Reemplaza los alert() del navegador con notificaciones elegantes en la página

class NotificationSystem {
    constructor() {
        this.container = null;
        this.notifications = [];
        this.init();
    }

    init() {
        // Crear el contenedor de notificaciones si no existe
        if (!document.querySelector('.notification-container')) {
            this.container = document.createElement('div');
            this.container.className = 'notification-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.notification-container');
        }
    }

    show(message, type = 'info', title = '', duration = 5000) {
        const notification = this.createNotification(message, type, title);
        this.container.appendChild(notification);
        this.notifications.push(notification);

        // Mostrar la notificación con animación
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        // Auto-cerrar después del tiempo especificado
        if (duration > 0) {
            setTimeout(() => {
                this.hide(notification);
            }, duration);
        }

        return notification;
    }

    createNotification(message, type, title) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        // Determinar el título por defecto basado en el tipo
        if (!title) {
            switch(type) {
                case 'success': title = 'Éxito'; break;
                case 'error': title = 'Error'; break;
                case 'warning': title = 'Advertencia'; break;
                case 'info': title = 'Información'; break;
                default: title = 'Notificación'; break;
            }
        }

        notification.innerHTML = `
            <div class="notification-icon"></div>
            <div class="notification-content">
                <div class="notification-title">${title}</div>
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="notificationSystem.hide(this.parentNode)">×</button>
        `;

        return notification;
    }

    hide(notification) {
        if (notification && notification.classList.contains('show')) {
            notification.classList.remove('show');
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
                
                // Remover de la lista de notificaciones
                const index = this.notifications.indexOf(notification);
                if (index > -1) {
                    this.notifications.splice(index, 1);
                }
            }, 300);
        }
    }

    // Métodos de conveniencia
    success(message, title = '', duration = 5000) {
        return this.show(message, 'success', title, duration);
    }

    error(message, title = '', duration = 8000) {
        return this.show(message, 'error', title, duration);
    }

    warning(message, title = '', duration = 6000) {
        return this.show(message, 'warning', title, duration);
    }

    info(message, title = '', duration = 5000) {
        return this.show(message, 'info', title, duration);
    }

    // Limpiar todas las notificaciones
    clear() {
        this.notifications.forEach(notification => {
            this.hide(notification);
        });
    }
}

// Crear instancia global
window.notificationSystem = new NotificationSystem();

// Sobrescribir alert() para usar el nuevo sistema
window.originalAlert = window.alert;
window.alert = function(message) {
    // Determinar el tipo basado en el contenido del mensaje
    let type = 'info';
    let title = '';
    
    if (typeof message === 'string') {
        const lowerMessage = message.toLowerCase();
        if (lowerMessage.includes('error') || lowerMessage.includes('problema') || lowerMessage.includes('falló')) {
            type = 'error';
            title = 'Error';
        } else if (lowerMessage.includes('éxito') || lowerMessage.includes('exitoso') || lowerMessage.includes('guardado') || lowerMessage.includes('enviado')) {
            type = 'success';
            title = 'Éxito';
        } else if (lowerMessage.includes('completa') || lowerMessage.includes('requiere') || lowerMessage.includes('debe')) {
            type = 'warning';
            title = 'Atención';
        }
    }
    
    notificationSystem.show(message, type, title);
};

// Métodos globales para uso directo
window.showNotification = (message, type, title, duration) => {
    return notificationSystem.show(message, type, title, duration);
};

window.showSuccess = (message, title, duration) => {
    return notificationSystem.success(message, title, duration);
};

window.showError = (message, title, duration) => {
    return notificationSystem.error(message, title, duration);
};

window.showWarning = (message, title, duration) => {
    return notificationSystem.warning(message, title, duration);
};

window.showInfo = (message, title, duration) => {
    return notificationSystem.info(message, title, duration);
};

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        notificationSystem.init();
    });
} else {
    notificationSystem.init();
}