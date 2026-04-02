// ==========================================
// POS System - Vanilla JavaScript
// ==========================================

// State Management
let state = {
    isSetup: false,
    currentUser: null,
    businessName: 'POS System',
    products: [],
    users: [],
    customers: [],
    sales: [],
    cart: [],
    cashOpened: null,
    cashClosures: [],
    currentCategory: 'all',
    searchTerm: ''
};

// Initialize App
document.addEventListener('DOMContentLoaded', () => {
    loadState();
    initApp();
    updateDate();
    setInterval(updateDate, 60000);
});

// Load state from localStorage
function loadState() {
    const saved = localStorage.getItem('posState');
    if (saved) {
        const parsed = JSON.parse(saved);
        state = { ...state, ...parsed };
    }
}

// Save state to localStorage
function saveState() {
    localStorage.setItem('posState', JSON.stringify(state));
}

// Initialize application
function initApp() {
    if (!state.isSetup) {
        showSetupScreen();
    } else if (!state.currentUser) {
        showLoginScreen();
    } else {
        showMainApp();
    }

    // Event Listeners
    document.getElementById('setupForm').addEventListener('submit', handleSetup);
    document.getElementById('loginForm').addEventListener('submit', handleLogin);
    document.getElementById('productForm').addEventListener('submit', handleProductSave);
    document.getElementById('vendorForm').addEventListener('submit', handleVendorSave);
    document.getElementById('customerForm').addEventListener('submit', handleCustomerSave);
    document.getElementById('searchProduct').addEventListener('input', handleSearch);
}

// Update current date display
function updateDate() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('currentDate').textContent = now.toLocaleDateString('es-ES', options);
}

// ==========================================
// Authentication
// ==========================================

function showSetupScreen() {
    document.getElementById('setupScreen').classList.remove('hidden');
    document.getElementById('loginScreen').classList.add('hidden');
    document.getElementById('mainApp').classList.add('hidden');
}

function showLoginScreen() {
    document.getElementById('setupScreen').classList.add('hidden');
    document.getElementById('loginScreen').classList.remove('hidden');
    document.getElementById('mainApp').classList.add('hidden');
    document.getElementById('loginTitle').textContent = state.businessName;
}

function showMainApp() {
    document.getElementById('setupScreen').classList.add('hidden');
    document.getElementById('loginScreen').classList.add('hidden');
    document.getElementById('mainApp').classList.remove('hidden');
    
    document.getElementById('appTitle').textContent = state.businessName;
    document.getElementById('currentUserDisplay').textContent = state.currentUser.name + ' (' + state.currentUser.role + ')';
    document.getElementById('userAvatar').textContent = state.currentUser.name.charAt(0).toUpperCase();

    // Show/hide menus based on role
    if (state.currentUser.role === 'admin') {
        document.getElementById('adminMenu').classList.remove('hidden');
        document.getElementById('vendorMenu').classList.add('hidden');
    } else {
        document.getElementById('adminMenu').classList.add('hidden');
        document.getElementById('vendorMenu').classList.remove('hidden');
    }

    // Set cash open time if not set
    if (!state.cashOpened) {
        state.cashOpened = new Date().toISOString();
        saveState();
    }

    showSection('pos');
    renderProducts();
    renderCustomerSelect();
}

function handleSetup(e) {
    e.preventDefault();
    
    const businessName = document.getElementById('businessName').value;
    const adminName = document.getElementById('adminName').value;
    const adminUser = document.getElementById('adminUser').value;
    const adminPass = document.getElementById('adminPass').value;

    state.businessName = businessName;
    state.isSetup = true;
    state.users.push({
        id: generateId(),
        name: adminName,
        username: adminUser,
        password: adminPass,
        role: 'admin',
        salesCount: 0,
        active: true,
        createdAt: new Date().toISOString()
    });

    // Add sample products
    addSampleProducts();
    
    saveState();
    showLoginScreen();
}

function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('loginUser').value;
    const password = document.getElementById('loginPass').value;

    const user = state.users.find(u => u.username === username && u.password === password && u.active);
    
    if (user) {
        state.currentUser = user;
        document.getElementById('loginError').classList.add('hidden');
        document.getElementById('loginUser').value = '';
        document.getElementById('loginPass').value = '';
        saveState();
        showMainApp();
    } else {
        document.getElementById('loginError').classList.remove('hidden');
    }
}

function logout() {
    state.currentUser = null;
    saveState();
    showLoginScreen();
}

// ==========================================
// Navigation
// ==========================================

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

