/*
 * Matosh POS - Custom JavaScript
 * Author: Gemini AI
 */

document.addEventListener('DOMContentLoaded', function () {
    console.log('Matosh POS system initialized.');

    // Example: Add smooth scroll for anchor links if needed
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // More interactive features will be added here, such as:
    // - AJAX calls for updating order statuses.
    // - Drag-and-drop functionality for the Kanban board.
    // - Real-time form validation.
    // - Handling modal dialogs for payments and order modifications.
});

function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('show');
    }, 100);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
            if (container.childElementCount === 0) {
                container.remove();
            }
        }, 300);
    }, 3000);
}
