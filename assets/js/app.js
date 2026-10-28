let cart = [];
let selectedPaymentMethod = 'TKI';
let selectedCustomerType = 'Santri';
let currentActiveProduct = null;

// ==========================================
// 1. KONTROL KATEGORI PELANGGAN & TRANSAKSI
// ==========================================

function selectCustomerType(type) {
    selectedCustomerType = type;
    document.querySelectorAll('.btn-customer').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-type') === type) {
            btn.classList.add('active');
        }
    });

    const input = document.getElementById('namaPelanggan');
    const label = document.getElementById('namaPelangganLabel');

    if (type === 'Santri') {
        label.innerText = 'Nama Santri:';
        input.placeholder = 'Ketik nama santri...';
        input.value = '';
        input.focus();
    } else if (type === 'Asatidz') {
        label.innerText = 'Nama Asatidz (Bisa Dikosongkan):';
        input.placeholder = 'cth: Ustadz Ahmad (atau biarkan kosong)';
        input.value = '';
    } else {
        label.innerText = 'Keterangan Tamu (Bisa Dikosongkan):';
        input.placeholder = 'cth: Wali dari Zaid (atau biarkan kosong)';
        input.value = '';
    }
}

// Ganti Metode Pembayaran & Tampilkan Form Tunai Jika Dipilih
function selectPaymentMethod(method) {
    selectedPaymentMethod = method;
    document.querySelectorAll('.btn-payment').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-method') === method) {
            btn.classList.add('active');
        }
    });

    const cashSection = document.getElementById('cashSection');
    if (method === 'TUNAI') {
        cashSection.style.display = 'block';
        calculateChange();
        document.getElementById('uangDiterima').focus();
    } else {
        cashSection.style.display = 'none';
        // Untuk TKI & QRIS: nominal selalu pas, tombol aktif selama keranjang tidak kosong
        const btnSubmit = document.getElementById('btnSubmitOrder');
        btnSubmit.disabled = cart.length === 0;
        btnSubmit.innerText = 'Simpan Transaksi';
    }
}

// ==========================================
// 2. KALKULATOR UANG TUNAI & KEMBALIAN
// ==========================================

function setExactCash() {
    const grandTotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
    document.getElementById('uangDiterima').value = grandTotal;
    calculateChange();
}

function setQuickCash(amount) {
    document.getElementById('uangDiterima').value = amount;
    calculateChange();
}

function calculateChange() {
    if (selectedPaymentMethod !== 'TUNAI') return;

    const grandTotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
    const uangInput = document.getElementById('uangDiterima').value;
    const uangDiterima = parseInt(uangInput, 10) || 0;

    const changeBox = document.getElementById('changeBox');
    const label = document.getElementById('changeBoxLabel');
    const amountText = document.getElementById('changeAmountText');
    const btnSubmit = document.getElementById('btnSubmitOrder');

    if (cart.length === 0) {
        changeBox.className = 'change-box neutral';
        label.innerText = 'Uang Kembalian:';
        amountText.innerText = 'Rp 0';
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Simpan Transaksi';
        return;
    }

    if (!uangInput || uangDiterima === 0) {
        changeBox.className = 'change-box neutral';
        label.innerText = 'Masukkan Uang Diterima:';
        amountText.innerText = 'Rp 0';
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Uang Belum Diinput';
        return;
    }

    if (uangDiterima < grandTotal) {
        // Uang Kurang: Tombol Disabled
        const selisihKurang = grandTotal - uangDiterima;
        changeBox.className = 'change-box danger';
        label.innerText = '⚠️ Uang Kurang:';
        amountText.innerText = `- ${formatRupiah(selisihKurang)}`;
        btnSubmit.disabled = true;
        btnSubmit.innerText = `Uang Kurang (${formatRupiah(selisihKurang)})`;
    } else {
        // Uang Cukup / Lebih: Tombol Enabled
        const kembalian = uangDiterima - grandTotal;
        changeBox.className = 'change-box success';
        label.innerText = '✓ Uang Kembalian:';
        amountText.innerText = formatRupiah(kembalian);
        btnSubmit.disabled = false;
        btnSubmit.innerText = kembalian === 0 ? 'Simpan Transaksi (Uang Pas)' : `Simpan Transaksi (Kembali ${formatRupiah(kembalian)})`;
    }
}

// ==========================================
// 3. FILTER, MODAL TOPPING & KERANJANG
// ==========================================