function showSection(section) {
    // Hide all sections
    const sections = ['pos', 'dashboard', 'products', 'vendors', 'customers', 'reports', 'cashClose', 'stockView'];
    sections.forEach(s => {
        document.getElementById(s + 'Section').classList.add('hidden');
    });

    // Show selected section
    document.getElementById(section + 'Section').classList.remove('hidden');

    // Update nav buttons
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('bg-sidebar-active');
        if (btn.dataset.section === section) {
            btn.classList.add('bg-sidebar-active');
        }
    });

    // Update title
    const titles = {
        pos: 'Punto de Venta',
        dashboard: 'Dashboard',
        products: 'Productos',
        vendors: 'Vendedores',
        customers: 'Clientes',
        reports: 'Reportes',
        cashClose: 'Cierre de Caja',
        stockView: 'Inventario'
    };
    document.getElementById('sectionTitle').textContent = titles[section];

    // Close sidebar on mobile
    if (window.innerWidth < 1024) {
        toggleSidebar();
    }

    // Update section specific content
    if (section === 'dashboard') updateDashboard();
    if (section === 'products') renderProductsTable();
    if (section === 'vendors') renderVendorsTable();
    if (section === 'customers') renderCustomersTable();
    if (section === 'cashClose') updateCashClose();
    if (section === 'stockView') renderStockViewTable('all');
}

// ==========================================
// Products
// ==========================================

function addSampleProducts() {
    const samples = [
        { name: 'Cafe Americano', category: 'bebidas', price: 2.50, stock: 100 },
        { name: 'Cafe Latte', category: 'bebidas', price: 3.50, stock: 100 },
        { name: 'Refresco', category: 'bebidas', price: 1.50, stock: 50 },
        { name: 'Agua', category: 'bebidas', price: 1.00, stock: 100 },
        { name: 'Empanada', category: 'comidas', price: 2.00, stock: 30 },
        { name: 'Sandwich', category: 'comidas', price: 4.50, stock: 20 },
        { name: 'Arepa', category: 'comidas', price: 3.00, stock: 25 },
        { name: 'Hamburguesa', category: 'comidas', price: 6.00, stock: 15 },
        { name: 'Chocolate', category: 'dulces', price: 1.50, stock: 40 },
        { name: 'Galletas', category: 'dulces', price: 1.00, stock: 50 },
        { name: 'Caramelos', category: 'dulces', price: 0.50, stock: 100 },
        { name: 'Chicle', category: 'otros', price: 0.25, stock: 200 },
        { name: 'Cigarrillos', category: 'otros', price: 5.00, stock: 30 }
    ];

    samples.forEach(p => {
        state.products.push({
            id: generateId(),
            ...p,
            createdAt: new Date().toISOString()
        });
    });
}

function renderProducts() {
    const container = document.getElementById('productsGrid');
    let filtered = state.products;

    // Filter by category
    if (state.currentCategory !== 'all') {
        filtered = filtered.filter(p => p.category === state.currentCategory);
    }

    // Filter by search - no limit when searching
    if (state.searchTerm) {
        filtered = filtered.filter(p => 
            p.name.toLowerCase().includes(state.searchTerm.toLowerCase())
        );
    } else {
        // Limit to 10 products per category when not searching
        if (state.currentCategory === 'all') {
            const byCategory = {};
            filtered.forEach(p => {
                if (!byCategory[p.category]) byCategory[p.category] = [];
                if (byCategory[p.category].length < 10) {
                    byCategory[p.category].push(p);
                }
            });
            filtered = Object.values(byCategory).flat();
        } else {
            filtered = filtered.slice(0, 10);
        }
    }

    if (filtered.length === 0) {
        container.innerHTML = '<div class="col-span-full text-center py-12 text-slate-400">No se encontraron productos</div>';
        return;
    }

    // Check which products are in cart
    const cartProductIds = state.cart.map(item => item.productId);

    container.innerHTML = filtered.map(product => {
        const inCart = cartProductIds.includes(product.id);
        const cartItem = state.cart.find(item => item.productId === product.id);
        return `
        <button onclick="addToCart('${product.id}')" 
            class="product-card relative bg-white rounded-2xl p-4 shadow-sm hover:shadow-md transition text-left ${product.stock <= 0 ? 'opacity-50' : ''} ${inCart ? 'in-cart' : ''}"
            ${product.stock <= 0 ? 'disabled' : ''}>
            <div class="w-full aspect-square bg-slate-100 rounded-xl mb-3 flex items-center justify-center">
                <span class="text-3xl">${getCategoryIcon(product.category)}</span>
            </div>
            <h4 class="font-semibold text-sm truncate">${product.name}</h4>
            <div class="flex justify-between items-center mt-2">
                <span class="text-primary font-bold">$${product.price.toFixed(2)}</span>
                <span class="text-xs ${product.stock <= 10 ? 'text-danger font-bold' : 'text-slate-400'}">Stock: ${product.stock}</span>
            </div>
            ${inCart ? `<span class="absolute top-2 left-2 bg-primary text-white text-xs px-2 py-1 rounded-full">${cartItem.quantity}</span>` : ''}
        </button>
    `}).join('');
}

function getCategoryIcon(category) {
    const icons = {
        bebidas: '<svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
        comidas: '<svg class="w-12 h-12 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
        dulces: '<svg class="w-12 h-12 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18z"/></svg>',
        otros: '<svg class="w-12 h-12 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>'
    };
    return icons[category] || icons.otros;
}

