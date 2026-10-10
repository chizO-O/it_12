document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-lot-dialog]');
    if (opener && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        const dialog = document.getElementById(opener.dataset.lotDialog);
        if (dialog && typeof dialog.showModal === 'function') {
            event.preventDefault();
            dialog.showModal();
        }
    }

    const closer = event.target.closest('[data-close-dialog]');
    if (closer) closer.closest('dialog')?.close();

    if (event.target.matches('dialog[open]')) {
        const bounds = event.target.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
            event.target.close();
        }
    }
});

document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
});

document.querySelectorAll('[data-lot-layout]').forEach((layout) => {
    const viewport = layout.querySelector('[data-layout-viewport]');
    const canvas = layout.querySelector('[data-layout-canvas]');
    const controls = layout.querySelector('[data-layout-controls]');
    if (!viewport || !canvas || !controls) return;

    let zoom = 1;
    controls.classList.replace('hidden', 'flex');

    const render = () => {
        canvas.style.width = `${Math.max(680, viewport.clientWidth) * zoom}px`;
        controls.querySelector('[data-layout-zoom-label]').textContent = `${Math.round(zoom * 100)}%`;
        controls.querySelector('[data-layout-zoom="out"]').disabled = zoom <= 0.75;
        controls.querySelector('[data-layout-zoom="in"]').disabled = zoom >= 2;
    };

    controls.addEventListener('click', (event) => {
        const button = event.target.closest('[data-layout-zoom]');
        if (!button) return;
        if (button.dataset.layoutZoom === 'reset') zoom = 1;
        else zoom = Math.max(0.75, Math.min(2, zoom + (button.dataset.layoutZoom === 'in' ? 0.25 : -0.25)));
        render();
    });

    window.addEventListener('resize', render);
});
