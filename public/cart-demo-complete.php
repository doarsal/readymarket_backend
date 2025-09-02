<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Carrito de Compras - Microsoft Marketplace</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .main-content {
            display: flex;
            min-height: 600px;
        }

        .products-section {
            flex: 2;
            padding: 20px;
            border-right: 1px solid #eee;
        }

        .cart-section {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
        }

        .auth-section {
            background: #e3f2fd;
            padding: 20px;
            border-bottom: 1px solid #ddd;
        }

        .product {
            border: 1px solid #ddd;
            margin: 10px 0;
            padding: 15px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .product h3 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .product .price {
            font-size: 18px;
            font-weight: bold;
            color: #27ae60;
            margin: 10px 0;
        }

        .product .sku {
            color: #7f8c8d;
            font-size: 12px;
        }

        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }

        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }

        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #d68910; }

        .cart-item {
            background: white;
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }

        .cart-total {
            background: #2c3e50;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .quantity-controls {
            display: inline-flex;
            margin: 10px 0;
        }

        .quantity-controls button {
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
        }

        .quantity-controls input {
            width: 60px;
            height: 30px;
            text-align: center;
            border: 1px solid #ddd;
            border-left: none;
            border-right: none;
        }

        .auth-form {
            display: none;
            margin-top: 15px;
        }

        .auth-form.active {
            display: block;
        }

        .form-group {
            margin: 10px 0;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .alert {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .cart-token {
            font-size: 11px;
            color: #666;
            margin-top: 10px;
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Estilos para formulario de pago */
        .payment-section {
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            border: 2px solid #007bff;
            display: none;
        }

        .payment-section.active {
            display: block;
        }

        .payment-form {
            display: grid;
            gap: 15px;
        }

        .payment-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .payment-form .form-group {
            display: flex;
            flex-direction: column;
        }

        .payment-form label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .payment-form input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .payment-form input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }

        .btn-checkout {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
        }

        .btn-checkout:hover {
            background: #218838;
        }

        .btn-checkout:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛒 Microsoft Marketplace - Demo Carrito</h1>
            <p>Sistema completo de carrito de compras con autenticación</p>
        </div>

        <div class="auth-section">
            <div id="guest-view">
                <h3>👤 Usuario Visitante</h3>
                <p>Estás navegando como visitante. Puedes agregar productos al carrito.</p>
                <button class="btn btn-success" onclick="showLogin()">Iniciar Sesión</button>
                <button class="btn" onclick="showRegister()">Registrarse</button>
            </div>

            <div id="user-view" style="display: none;">
                <h3>👤 Bienvenido, <span id="user-name"></span></h3>
                <button class="btn btn-danger" onclick="logout()">Cerrar Sesión</button>
            </div>

            <!-- Login Form -->
            <div id="login-form" class="auth-form">
                <h4>Iniciar Sesión</h4>
                <div class="form-group">
                    <input type="email" id="login-email" placeholder="Email" value="test@example.com">
                </div>
                <div class="form-group">
                    <input type="password" id="login-password" placeholder="Contraseña" value="password123">
                </div>
                <button class="btn btn-success" onclick="login()">Entrar</button>
                <button class="btn" onclick="hideAuthForms()">Cancelar</button>
            </div>

            <!-- Register Form -->
            <div id="register-form" class="auth-form">
                <h4>Registrarse</h4>
                <div class="form-group">
                    <input type="text" id="register-name" placeholder="Nombre completo">
                </div>
                <div class="form-group">
                    <input type="email" id="register-email" placeholder="Email">
                </div>
                <div class="form-group">
                    <input type="password" id="register-password" placeholder="Contraseña">
                </div>
                <div class="form-group">
                    <input type="password" id="register-password-confirm" placeholder="Confirmar contraseña">
                </div>
                <button class="btn btn-success" onclick="register()">Registrarse</button>
                <button class="btn" onclick="hideAuthForms()">Cancelar</button>
            </div>

            <div id="auth-message"></div>
        </div>

        <div class="main-content">
            <div class="products-section">
                <h2>📦 Productos Microsoft</h2>
                <div id="products-list">
                    <div class="product">
                        <h3>Access LTSC 2021</h3>
                        <div class="sku">SKU: 0001</div>
                        <div class="price">$171.00 USD</div>
                        <button class="btn" onclick="addToCart(1, 'Access LTSC 2021', 171)">Agregar al Carrito</button>
                    </div>

                    <div class="product">
                        <h3>Azure Information Protection Premium P1</h3>
                        <div class="sku">SKU: 0001</div>
                        <div class="price">$5.40 USD</div>
                        <button class="btn" onclick="addToCart(2, 'Azure Information Protection Premium P1', 5.4)">Agregar al Carrito</button>
                    </div>

                    <div class="product">
                        <h3>Exchange Online Archiving for Exchange Server</h3>
                        <div class="sku">SKU: 0001</div>
                        <div class="price">$2.88 USD</div>
                        <button class="btn" onclick="addToCart(4, 'Exchange Online Archiving for Exchange Server', 2.88)">Agregar al Carrito</button>
                    </div>

                    <div class="product">
                        <h3>Azure Active Directory Premium P1</h3>
                        <div class="sku">SKU: 0002</div>
                        <div class="price">$5.76 USD</div>
                        <button class="btn" onclick="addToCart(5, 'Azure Active Directory Premium P1', 5.76)">Agregar al Carrito</button>
                    </div>
                </div>
            </div>

            <div class="cart-section">
                <h2>🛒 Mi Carrito</h2>
                <div id="cart-items"></div>
                <div id="cart-total" class="cart-total" style="display: none;">
                    <div>Subtotal: $<span id="subtotal">0.00</span></div>
                    <div>IVA (16%): $<span id="tax">0.00</span></div>
                    <div><strong>Total: $<span id="total">0.00</span></strong></div>
                    <button class="btn-checkout" onclick="showPaymentForm()" id="checkout-btn" style="display: none;">
                        💳 Proceder al Pago
                    </button>
                </div>

                <!-- Formulario de Pago MITEC -->
                <div id="payment-section" class="payment-section">
                    <h3>💳 Información de Pago</h3>
                    <p><small>Procesado seguramente por MITEC (Ambiente de Producción)</small></p>

                    <form id="payment-form" class="payment-form">
                        <div class="form-group">
                            <label for="card_name">Nombre del Tarjetahabiente</label>
                            <input type="text" id="card_name" name="card_name" value="PAULINO MOTA" required>
                        </div>

                        <div class="form-group">
                            <label for="card_number">Número de Tarjeta</label>
                            <input type="text" id="card_number" name="card_number" value="376701040491010"
                                   placeholder="1234 5678 9012 3456" maxlength="19" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="exp_month">Mes de Expiración</label>
                                <select id="exp_month" name="exp_month" required>
                                    <option value="">MM</option>
                                    <option value="01">01</option>
                                    <option value="02">02</option>
                                    <option value="03">03</option>
                                    <option value="04">04</option>
                                    <option value="05">05</option>
                                    <option value="06">06</option>
                                    <option value="07" selected>07</option>
                                    <option value="08">08</option>
                                    <option value="09">09</option>
                                    <option value="10">10</option>
                                    <option value="11">11</option>
                                    <option value="12">12</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="exp_year">Año de Expiración</label>
                                <select id="exp_year" name="exp_year" required>
                                    <option value="">YY</option>
                                    <option value="25">25</option>
                                    <option value="26">26</option>
                                    <option value="27">27</option>
                                    <option value="28">28</option>
                                    <option value="29" selected>29</option>
                                    <option value="30">30</option>
                                    <option value="31">31</option>
                                    <option value="32">32</option>
                                    <option value="33">33</option>
                                    <option value="34">34</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="cvv">CVV</label>
                            <input type="text" id="cvv" name="cvv" value="6261" placeholder="123" maxlength="4" required>
                        </div>

                        <div class="form-group">
                            <label for="billing_email">Email de Facturación</label>
                            <input type="email" id="billing_email" name="billing_email" value="test@example.com" required>
                        </div>

                        <div class="form-group">
                            <label for="billing_phone">Teléfono de Facturación</label>
                            <input type="tel" id="billing_phone" name="billing_phone" value="5555555555" required>
                        </div>

                        <button type="submit" class="btn-checkout" id="pay-btn">
                            🔒 Pagar $<span id="final-total">0.00</span>
                        </button>

                        <button type="button" class="btn" onclick="hidePaymentForm()" style="margin-top: 10px;">
                            Cancelar
                        </button>
                    </form>
                </div>
                <div class="cart-token" id="cart-token-display"></div>
                <button class="btn btn-danger" onclick="clearCart()" style="margin-top: 15px;">Vaciar Carrito</button>
            </div>
        </div>
    </div>

    <script>
        // URL completamente relativa - desde public/ hacia api/v1/
        const API_BASE = './api/v1';

        let authToken = localStorage.getItem('auth_token');
        let cartToken = localStorage.getItem('cart_token');
        let currentUser = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            if (authToken) {
                checkAuthStatus();
            }

            // Si hay cart_token guardado, cargar el carrito existente
            // Si no hay token, mostrar carrito vacío (usuario nuevo)
            if (cartToken) {
                loadCart();
            } else {
                displayEmptyCart();
            }
        });

        // Auth Functions
        function showLogin() {
            hideAuthForms();
            document.getElementById('login-form').classList.add('active');
        }

        function showRegister() {
            hideAuthForms();
            document.getElementById('register-form').classList.add('active');
        }

        function hideAuthForms() {
            document.querySelectorAll('.auth-form').forEach(form => {
                form.classList.remove('active');
            });
        }

        async function register() {
            const name = document.getElementById('register-name').value;
            const email = document.getElementById('register-email').value;
            const password = document.getElementById('register-password').value;
            const password_confirmation = document.getElementById('register-password-confirm').value;

            if (!name || !email || !password || password !== password_confirmation) {
                showMessage('Por favor completa todos los campos y confirma la contraseña', 'error');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/auth/register`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Cart-Token': cartToken || ''
                    },
                    body: JSON.stringify({ name, email, password, password_confirmation })
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Registro exitoso! Se ha enviado un código de verificación a tu email.', 'success');
                    hideAuthForms();
                } else {
                    showMessage(data.message || 'Error en el registro', 'error');
                }
            } catch (error) {
                showMessage('Error de conexión', 'error');
                console.error('Register error:', error);
            }
        }

        async function login() {
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;

            if (!email || !password) {
                showMessage('Por favor ingresa email y contraseña', 'error');
                return;
            }

            try {
                const headers = {
                    'Content-Type': 'application/json'
                };

                // Include cart token if we have one (for guest cart merge)
                if (cartToken) {
                    headers['X-Cart-Token'] = cartToken;
                }

                const response = await fetch(`${API_BASE}/auth/login`, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();

                if (data.success) {
                    authToken = data.data.token;
                    currentUser = data.data.user;
                    localStorage.setItem('auth_token', authToken);

                    showMessage('Login exitoso! Carrito sincronizado.', 'success');
                    updateAuthUI();
                    hideAuthForms();

                    // Reload cart after login to see merged items
                    setTimeout(() => {
                        loadCart();
                    }, 500);
                } else {
                    showMessage(data.message || 'Error en el login', 'error');
                }
            } catch (error) {
                showMessage('Error de conexión', 'error');
                console.error('Login error:', error);
            }
        }

        async function logout() {
            try {
                if (authToken) {
                    await fetch(`${API_BASE}/auth/logout`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${authToken}`,
                            'Content-Type': 'application/json'
                        }
                    });
                }
            } catch (error) {
                console.error('Logout error:', error);
            }

            authToken = null;
            currentUser = null;
            localStorage.removeItem('auth_token');

            showMessage('Sesión cerrada', 'success');
            updateAuthUI();

            // Reload cart as guest
            loadCart();
        }

        async function checkAuthStatus() {
            try {
                const response = await fetch(`${API_BASE}/auth/me`, {
                    headers: {
                        'Authorization': `Bearer ${authToken}`,
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    currentUser = data.data;
                    updateAuthUI();
                } else {
                    // Token invalid
                    authToken = null;
                    localStorage.removeItem('auth_token');
                    updateAuthUI();
                }
            } catch (error) {
                console.error('Auth check error:', error);
                authToken = null;
                localStorage.removeItem('auth_token');
                updateAuthUI();
            }
        }

        function updateAuthUI() {
            const guestView = document.getElementById('guest-view');
            const userView = document.getElementById('user-view');
            const userName = document.getElementById('user-name');

            if (currentUser) {
                guestView.style.display = 'none';
                userView.style.display = 'block';
                userName.textContent = currentUser.name;
            } else {
                guestView.style.display = 'block';
                userView.style.display = 'none';
            }
        }

        // Cart Functions
        async function addToCart(productId, title, price) {
            try {
                const headers = {
                    'Content-Type': 'application/json'
                };

                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                } else if (cartToken) {
                    headers['X-Cart-Token'] = cartToken;
                }

                const response = await fetch(`${API_BASE}/cart/items`, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: 1
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Guardar el token del header de respuesta
                    const newCartToken = response.headers.get('X-Cart-Token');
                    if (newCartToken) {
                        cartToken = newCartToken;
                        localStorage.setItem('cart_token', cartToken);
                        displayCartToken(cartToken);
                    }

                    showMessage(`${title} agregado al carrito`, 'success');
                    // Usar directamente la respuesta del API, NO llamar loadCart()
                    displayCart(data.data.cart);
                } else {
                    showMessage(data.message || 'Error al agregar producto', 'error');
                }
            } catch (error) {
                showMessage('Error de conexión', 'error');
                console.error('Add to cart error:', error);
            }
        }

        function displayEmptyCart() {
            const cartItemsContainer = document.getElementById('cart-items');
            const cartTotalContainer = document.getElementById('cart-total');

            cartItemsContainer.innerHTML = '<p>Tu carrito está vacío</p>';
            cartTotalContainer.style.display = 'none';

            // No mostrar token si no hay carrito
            displayCartToken(null);
        }

        async function loadCart() {
            try {
                const headers = {
                    'Content-Type': 'application/json'
                };

                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                }

                if (cartToken) {
                    headers['X-Cart-Token'] = cartToken;
                }

                const response = await fetch(`${API_BASE}/cart`, {
                    headers: headers
                });

                if (response.ok) {
                    const data = await response.json();

                    if (data.success) {
                        // Update cart token from response header or data
                        const newCartToken = response.headers.get('X-Cart-Token') || data.data.cart_token;
                        if (newCartToken && newCartToken !== cartToken) {
                            cartToken = newCartToken;
                            localStorage.setItem('cart_token', cartToken);
                        }

                        displayCart(data.data);
                        displayCartToken(cartToken);
                    } else {
                        showMessage(data.message || 'Error al cargar carrito', 'error');
                    }
                } else {
                    const errorData = await response.json();
                    showMessage(errorData.message || 'Error al cargar carrito', 'error');
                }
            } catch (error) {
                console.error('Load cart error:', error);
                showMessage('Error de conexión al cargar carrito', 'error');
            }
        }

        function displayCart(cartData) {
            const cartItemsContainer = document.getElementById('cart-items');
            const cartTotalContainer = document.getElementById('cart-total');

            if (!cartData.items || cartData.items.length === 0) {
                cartItemsContainer.innerHTML = '<p>Tu carrito está vacío</p>';
                cartTotalContainer.style.display = 'none';
                return;
            }

            let itemsHTML = '';
            cartData.items.forEach(item => {
                itemsHTML += `
                    <div class="cart-item">
                        <h4>${item.product ? item.product.title : 'Producto'}</h4>
                        <div class="product-title">${item.product?.title || 'Producto sin nombre'}</div>
                        <div class="quantity-controls">
                            <button onclick="updateQuantity(${item.id}, ${item.quantity - 1})">-</button>
                            <input type="number" value="${item.quantity}" onchange="updateQuantity(${item.id}, this.value)" min="1">
                            <button onclick="updateQuantity(${item.id}, ${item.quantity + 1})">+</button>
                        </div>
                        <div>Precio unitario: $${item.unit_price}</div>
                        <div><strong>Total: $${item.total_price}</strong></div>
                        <button class="btn btn-danger" onclick="removeFromCart(${item.id})">Eliminar</button>
                    </div>
                `;
            });

            cartItemsContainer.innerHTML = itemsHTML;

            // Update totals
            document.getElementById('subtotal').textContent = cartData.subtotal || '0.00';
            document.getElementById('tax').textContent = cartData.tax_amount || '0.00';
            document.getElementById('total').textContent = cartData.total_amount || '0.00';
            cartTotalContainer.style.display = 'block';
        }

        function displayCartToken(token) {
            const display = document.getElementById('cart-token-display');
            if (token && !authToken) {
                display.innerHTML = `
                    <div style="font-size: 10px; color: #666; margin-top: 10px;">
                        🔑 Cart Token: ${token.substring(0, 12)}...
                        <br>
                        <a href="debug-cart.php?cart_token=${token}" target="_blank" style="color: #007bff;">Debug Cart</a>
                    </div>
                `;
            } else {
                display.innerHTML = '';
            }
        }

        async function updateQuantity(itemId, newQuantity) {
            if (newQuantity < 1) {
                removeFromCart(itemId);
                return;
            }

            try {
                const headers = {
                    'Content-Type': 'application/json'
                };

                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                } else if (cartToken) {
                    headers['X-Cart-Token'] = cartToken;
                }

                const response = await fetch(`${API_BASE}/cart/items/${itemId}`, {
                    method: 'PUT',
                    headers: headers,
                    body: JSON.stringify({ quantity: parseInt(newQuantity) })
                });

                const data = await response.json();

                if (data.success) {
                    // Usar directamente la respuesta del API, NO llamar loadCart()
                    displayCart(data.data.cart);
                } else {
                    showMessage(data.message || 'Error al actualizar cantidad', 'error');
                }
            } catch (error) {
                showMessage('Error de conexión', 'error');
                console.error('Update quantity error:', error);
            }
        }

        async function removeFromCart(itemId) {
            try {
                const headers = {
                    'Content-Type': 'application/json'
                };

                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                } else if (cartToken) {
                    headers['X-Cart-Token'] = cartToken;
                }

                const response = await fetch(`${API_BASE}/cart/items/${itemId}`, {
                    method: 'DELETE',
                    headers: headers
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Producto eliminado del carrito', 'success');
                    // Usar directamente la respuesta del API, NO llamar loadCart()
                    displayCart(data.data.cart);
                } else {
                    showMessage(data.message || 'Error al eliminar producto', 'error');
                }
            } catch (error) {
                showMessage('Error de conexión', 'error');
                console.error('Remove from cart error:', error);
            }
        }

        async function clearCart() {
            if (!confirm('¿Estás seguro de que quieres vaciar el carrito?')) {
                return;
            }

            try {
                const headers = {
                    'Content-Type': 'application/json'
                };

                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                } else if (cartToken) {
                    headers['X-Cart-Token'] = cartToken;
                }

                const response = await fetch(`${API_BASE}/cart/clear`, {
                    method: 'DELETE',
                    headers: headers
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Carrito vaciado', 'success');
                    // Mostrar carrito vacío directamente
                    displayEmptyCart();
                } else {
                    showMessage(data.message || 'Error al vaciar carrito', 'error');
                }
            } catch (error) {
                showMessage('Error de conexión', 'error');
                console.error('Clear cart error:', error);
            }
        }

        // Utility Functions
        function showMessage(message, type) {
            const messageContainer = document.getElementById('auth-message');
            messageContainer.innerHTML = `<div class="alert alert-${type === 'error' ? 'error' : 'success'}">${message}</div>`;

            setTimeout(() => {
                messageContainer.innerHTML = '';
            }, 5000);
        }

        // Payment Functions
        function showPaymentForm() {
            const paymentSection = document.getElementById('payment-section');
            const finalTotal = document.getElementById('final-total');
            const totalAmount = document.getElementById('total').textContent;

            finalTotal.textContent = totalAmount;
            paymentSection.classList.add('active');

            // Scroll to payment form
            paymentSection.scrollIntoView({ behavior: 'smooth' });
        }

        function hidePaymentForm() {
            const paymentSection = document.getElementById('payment-section');
            paymentSection.classList.remove('active');
        }

        // Format card number input
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Handle payment form submission
        document.getElementById('payment-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const payBtn = document.getElementById('pay-btn');
            const originalText = payBtn.innerHTML;

            try {
                payBtn.disabled = true;
                payBtn.innerHTML = '🔄 Procesando...';

                // Get form data
                const formData = new FormData(e.target);
                const paymentData = {
                    card_number: formData.get('card_number').replace(/\s/g, ''),
                    card_name: formData.get('card_name'),
                    exp_month: formData.get('exp_month'),
                    exp_year: formData.get('exp_year'),
                    cvv: formData.get('cvv'),
                    amount: parseFloat(document.getElementById('total').textContent || '0').toFixed(2),
                    currency: 'MXN',
                    billing_phone: formData.get('billing_phone'),
                    billing_email: formData.get('billing_email')
                };

                console.log('Enviando datos de pago:', paymentData);

                // Call payment API
                const response = await fetch(`${API_BASE}/payments/mitec/process`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${authToken}`,
                        'X-Cart-Token': cartToken  // Enviar cart_token en header
                    },
                    body: JSON.stringify(paymentData)
                });

                const result = await response.json();

                if (result.success) {
                    showMessage('¡Pago iniciado! Redirigiendo...', 'success');

                    // Redireccionar a la página de pago
                    window.location.href = result.redirect_url;

                } else {
                    showMessage(result.message || 'Error al procesar el pago', 'error');
                    console.error('Payment error:', result);
                }

            } catch (error) {
                showMessage('Error de conexión al procesar el pago', 'error');
                console.error('Payment processing error:', error);
            } finally {
                payBtn.disabled = false;
                payBtn.innerHTML = originalText;
            }
        });

        // Update cart display to show checkout button
        function updateCartDisplay() {
            const checkoutBtn = document.getElementById('checkout-btn');
            const totalElement = document.getElementById('total');

            if (totalElement && parseFloat(totalElement.textContent) > 0) {
                checkoutBtn.style.display = 'block';
            } else {
                checkoutBtn.style.display = 'none';
                hidePaymentForm();
            }
        }

        // Call updateCartDisplay whenever cart is updated
        const originalDisplayCart = displayCart;
        displayCart = function(cartData) {
            originalDisplayCart(cartData);
            updateCartDisplay();
        };

        const originalDisplayEmptyCart = displayEmptyCart;
        displayEmptyCart = function() {
            originalDisplayEmptyCart();
            updateCartDisplay();
        };
    </script>
</body>
</html>
