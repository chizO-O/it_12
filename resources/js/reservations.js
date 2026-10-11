document.querySelectorAll('[data-assessment-form]').forEach((form) => {
    const preference = form.querySelector('[data-payment-preference]');
    const installment = form.querySelector('[data-installment-fields]');
    if (!preference || !installment) return;

    const update = () => {
        const show = preference.value === 'installment';
        installment.hidden = !show;
        installment.disabled = !show;
    };
    preference.addEventListener('change', update);
    update();
});
