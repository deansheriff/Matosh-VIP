document.addEventListener('DOMContentLoaded', function () {
    const orderForm = document.querySelector('#create-order-form, #modify-order-form');
    if (!orderForm) {
        return; // Not on a relevant order page
    }

    const currencySymbol = orderForm.dataset.currencySymbol || '$';

    const menuSearchInput = document.querySelector('#menu-search');
    const menuItemsContainer = document.querySelector('#menu-items-container');
    const orderItemsTbody = document.querySelector('#order-items-tbody');
    const orderTotalEl = document.querySelector('#order-total');
    const orderSubtotalEl = document.querySelector('#order-subtotal');
    const orderTaxEl = document.querySelector('#order-tax');
    const hiddenFormContainer = document.querySelector('#hidden-form-inputs');
    
    const taxRate = parseFloat(orderForm.dataset.taxRate) || 0;
    const taxType = orderForm.dataset.taxType || 'percentage';

    let allMenuItems = [];
    if (menuItemsContainer) {
        allMenuItems = Array.from(menuItemsContainer.children);
    }

    let currentOrder = {}; // { "itemId": { name, price, quantity } }

    // 0. Initial Load for Modify Page
    const initialOrderDataEl = document.querySelector('#initial-order-data');
    if (initialOrderDataEl) {
        currentOrder = JSON.parse(initialOrderDataEl.textContent);
        renderOrder();
    }


    // 1. Search/Filter Logic
    if (menuSearchInput) {
        menuSearchInput.addEventListener('keyup', () => {
            const searchTerm = menuSearchInput.value.toLowerCase();
            allMenuItems.forEach(itemCard => {
                const itemName = itemCard.dataset.name.toLowerCase();
                if (itemName.includes(searchTerm)) {
                    itemCard.style.display = 'block';
                } else {
                    itemCard.style.display = 'none';
                }
            });
        });
    }

    // 2. Add Item to Order Logic
    if (menuItemsContainer) {
        menuItemsContainer.addEventListener('click', (e) => {
            const itemCard = e.target.closest('.menu-item-card');
            if (!itemCard) return;

            const itemId = itemCard.dataset.id;
            const itemName = itemCard.dataset.name;
            const itemPrice = parseFloat(itemCard.dataset.price);

            if (currentOrder[itemId]) {
                currentOrder[itemId].quantity++;
            } else {
                currentOrder[itemId] = {
                    name: itemName,
                    price: itemPrice,
                    quantity: 1,
                };
            }
            renderOrder(true); // Show toast on item add
        });
    }

    // 3. Order Item Actions (Quantity Change, Remove)
    orderItemsTbody.addEventListener('click', (e) => {
        const target = e.target;
        const tr = target.closest('tr');
        if (!tr) return;
        const itemId = tr.dataset.id;

        if (target.classList.contains('quantity-decrease')) {
            currentOrder[itemId].quantity--;
            if (currentOrder[itemId].quantity <= 0) {
                delete currentOrder[itemId];
            }
        }
        if (target.classList.contains('quantity-increase')) {
            currentOrder[itemId].quantity++;
        }
        if (target.classList.contains('remove-item')) {
            delete currentOrder[itemId];
        }
        renderOrder();
    });

    // 4. Render/Update Function
    function renderOrder(showToastNotification = false) {
        orderItemsTbody.innerHTML = '';
        let subtotal = 0;

        for (const id in currentOrder) {
            const item = currentOrder[id];
            const itemSubtotal = item.price * item.quantity;
            subtotal += itemSubtotal;

            const tr = document.createElement('tr');
            tr.dataset.id = id;
            tr.innerHTML = `
                <td>${item.name}</td>
                <td class="d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-secondary quantity-decrease">-</button>
                    <span class="mx-2">${item.quantity}</span>
                    <button type="button" class="btn btn-sm btn-secondary quantity-increase">+</button>
                </td>
                <td>${currencySymbol}${itemSubtotal.toFixed(2)}</td>
                <td><button type="button" class="btn btn-sm btn-danger remove-item">&times;</button></td>
            `;
            orderItemsTbody.appendChild(tr);
        }

        let taxAmount = 0;
        if (taxType === 'percentage') {
            taxAmount = (subtotal * taxRate) / 100;
        } else {
            taxAmount = taxRate;
        }
        
        const total = subtotal + taxAmount;

        if (orderSubtotalEl) {
            orderSubtotalEl.textContent = `${currencySymbol}${subtotal.toFixed(2)}`;
        }
        if (orderTaxEl) {
            orderTaxEl.textContent = `${currencySymbol}${taxAmount.toFixed(2)}`;
        }
        orderTotalEl.textContent = `${currencySymbol}${total.toFixed(2)}`;
        updateHiddenForm();

        if (showToastNotification) {
            showToast('Order updated!');
        }
    }

    // 5. Update Hidden Form Inputs before submit
    function updateHiddenForm() {
        hiddenFormContainer.innerHTML = '';
        for (const id in currentOrder) {
            const item = currentOrder[id];
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `menu_items[${id}]`;
            input.value = item.quantity;
            hiddenFormContainer.appendChild(input);
        }
    }
});