function filterCategory(category) {
    state.currentCategory = category;
    
    // Update category buttons
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('bg-primary', 'text-white');
        btn.classList.add('bg-slate-100');
        if (btn.dataset.category === category) {
            btn.classList.add('bg-primary', 'text-white');
            btn.classList.remove('bg-slate-100');
        }
    });

    renderProducts();
}

function handleSearch(e) {
    state.searchTerm = e.target.value;
    renderProducts();
}

function renderProductsTable() {
    const tbody = document.getElementById('productsTable');
    
    if (state.products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-slate-400">No hay productos</td></tr>';
        return;
    }

    tbody.innerHTML = state.products.map(product => `
        <tr class="hover:bg-slate-50">
            <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center">
                        ${getCategoryIcon(product.category).replace('w-12 h-12', 'w-5 h-5')}
                    </div>
                    <span class="font-medium">${product.name}</span>
                </div>
            </td>
            <td class="px-4 py-3 capitalize">${product.category}</td>
            <td class="px-4 py-3 font-semibold">$${product.price.toFixed(2)}</td>
            <td class="px-4 py-3">
                <span class="${product.stock <= 5 ? 'text-danger' : ''}">${product.stock}</span>
            </td>
            <td class="px-4 py-3">
                <div class="flex gap-2">
                    <button onclick="editProduct('${product.id}')" class="p-2 hover:bg-slate-100 rounded-lg transition">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button onclick="deleteProduct('${product.id}')" class="p-2 hover:bg-danger/10 rounded-lg transition">
                        <svg class="w-4 h-4 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function openProductModal(id = null) {
    const modal = document.getElementById('productModal');
    const title = document.getElementById('productModalTitle');
    
    if (id) {
        const product = state.products.find(p => p.id === id);
        if (product) {
            title.textContent = 'Editar Producto';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productCategory').value = product.category;
            document.getElementById('productPrice').value = product.price;
            document.getElementById('productStock').value = product.stock;
        }
    } else {
        title.textContent = 'Agregar Producto';
        document.getElementById('productForm').reset();
        document.getElementById('productId').value = '';
    }
    
    modal.classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
    document.getElementById('productForm').reset();
}

function handleProductSave(e) {
    e.preventDefault();
    
    const id = document.getElementById('productId').value;
    const productData = {
        name: document.getElementById('productName').value,
        category: document.getElementById('productCategory').value,
        price: parseFloat(document.getElementById('productPrice').value),
        stock: parseInt(document.getElementById('productStock').value)
    };

    if (id) {
        const index = state.products.findIndex(p => p.id === id);
        if (index !== -1) {
            state.products[index] = { ...state.products[index], ...productData };
        }
    } else {
        state.products.push({
            id: generateId(),
            ...productData,
            createdAt: new Date().toISOString()
        });
    }

    saveState();
    closeProductModal();
    renderProductsTable();
    renderProducts();
}

function editProduct(id) {
    openProductModal(id);
}

function deleteProduct(id) {
    if (confirm('¿Estas seguro de eliminar este producto?')) {
        state.products = state.products.filter(p => p.id !== id);
        saveState();
        renderProductsTable();
        renderProducts();
    }
}

// ==========================================
// Cart
// ==========================================

function addToCart(productId) {
    const product = state.products.find(p => p.id === productId);
    if (!product || product.stock <= 0) return;

    const existingItem = state.cart.find(item => item.productId === productId);
    
    if (existingItem) {
        if (existingItem.quantity < product.stock) {
            existingItem.quantity++;
        }
    } else {
        state.cart.push({
            productId: productId,
            name: product.name,
            price: product.price,
            quantity: 1
        });
    }

    renderCart();
    renderProducts(); // Update product grid to show selection
}

function removeFromCart(productId) {
    state.cart = state.cart.filter(item => item.productId !== productId);
    renderCart();
    renderProducts();
}

function updateCartQuantity(productId, change) {
    const item = state.cart.find(i => i.productId === productId);
    const product = state.products.find(p => p.id === productId);
    
    if (item && product) {
        item.quantity += change;
        if (item.quantity <= 0) {
            removeFromCart(productId);
        } else if (item.quantity > product.stock) {
            item.quantity = product.stock;
        }
        renderCart();
        renderProducts();
    }
}

function setCartQuantity(productId, value) {
    const item = state.cart.find(i => i.productId === productId);
    const product = state.products.find(p => p.id === productId);
    
    // Parse as integer, remove any decimals
    let qty = parseInt(value, 10);
    
    // Validate
    if (isNaN(qty) || qty < 1) {
        qty = 1;
    }
    
    if (item && product) {
        if (qty > product.stock) {
            qty = product.stock;
        }
        item.quantity = qty;
        renderCart();
        renderProducts();
    }
}

function renderCart() {
    const container = document.getElementById('cartItems');
    const totalEl = document.getElementById('cartTotal');

    if (state.cart.length === 0) {
        container.innerHTML = '<p class="text-slate-400 text-center py-8">Carrito vacio</p>';
        totalEl.textContent = '$0.00';
        return;
    }

    let total = 0;
    container.innerHTML = state.cart.map(item => {
        const product = state.products.find(p => p.id === item.productId);
        const maxStock = product ? product.stock : item.quantity;
        const subtotal = item.price * item.quantity;
        total += subtotal;
        return `
            <div class="cart-item bg-slate-50 rounded-xl p-3">
                <div class="flex justify-between items-start mb-2">
                    <span class="font-medium text-sm">${item.name}</span>
                    <button onclick="removeFromCart('${item.productId}')" class="text-slate-400 hover:text-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <button onclick="updateCartQuantity('${item.productId}', -1)" class="w-7 h-7 bg-white rounded-lg flex items-center justify-center hover:bg-slate-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                            </svg>
                        </button>
                        <input type="number" 
                            value="${item.quantity}" 
                            min="1" 
                            max="${maxStock}"
                            onchange="setCartQuantity('${item.productId}', this.value)"
                            onkeydown="return event.key !== '.' && event.key !== ',' && event.key !== '-' && event.key !== 'e'"
                            class="w-12 text-center font-semibold bg-white border border-slate-200 rounded-lg py-1 focus:outline-none focus:ring-2 focus:ring-primary">
                        <button onclick="updateCartQuantity('${item.productId}', 1)" class="w-7 h-7 bg-white rounded-lg flex items-center justify-center hover:bg-slate-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>
                    <span class="font-bold text-primary">$${subtotal.toFixed(2)}</span>
                </div>
            </div>
        `;
    }).join('');

    totalEl.textContent = '$' + total.toFixed(2);
}

function clearCart() {
    if (state.cart.length > 0 && confirm('¿Limpiar el carrito?')) {
        state.cart = [];
        renderCart();
        renderProducts();
    }
}

function renderCustomerSelect() {
    const select = document.getElementById('customerSelect');
    select.innerHTML = '<option value="">Cliente General</option>' +
        state.customers.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
}

// ==========================================
// Sales
// ==========================================

function processSale() {
    if (state.cart.length === 0) {
        alert('El carrito esta vacio');
        return;
    }

    const total = state.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const customerId = document.getElementById('customerSelect').value;
    const saleDate = new Date();

    // Create sale record
    const sale = {
        id: generateId(),
        items: [...state.cart],
        total: total,
        customerId: customerId || null,
        userId: state.currentUser.id,
        userName: state.currentUser.name,
        date: saleDate.toISOString()
    };

    // Update stock
    state.cart.forEach(item => {
        const product = state.products.find(p => p.id === item.productId);
        if (product) {
            product.stock -= item.quantity;
        }
    });

    // Update user sales count
    const user = state.users.find(u => u.id === state.currentUser.id);
    if (user) {
        user.salesCount++;
    }

    // Update customer if selected
    if (customerId) {
        const customer = state.customers.find(c => c.id === customerId);
        if (customer) {
            customer.totalPurchases += total;
            customer.lastPurchase = saleDate.toISOString();
        }
    }

    state.sales.push(sale);
    
    // Build receipt
    document.getElementById('receiptBusinessName').textContent = state.businessName;
    document.getElementById('saleTotal').textContent = '$' + total.toFixed(2);
    document.getElementById('receiptDate').textContent = saleDate.toLocaleDateString('es-ES', { 
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' 
    });
    document.getElementById('receiptVendor').textContent = 'Atendido por: ' + state.currentUser.name;
    
    // Receipt items
    const receiptDetails = document.getElementById('receiptDetails');
    receiptDetails.innerHTML = state.cart.map(item => `
        <div class="flex justify-between">
            <span>${item.quantity}x ${item.name}</span>
            <span>$${(item.price * item.quantity).toFixed(2)}</span>
        </div>
    `).join('');
    
    state.cart = [];
    
    saveState();
    renderCart();
    renderProducts();

    // Show success modal
    document.getElementById('saleSuccessModal').classList.remove('hidden');
}

function closeSaleSuccessModal() {
    document.getElementById('saleSuccessModal').classList.add('hidden');
}

// ==========================================
// Vendors
// ==========================================

function renderVendorsTable() {
    const tbody = document.getElementById('vendorsTable');
    const vendors = state.users.filter(u => u.role === 'vendedor');
    
    if (vendors.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-slate-400">No hay vendedores registrados</td></tr>';
        return;
    }

    tbody.innerHTML = vendors.map(vendor => `
        <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium">${vendor.name}</td>
            <td class="px-4 py-3">${vendor.username}</td>
            <td class="px-4 py-3">${vendor.salesCount}</td>
            <td class="px-4 py-3">
                <span class="px-2 py-1 rounded-full text-xs ${vendor.active ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'}">
                    ${vendor.active ? 'Activo' : 'Inactivo'}
                </span>
            </td>
            <td class="px-4 py-3">
                <div class="flex gap-2">
                    <button onclick="editVendor('${vendor.id}')" class="p-2 hover:bg-slate-100 rounded-lg transition">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button onclick="toggleVendorStatus('${vendor.id}')" class="p-2 hover:bg-slate-100 rounded-lg transition">
                        <svg class="w-4 h-4 ${vendor.active ? 'text-danger' : 'text-success'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${vendor.active ? 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'}"/>
                        </svg>
                    </button>
                    <button onclick="deleteVendor('${vendor.id}')" class="p-2 hover:bg-danger/10 rounded-lg transition">
                        <svg class="w-4 h-4 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function openVendorModal(id = null) {
    const modal = document.getElementById('vendorModal');
    const title = document.getElementById('vendorModalTitle');
    const passInput = document.getElementById('vendorPass');
    const passHint = document.getElementById('vendorPassHint');
    
    if (id) {
        const vendor = state.users.find(u => u.id === id);
        if (vendor) {
            title.textContent = 'Editar Vendedor';
            document.getElementById('vendorId').value = vendor.id;
            document.getElementById('vendorName').value = vendor.name;
            document.getElementById('vendorUser').value = vendor.username;
            passInput.value = '';
            passInput.removeAttribute('required');
            passInput.placeholder = 'Dejar vacio para mantener';
            passHint.classList.remove('hidden');
        }
    } else {
        title.textContent = 'Agregar Vendedor';
        document.getElementById('vendorForm').reset();
        document.getElementById('vendorId').value = '';
        passInput.setAttribute('required', 'required');
        passInput.placeholder = 'Contrasena requerida';
        passHint.classList.add('hidden');
    }
    
    modal.classList.remove('hidden');
}

function closeVendorModal() {
    document.getElementById('vendorModal').classList.add('hidden');
    document.getElementById('vendorForm').reset();
}

function handleVendorSave(e) {
    e.preventDefault();
    
    const id = document.getElementById('vendorId').value;
    const name = document.getElementById('vendorName').value;
    const username = document.getElementById('vendorUser').value;
    const password = document.getElementById('vendorPass').value;

    if (id) {
        // Editing existing vendor
        const index = state.users.findIndex(u => u.id === id);
        if (index !== -1) {
            state.users[index].name = name;
            state.users[index].username = username;
            if (password) {
                state.users[index].password = password;
            }
        }
    } else {
        // Creating new vendor - password is required
        if (!password || password.trim() === '') {
            alert('La contrasena es requerida para nuevos vendedores');
            return;
        }
        
        // Check if username exists
        if (state.users.some(u => u.username === username)) {
            alert('Este usuario ya existe');
            return;
        }
        
        state.users.push({
            id: generateId(),
            name: name,
            username: username,
            password: password,
            role: 'vendedor',
            salesCount: 0,
            active: true,
            createdAt: new Date().toISOString()
        });
    }

    saveState();
    closeVendorModal();
    renderVendorsTable();
}

function editVendor(id) {
    openVendorModal(id);
}

function toggleVendorStatus(id) {
    const vendor = state.users.find(u => u.id === id);
    if (vendor) {
        vendor.active = !vendor.active;
        saveState();
        renderVendorsTable();
    }
}

function deleteVendor(id) {
    if (confirm('¿Estas seguro de eliminar este vendedor?')) {
        state.users = state.users.filter(u => u.id !== id);
        saveState();
        renderVendorsTable();
    }
}

// ==========================================
// Customers
// ==========================================

function renderCustomersTable() {
    const tbody = document.getElementById('customersTable');
    
    if (state.customers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-slate-400">No hay clientes registrados</td></tr>';
        return;
    }

    tbody.innerHTML = state.customers.map(customer => `
        <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium">${customer.name}</td>
            <td class="px-4 py-3">${customer.phone || '-'}</td>
            <td class="px-4 py-3 font-semibold text-success">$${customer.totalPurchases.toFixed(2)}</td>
            <td class="px-4 py-3 text-sm text-slate-500">${customer.lastPurchase ? formatDate(customer.lastPurchase) : 'Nunca'}</td>
            <td class="px-4 py-3">
                <div class="flex gap-2">
                    <button onclick="editCustomer('${customer.id}')" class="p-2 hover:bg-slate-100 rounded-lg transition">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button onclick="viewCustomerSales('${customer.id}')" class="p-2 hover:bg-slate-100 rounded-lg transition">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                    <button onclick="deleteCustomer('${customer.id}')" class="p-2 hover:bg-danger/10 rounded-lg transition">
                        <svg class="w-4 h-4 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function openCustomerModal(id = null) {
    const modal = document.getElementById('customerModal');
    const title = document.getElementById('customerModalTitle');
    
    if (id) {
        const customer = state.customers.find(c => c.id === id);
        if (customer) {
            title.textContent = 'Editar Cliente';
            document.getElementById('customerId').value = customer.id;
            document.getElementById('customerName').value = customer.name;
            document.getElementById('customerPhone').value = customer.phone || '';
            document.getElementById('customerEmail').value = customer.email || '';
        }
    } else {
        title.textContent = 'Agregar Cliente';
        document.getElementById('customerForm').reset();
        document.getElementById('customerId').value = '';
    }
    
    modal.classList.remove('hidden');
}

function closeCustomerModal() {
    document.getElementById('customerModal').classList.add('hidden');
    document.getElementById('customerForm').reset();
}

function handleCustomerSave(e) {
    e.preventDefault();
    
    const id = document.getElementById('customerId').value;
    const customerData = {
        name: document.getElementById('customerName').value,
        phone: document.getElementById('customerPhone').value,
        email: document.getElementById('customerEmail').value
    };

    if (id) {
        const index = state.customers.findIndex(c => c.id === id);
        if (index !== -1) {
            state.customers[index] = { ...state.customers[index], ...customerData };
        }
    } else {
        state.customers.push({
            id: generateId(),
            ...customerData,
            totalPurchases: 0,
            lastPurchase: null,
            createdAt: new Date().toISOString()
        });
    }

    saveState();
    closeCustomerModal();
    renderCustomersTable();
    renderCustomerSelect();
}

function editCustomer(id) {
    openCustomerModal(id);
}

function deleteCustomer(id) {
    if (confirm('¿Estas seguro de eliminar este cliente?')) {
        state.customers = state.customers.filter(c => c.id !== id);
        saveState();
        renderCustomersTable();
        renderCustomerSelect();
    }
}

function viewCustomerSales(customerId) {
    const customer = state.customers.find(c => c.id === customerId);
    const customerSales = state.sales.filter(s => s.customerId === customerId);
    
    let message = `Ventas de ${customer.name}:\n\n`;
    if (customerSales.length === 0) {
        message += 'Sin compras registradas';
    } else {
        customerSales.forEach(sale => {
            message += `${formatDate(sale.date)} - $${sale.total.toFixed(2)}\n`;
        });
    }
    alert(message);
}

// ==========================================
// Dashboard
// ==========================================

function updateDashboard() {
    const today = new Date().toDateString();
    const todaySales = state.sales.filter(s => new Date(s.date).toDateString() === today);
    
    const todayTotal = todaySales.reduce((sum, s) => sum + s.total, 0);
    const lowStockCount = state.products.filter(p => p.stock <= 10).length;

    document.getElementById('todaySales').textContent = '$' + todayTotal.toFixed(2);
    document.getElementById('todayCount').textContent = todaySales.length;
    document.getElementById('totalProducts').textContent = state.products.length;
    document.getElementById('lowStock').textContent = lowStockCount;

    // Recent sales
    const recentContainer = document.getElementById('recentSales');
    const recentSales = [...state.sales].reverse().slice(0, 5);
    
    if (recentSales.length === 0) {
        recentContainer.innerHTML = '<p class="text-slate-400 text-center py-4">Sin ventas recientes</p>';
    } else {
        recentContainer.innerHTML = recentSales.map(sale => `
            <div class="flex justify-between items-center py-2 border-b last:border-0">
                <div>
                    <p class="font-medium text-sm">${sale.items.length} producto(s)</p>
                    <p class="text-xs text-slate-500">${formatDate(sale.date)} - ${sale.userName}</p>
                </div>
                <span class="font-bold text-success">$${sale.total.toFixed(2)}</span>
            </div>
        `).join('');
    }

    // Top products
    const topContainer = document.getElementById('topProducts');
    const productSales = {};
    
    state.sales.forEach(sale => {
        sale.items.forEach(item => {
            if (!productSales[item.name]) {
                productSales[item.name] = 0;
            }
            productSales[item.name] += item.quantity;
        });
    });

    const topProducts = Object.entries(productSales)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5);

    if (topProducts.length === 0) {
        topContainer.innerHTML = '<p class="text-slate-400 text-center py-4">Sin datos</p>';
    } else {
        topContainer.innerHTML = topProducts.map(([name, qty], index) => `
            <div class="flex justify-between items-center py-2 border-b last:border-0">
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold">${index + 1}</span>
                    <span class="font-medium text-sm">${name}</span>
                </div>
                <span class="text-slate-600">${qty} vendidos</span>
            </div>
        `).join('');
    }
}

// ==========================================
// Reports
// ==========================================

function showReport(type) {
    const container = document.getElementById('reportContent');
    container.classList.remove('hidden');
    
    let html = '';
    const today = new Date();
    
    if (type === 'daily') {
        const todaySales = state.sales.filter(s => new Date(s.date).toDateString() === today.toDateString());
        const total = todaySales.reduce((sum, s) => sum + s.total, 0);
        
        const productsSold = {};
        todaySales.forEach(sale => {
            sale.items.forEach(item => {
                if (!productsSold[item.name]) {
                    productsSold[item.name] = { qty: 0, total: 0 };
                }
                productsSold[item.name].qty += item.quantity;
                productsSold[item.name].total += item.price * item.quantity;
            });
        });

        html = `
            <h3 class="font-bold text-xl mb-4">Reporte Diario - ${formatDate(today.toISOString())}</h3>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-sm">Total Vendido</p>
                    <p class="text-2xl font-bold text-success">$${total.toFixed(2)}</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-sm">Cantidad de Ventas</p>
                    <p class="text-2xl font-bold">${todaySales.length}</p>
                </div>
            </div>
            <h4 class="font-semibold mb-3">Detalle por Producto</h4>
            <div class="bg-slate-50 rounded-xl overflow-hidden">
                <table class="w-full">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="text-left px-4 py-2 text-sm">Producto</th>
                            <th class="text-left px-4 py-2 text-sm">Cantidad</th>
                            <th class="text-left px-4 py-2 text-sm">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${Object.entries(productsSold).map(([name, data]) => `
                            <tr class="border-t border-slate-200">
                                <td class="px-4 py-2">${name}</td>
                                <td class="px-4 py-2">${data.qty}</td>
                                <td class="px-4 py-2 font-semibold">$${data.total.toFixed(2)}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="3" class="text-center py-4 text-slate-400">Sin ventas hoy</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    } else if (type === 'monthly') {
        const monthSales = state.sales.filter(s => {
            const saleDate = new Date(s.date);
            return saleDate.getMonth() === today.getMonth() && saleDate.getFullYear() === today.getFullYear();
        });
        const total = monthSales.reduce((sum, s) => sum + s.total, 0);
        
        const productsSold = {};
        monthSales.forEach(sale => {
            sale.items.forEach(item => {
                if (!productsSold[item.name]) {
                    productsSold[item.name] = 0;
                }
                productsSold[item.name] += item.quantity;
            });
        });

        const topProduct = Object.entries(productsSold).sort((a, b) => b[1] - a[1])[0];

        html = `
            <h3 class="font-bold text-xl mb-4">Reporte Mensual - ${today.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' })}</h3>
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-sm">Total del Mes</p>
                    <p class="text-2xl font-bold text-success">$${total.toFixed(2)}</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-sm">Ventas Realizadas</p>
                    <p class="text-2xl font-bold">${monthSales.length}</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-sm">Mas Vendido</p>
                    <p class="text-lg font-bold">${topProduct ? topProduct[0] : '-'}</p>
                </div>
            </div>
        `;
    } else if (type === 'lowstock') {
        const lowStockProducts = state.products.filter(p => p.stock <= 10).sort((a, b) => a.stock - b.stock);
        
        html = `
            <h3 class="font-bold text-xl mb-4">Reporte de Stock Bajo - Productos a Reponer</h3>
            <div class="bg-danger/10 border border-danger/20 rounded-xl p-4 mb-6">
                <p class="text-danger font-semibold">${lowStockProducts.length} producto(s) con stock bajo (10 o menos unidades)</p>
            </div>
            <div class="bg-slate-50 rounded-xl overflow-hidden">
                <table class="w-full">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="text-left px-4 py-2 text-sm">Producto</th>
                            <th class="text-left px-4 py-2 text-sm">Categoria</th>
                            <th class="text-left px-4 py-2 text-sm">Stock Actual</th>
                            <th class="text-left px-4 py-2 text-sm">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lowStockProducts.map(p => `
                            <tr class="border-t border-slate-200">
                                <td class="px-4 py-2 font-medium">${p.name}</td>
                                <td class="px-4 py-2 capitalize">${p.category}</td>
                                <td class="px-4 py-2 font-bold ${p.stock === 0 ? 'text-danger' : 'text-warning'}">${p.stock}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 rounded-full text-xs ${p.stock === 0 ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning'}">
                                        ${p.stock === 0 ? 'AGOTADO' : 'Bajo'}
                                    </span>
                                </td>
                            </tr>
                        `).join('') || '<tr><td colspan="4" class="text-center py-4 text-slate-400">No hay productos con stock bajo</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    } else if (type === 'annual') {
        const yearSales = state.sales.filter(s => new Date(s.date).getFullYear() === today.getFullYear());
        const total = yearSales.reduce((sum, s) => sum + s.total, 0);
        
        const monthlySales = {};
        yearSales.forEach(sale => {
            const month = new Date(sale.date).toLocaleDateString('es-ES', { month: 'short' });
            if (!monthlySales[month]) {
                monthlySales[month] = 0;
            }
            monthlySales[month] += sale.total;
        });

        html = `
            <h3 class="font-bold text-xl mb-4">Reporte Anual - ${today.getFullYear()}</h3>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-sm">Total del Ano</p>
                    <p class="text-2xl font-bold text-success">$${total.toFixed(2)}</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-sm">Ventas Totales</p>
                    <p class="text-2xl font-bold">${yearSales.length}</p>
                </div>
            </div>
            <h4 class="font-semibold mb-3">Ventas por Mes</h4>
            <div class="bg-slate-50 rounded-xl p-4">
                ${Object.entries(monthlySales).map(([month, amount]) => `
                    <div class="flex justify-between items-center py-2 border-b last:border-0 border-slate-200">
                        <span class="capitalize">${month}</span>
                        <span class="font-semibold">$${amount.toFixed(2)}</span>
                    </div>
                `).join('') || '<p class="text-center text-slate-400">Sin ventas este ano</p>'}
            </div>
        `;
    }

    container.innerHTML = html;
}

// ==========================================
// Stock View (for vendors)
// ==========================================

let currentStockFilter = 'all';

function renderStockViewTable(filter = 'all') {
    currentStockFilter = filter;
    const tbody = document.getElementById('stockViewTable');
    let products = [...state.products];
    
    // Update filter buttons
    document.querySelectorAll('.stock-filter-btn').forEach(btn => {
        btn.classList.remove('bg-primary', 'text-white');
        btn.classList.add('bg-slate-100');
        if (btn.dataset.stockfilter === filter) {
            btn.classList.add('bg-primary', 'text-white');
            btn.classList.remove('bg-slate-100');
        }
    });
    
    if (filter === 'low') {
        products = products.filter(p => p.stock <= 10);
    }
    
    if (products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-slate-400">No hay productos</td></tr>';
        return;
    }

    tbody.innerHTML = products.map(product => `
        <tr class="hover:bg-slate-50 ${product.stock <= 10 ? 'bg-danger/5' : ''}">
            <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center">
                        ${getCategoryIcon(product.category).replace('w-12 h-12', 'w-5 h-5')}
                    </div>
                    <span class="font-medium">${product.name}</span>
                </div>
            </td>
            <td class="px-4 py-3 capitalize">${product.category}</td>
            <td class="px-4 py-3 font-semibold">$${product.price.toFixed(2)}</td>
            <td class="px-4 py-3">
                <span class="font-bold ${product.stock <= 10 ? 'text-danger' : ''}">${product.stock}</span>
            </td>
            <td class="px-4 py-3">
                ${product.stock === 0 
                    ? '<span class="px-3 py-1 rounded-full text-xs bg-danger/10 text-danger font-semibold">AGOTADO</span>'
                    : product.stock <= 10 
                        ? '<span class="px-3 py-1 rounded-full text-xs bg-warning/10 text-warning font-semibold">Stock Bajo</span>'
                        : '<span class="px-3 py-1 rounded-full text-xs bg-success/10 text-success">OK</span>'
                }
            </td>
        </tr>
    `).join('');
}

function filterStockView(filter) {
    renderStockViewTable(filter);
}

// ==========================================
// Cash Close
// ==========================================

function updateCashClose() {
    const today = new Date().toDateString();
    const todaySales = state.sales.filter(s => new Date(s.date).toDateString() === today);
    const total = todaySales.reduce((sum, s) => sum + s.total, 0);

    document.getElementById('openTime').textContent = state.cashOpened ? 
        new Date(state.cashOpened).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) : '--:--';
    document.getElementById('closeTotalSales').textContent = todaySales.length;
    document.getElementById('closeTotalCash').textContent = '$' + total.toFixed(2);

    const productsSold = {};
    todaySales.forEach(sale => {
        sale.items.forEach(item => {
            if (!productsSold[item.name]) {
                productsSold[item.name] = 0;
            }
            productsSold[item.name] += item.quantity;
        });
    });

    const listContainer = document.getElementById('closeProductsList').querySelector('.bg-slate-50');
    if (Object.keys(productsSold).length === 0) {
        listContainer.innerHTML = '<p class="text-slate-400 text-center">Sin ventas</p>';
    } else {
        listContainer.innerHTML = Object.entries(productsSold).map(([name, qty]) => `
            <div class="flex justify-between py-1">
                <span>${name}</span>
                <span class="font-semibold">${qty}</span>
            </div>
        `).join('');
    }
}

function printClose() {
    window.print();
}

function closeCash() {
    if (!confirm('¿Estas seguro de cerrar la caja? Esta accion no se puede deshacer.')) {
        return;
    }

    const today = new Date().toDateString();
    const todaySales = state.sales.filter(s => new Date(s.date).toDateString() === today);
    const total = todaySales.reduce((sum, s) => sum + s.total, 0);

    const productsSold = {};
    todaySales.forEach(sale => {
        sale.items.forEach(item => {
            if (!productsSold[item.name]) {
                productsSold[item.name] = 0;
            }
            productsSold[item.name] += item.quantity;
        });
    });

    const closure = {
        id: generateId(),
        openTime: state.cashOpened,
        closeTime: new Date().toISOString(),
        totalSales: todaySales.length,
        totalCash: total,
        productsSold: productsSold,
        closedBy: state.currentUser.id,
        closedByName: state.currentUser.name
    };

    state.cashClosures.push(closure);
    state.cashOpened = new Date().toISOString();
    
    saveState();
    updateCashClose();
    
    alert('Caja cerrada exitosamente');
}

// ==========================================
// Utilities
// ==========================================

function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