function filterByBooth(boothId, element) {
    document.querySelectorAll('.filter-chip').forEach(btn => btn.classList.remove('active'));
    element.classList.add('active');

    const cards = document.querySelectorAll('.product-card');
    cards.forEach(card => {
        const cardBoothId = parseInt(card.getAttribute('data-booth-id'), 10);
        if (boothId === 0 || cardBoothId === boothId) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function handleProductClick(productId) {
    const product = window.PRODUCTS.find(p => p.id === productId);
    if (!product) return;

    if (product.toppings && product.toppings.length > 0) {
        openToppingModal(product);
    } else {
        addToCart(product, null);
    }
}

function openToppingModal(product) {
    currentActiveProduct = product;
    document.getElementById('modalBoothName').innerText = product.nama_booth;
    document.getElementById('modalProductName').innerText = product.nama_produk;

    const container = document.getElementById('toppingOptionsContainer');
    container.innerHTML = `
        <label>
            <div>
                <input type="radio" name="toppingOption" value="0" checked>
                <span style="margin-left: 8px;">Tanpa Topping Tambahan</span>
            </div>
            <strong>+Rp 0</strong>
        </label>
    `;

    product.toppings.forEach(top => {
        container.innerHTML += `
            <label>
                <div>
                    <input type="radio" name="toppingOption" value="${top.id}">
                    <span style="margin-left: 8px;">+ ${top.nama_topping}</span>
                </div>
                <strong style="color: #2e7d32;">+${formatRupiah(top.harga_ekstra)}</strong>
            </label>
        `;
    });

    document.getElementById('toppingModal').classList.add('open');
}

function closeToppingModal() {
    document.getElementById('toppingModal').classList.remove('open');
    currentActiveProduct = null;
}

function confirmAddToCartWithTopping() {
    if (!currentActiveProduct) return;

    const selectedOption = document.querySelector('input[name="toppingOption"]:checked');
    const toppingId = selectedOption ? parseInt(selectedOption.value, 10) : 0;

    let selectedTopping = null;
    if (toppingId > 0) {
        selectedTopping = currentActiveProduct.toppings.find(t => t.id === toppingId);
    }

    addToCart(currentActiveProduct, selectedTopping);
    closeToppingModal();
}

function addToCart(product, topping) {
    const toppingId = topping ? topping.id : 0;
    const itemKey = `${product.id}_${toppingId}`;

    const existingIndex = cart.findIndex(item => item.itemKey === itemKey);
    const extraPrice = topping ? topping.harga_ekstra : 0;
    const finalPrice = product.harga_dasar + extraPrice;

    if (existingIndex > -1) {
        cart[existingIndex].qty += 1;
        cart[existingIndex].subtotal = cart[existingIndex].qty * cart[existingIndex].harga_satuan;
    } else {
        cart.push({
            itemKey: itemKey,
            productId: product.id,
            boothId: product.booth_id,
            boothName: product.nama_booth,
            productName: product.nama_produk,
            toppingName: topping ? topping.nama_topping : null,
            harga_satuan: finalPrice,
            qty: 1,
            subtotal: finalPrice
        });
    }

    renderCart();
}

function updateQty(itemKey, delta) {
    const item = cart.find(i => i.itemKey === itemKey);
    if (!item) return;

    item.qty += delta;
    if (item.qty <= 0) {
        removeFromCart(itemKey);
        return;
    }

    item.subtotal = item.qty * item.harga_satuan;
    renderCart();
}

function removeFromCart(itemKey) {
    cart = cart.filter(item => item.itemKey !== itemKey);
    renderCart();
}

function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
}

function renderCart() {
    const tbody = document.getElementById('cartTableBody');
    tbody.innerHTML = '';

    if (cart.length === 0) {
        tbody.innerHTML = '<tr id="emptyCartRow"><td colspan="4" class="text-center">Belum ada item dipilih</td></tr>';
        document.getElementById('grandTotalText').innerText = 'Rp 0';
        calculateChange();
        return;
    }

    let grandTotal = 0;

    cart.forEach(item => {
        grandTotal += item.subtotal;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <span class="item-booth-label">[${item.boothName}]</span>
                <strong>${item.productName}</strong>
                ${item.toppingName ? `<span class="item-topping-label">+ Topping: ${item.toppingName}</span>` : ''}
                <small style="color: #637381;">@ ${formatRupiah(item.harga_satuan)}</small>
            </td>
            <td>
                <button type="button" class="btn-qty" onclick="updateQty('${item.itemKey}', -1)">-</button>
                <span style="margin: 0 4px; font-weight: 600;">${item.qty}</span>
                <button type="button" class="btn-qty" onclick="updateQty('${item.itemKey}', 1)">+</button>
            </td>
            <td><strong>${formatRupiah(item.subtotal)}</strong></td>
            <td>
                <button type="button" class="btn-del" onclick="removeFromCart('${item.itemKey}')">✕</button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('grandTotalText').innerText = formatRupiah(grandTotal);

    // Otomatis sinkronkan kalkulasi kembalian atau status tombol
    if (selectedPaymentMethod === 'TUNAI') {
        calculateChange();
    } else {
        document.getElementById('btnSubmitOrder').disabled = false;
    }
}

// ==========================================
// 4. SUBMIT TRANSAKSI KE SERVER
// ==========================================

async function submitTransaction() {
    if (cart.length === 0) {
        alert('Keranjang masih kosong!');
        return;
    }

    const grandTotal = cart.reduce((sum, item) => sum + item.subtotal, 0);

    // Validasi ketat jika metode Tunai
    if (selectedPaymentMethod === 'TUNAI') {
        const uangInput = document.getElementById('uangDiterima').value;
        const uangDiterima = parseInt(uangInput, 10) || 0;

        if (uangDiterima < grandTotal) {
            alert(`Uang tunai kurang sebesar ${formatRupiah(grandTotal - uangDiterima)}!`);
            document.getElementById('uangDiterima').focus();
            return;
        }
    }

    const rawName = document.getElementById('namaPelanggan').value.trim();
    let namaPelanggan = '';

    if (selectedCustomerType === 'Santri') {
        if (!rawName) {
            alert('Silakan tulis nama santri terlebih dahulu!');
            document.getElementById('namaPelanggan').focus();
            return;
        }
        namaPelanggan = `${rawName} (Santri)`;
    } else if (selectedCustomerType === 'Asatidz') {
        namaPelanggan = rawName ? `Asatidz: ${rawName}` : 'Asatidz';
    } else {
        namaPelanggan = rawName ? `Tamu: ${rawName}` : 'Wali Santri / Tamu';
    }

    const payload = {
        nama_pelanggan: namaPelanggan,
        metode_bayar: selectedPaymentMethod,
        total_bayar: grandTotal,
        items: cart
    };

    const btnSubmit = document.getElementById('btnSubmitOrder');
    btnSubmit.disabled = true;
    btnSubmit.innerText = 'Menyimpan...';

    try {
        const response = await fetch('process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            let infoSukses = `Transaksi Berhasil Dicatat!\nInvoice: ${result.invoice}\nPelanggan: ${namaPelanggan}\nMetode: ${selectedPaymentMethod}\nTotal: ${formatRupiah(grandTotal)}`;
            
            if (selectedPaymentMethod === 'TUNAI') {
                const uangDiterima = parseInt(document.getElementById('uangDiterima').value, 10) || grandTotal;
                const kembalian = uangDiterima - grandTotal;
                infoSukses += `\nUang Diterima: ${formatRupiah(uangDiterima)}\nKembalian: ${formatRupiah(kembalian)}`;
            }

            alert(infoSukses);
            
            // Reset keranjang dan input tunai
            cart = [];
            document.getElementById('uangDiterima').value = '';
            selectCustomerType('Santri');
            selectPaymentMethod('TKI');
            renderCart();
        } else {
            alert('Gagal: ' + result.message);
        }
    } catch (err) {
        alert('Terjadi kesalahan koneksi ke server!');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.innerText = 'Simpan Transaksi';
    }
}

// ==========================================
// 5. KONTROL CRUD BOOTH & PRODUK (MODAL)
// ==========================================

function openBoothModal() {
    document.getElementById('boothModal').classList.add('open');
}

function closeBoothModal() {
    document.getElementById('boothModal').classList.remove('open');
}

function openAddProductModal() {
    document.getElementById('productForm').reset();
    document.getElementById('prodFormId').value = 0;
    document.getElementById('productModalTitle').innerText = 'Tambah Menu Baru';
    document.getElementById('toppingRowsContainer').innerHTML = '';
    document.getElementById('btnDeleteProduct').style.display = 'none';

    document.getElementById('productModal').classList.add('open');
}

function openEditProductModal(productId, event) {
    if (event) event.stopPropagation();

    const product = window.PRODUCTS.find(p => p.id === productId);
    if (!product) return;

    document.getElementById('prodFormId').value = product.id;
    document.getElementById('prodFormBooth').value = product.booth_id;
    document.getElementById('prodFormName').value = product.nama_produk;
    document.getElementById('prodFormPrice').value = product.harga_dasar;
    document.getElementById('productModalTitle').innerText = 'Edit Menu Produk';

    const btnDel = document.getElementById('btnDeleteProduct');
    btnDel.href = `crud_action.php?action=delete_product&id=${product.id}`;
    btnDel.style.display = 'inline-block';

    const container = document.getElementById('toppingRowsContainer');
    container.innerHTML = '';
    if (product.toppings && product.toppings.length > 0) {
        product.toppings.forEach(top => {
            addToppingRow(top.nama_topping, top.harga_ekstra);
        });
    }

    document.getElementById('productModal').classList.add('open');
}

function closeProductModal() {
    document.getElementById('productModal').classList.remove('open');
}

function addToppingRow(name = '', price = '') {
    const container = document.getElementById('toppingRowsContainer');
    const div = document.createElement('div');
    div.className = 'topping-input-row';
    div.innerHTML = `
        <input type="text" name="topping_names[]" value="${name}" placeholder="Nama topping (cth: Keju)" style="flex: 2; padding: 6px 10px; border: 1px solid #c4cdd5; border-radius: 4px; font-size: 0.85rem;" required>
        <input type="number" name="topping_prices[]" value="${price}" placeholder="+Rp (cth: 2000)" min="0" step="500" style="flex: 1; padding: 6px 10px; border: 1px solid #c4cdd5; border-radius: 4px; font-size: 0.85rem;" required>
        <button type="button" class="btn-del" onclick="this.parentElement.remove()">✕</button>
    `;
    container.appendChild(div);
}