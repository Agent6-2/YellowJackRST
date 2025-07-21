<?php
/**
 * Pied de page du panel employé - Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */
?>

<footer class="footer mt-auto py-3 bg-light border-top">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6">
                <span class="text-muted">
                    <i class="fas fa-glass-whiskey me-2"></i>
                    © <?php echo date('Y'); ?> Le Yellowjack - Panel Employé
                </span>
            </div>
            <div class="col-md-6 text-end">
                <small class="text-muted">
                    <i class="fas fa-clock me-1"></i>
                    Dernière mise à jour: <?php echo date('d/m/Y H:i'); ?>
                </small>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Font Awesome -->
<script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>

<!-- Scripts personnalisés -->
<script>
// Fonction pour afficher les notifications toast
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Supprimer le toast après qu'il soit caché
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '1055';
    document.body.appendChild(container);
    return container;
}

// Afficher les messages de succès/erreur PHP
<?php if (isset($success_message)): ?>
showToast('<?php echo addslashes($success_message); ?>', 'success');
<?php endif; ?>

<?php if (isset($error_message)): ?>
showToast('<?php echo addslashes($error_message); ?>', 'error');
<?php endif; ?>
</script>

</body>
</html>