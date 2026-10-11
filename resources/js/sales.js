document.querySelectorAll('[data-sales-terms-form]').forEach((form) => {
    const type = form.querySelector('[data-sales-payment-type]');
    const installment = form.querySelector('[data-sales-installment-fields]');
    if (!type || !installment) return;
    const update = () => {
        const enabled = type.value === 'installment';
        installment.hidden = !enabled;
        installment.disabled = !enabled;
    };
    type.addEventListener('change', update);
    update();
});
