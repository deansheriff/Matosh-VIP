document.addEventListener('DOMContentLoaded', function () {
    const addCustomerForm = document.querySelector('#add-customer-form');
    if (!addCustomerForm) {
        return;
    }

    addCustomerForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const name = document.querySelector('#customer-name').value;
        const phone = document.querySelector('#customer-phone').value;
        const email = document.querySelector('#customer-email').value;

        fetch('/api/add_customer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name, phone, email })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const customerSelect = document.querySelector('#customer_id');
                const newOption = new Option(data.customer.name, data.customer.id, true, true);
                customerSelect.appendChild(newOption);

                const modal = bootstrap.Modal.getInstance(document.querySelector('#addCustomerModal'));
                modal.hide();

                showToast('Customer added successfully!', 'success');
            } else {
                showToast('Error: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An unexpected error occurred.', 'error');
        });
    });
});
