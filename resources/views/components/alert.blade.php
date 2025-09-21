<!-- Dynamic Alert Component with Alpine.js -->
<div
    x-data="alertComponent()"
    x-show="show"
    x-transition.opacity.duration.300ms
    x-init="$store.alert.init()"
    class="position-fixed"
    style="top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;"
    x-cloak
>
    <div
        class="alert alert-dismissible fade show shadow-lg"
        :class="alertClass"
        role="alert"
    >
        <div class="d-flex align-items-center">
            <i :class="iconClass" class="me-2"></i>
            <div class="flex-grow-1" x-text="message"></div>
        </div>
        <button
            type="button"
            class="btn-close"
            @click="close()"
            aria-label="Close"
        ></button>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('alert', {
        visible: false,
        message: '',
        type: 'info',
        timeout: null,

        init() {
            // Initialize the global alert store
        },

        show(message, type = 'info', duration = 5000) {
            this.message = message;
            this.type = type;
            this.visible = true;

            // Clear any existing timeout
            if (this.timeout) {
                clearTimeout(this.timeout);
            }

            // Auto-hide after duration
            if (duration > 0) {
                this.timeout = setTimeout(() => {
                    this.hide();
                }, duration);
            }
        },

        hide() {
            this.visible = false;
            if (this.timeout) {
                clearTimeout(this.timeout);
                this.timeout = null;
            }
        }
    });
});

function alertComponent() {
    return {
        get show() {
            return this.$store.alert.visible;
        },

        get message() {
            return this.$store.alert.message;
        },

        get type() {
            return this.$store.alert.type;
        },

        get alertClass() {
            const classes = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'danger': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info',
                'primary': 'alert-primary'
            };
            return classes[this.type] || 'alert-info';
        },

        get iconClass() {
            const icons = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-exclamation-triangle',
                'danger': 'fas fa-exclamation-triangle',
                'warning': 'fas fa-exclamation-triangle',
                'info': 'fas fa-info-circle',
                'primary': 'fas fa-info-circle'
            };
            return icons[this.type] || 'fas fa-info-circle';
        },

        close() {
            this.$store.alert.hide();
        }
    };
}

// Global function for backward compatibility and easy usage
window.showAlert = function(message, type = 'info', duration = 5000) {
    Alpine.store('alert').show(message, type, duration);
};

// Alias for common usage patterns
window.alertSuccess = function(message, duration = 5000) {
    window.showAlert(message, 'success', duration);
};

window.alertError = function(message, duration = 5000) {
    window.showAlert(message, 'error', duration);
};

window.alertWarning = function(message, duration = 5000) {
    window.showAlert(message, 'warning', duration);
};

window.alertInfo = function(message, duration = 5000) {
    window.showAlert(message, 'info', duration);
};
</script>
