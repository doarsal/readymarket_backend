<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test API - Marketplace Microsoft 365</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .category-card:hover {
            transform: translateY(-5px);
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-3px);
            transition: transform 0.3s ease;
        }

        .loading {
            text-align: center;
            padding: 2rem;
        }

        .price-display {
            text-align: center;
        }

        .pagination .page-link {
            color: #0d6efd;
        }

        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
        }

        #productFilters {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
        }

        .search-highlight {
            background-color: yellow;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4"><i class="fas fa-store"></i> Marketplace Microsoft 365 <span id="categoryName"></span></h1>

                <!-- Categorías -->
                <div class="mb-4">
                    <h3>Categorías</h3>
                    <div id="categoriesContainer" class="row">
                        <div class="col-12">
                            <div class="loading">
                                <i class="fas fa-spinner fa-spin"></i> Cargando categorías...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Productos -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Productos</h3>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control" id="searchInput" placeholder="Buscar productos..." style="width: 300px;" onkeypress="if(event.key==='Enter') searchProducts()">
                            <button class="btn btn-outline-secondary" onclick="searchProducts()">
                                <i class="fas fa-search"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="clearSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Filtros y controles -->
                    <div class="row mb-3" id="productFilters" style="display: none;">
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="sortBy" onchange="loadProductsWithFilters()">
                                <option value="title">Ordenar por nombre</option>
                                <option value="unit_price">Ordenar por precio</option>
                                <option value="publisher">Ordenar por editor</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" id="sortOrder" onchange="loadProductsWithFilters()">
                                <option value="asc">Ascendente</option>
                                <option value="desc">Descendente</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" id="perPage" onchange="loadProductsWithFilters()">
                                <option value="9">9 por página</option>
                                <option value="18">18 por página</option>
                                <option value="36">36 por página</option>
                                <option value="50">50 por página</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-info me-2" id="totalProducts">0 productos</span>
                                <span class="text-muted small" id="searchInfo"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="productsContainer" class="row">
                    <div class="col-12">
                        <p class="text-muted">Selecciona una categoría para ver los productos</p>
                    </div>
                </div>

                <!-- Paginación -->
                <div id="paginationContainer" class="d-flex justify-content-center mt-4" style="display: none;">
                    <nav aria-label="Navegación de productos">
                        <ul class="pagination pagination-sm" id="paginationList">
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Modal para detalles del producto -->
        <div class="modal fade" id="productModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="productModalTitle">Detalles del Producto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="productModalBody">
                        <div class="loading">
                            <i class="fas fa-spinner fa-spin"></i> Cargando...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = './api/v1';

        // Variables globales para paginación y filtros
        let currentPage = 1;
        let currentCategoryId = null;
        let currentCategoryName = '';
        let totalPages = 1;
        let currentSearch = '';
        let isSearchMode = false;

        // Traducciones de billing plans a español basadas en TermDuration y BillingPlan
        const billingPlanTranslations = {
            'Monthly': 'Mensual',
            'Annual': 'Anual',
            'OneTime': 'Perpetuo',
            'none': 'Ninguno'
        };

        // Traducciones más específicas por combinación TermDuration + BillingPlan
        const periodTranslations = {
            // P1M - Períodos mensuales
            'P1M_Monthly': '1 Mes',
            'P1M_OneTime': 'Un solo pago (Perpetuo)',

            // P1Y - Períodos anuales
            'P1Y_Annual': '12 meses, pago anual',
            'P1Y_Monthly': '12 meses, pago mensual',

            // P3Y - Períodos trienales
            'P3Y_Annual': '3 años, pago anual',
            'P3Y_Monthly': '3 años, pago mensual',

            // PN - Sin período definido
            'PN_Monthly': 'Mensual',
            'PN_none': 'Ninguno',

            // ONE_TIME - Un solo pago
            'ONE_TIME_Monthly': 'Un solo pago (Perpetuo)',

            // Casos especiales sin duración específica
            '_OneTime': 'Perpetuo',
            'none_OneTime': 'Perpetuo',
            '_Monthly': 'Mensual'
        };        // Función para traducir billing plan con más precisión
        function translateBillingPlan(billingPlan, termDuration = null) {
            // Si tenemos TermDuration, intentar traducción específica
            if (termDuration) {
                const specificKey = `${termDuration}_${billingPlan}`;
                if (periodTranslations[specificKey]) {
                    return periodTranslations[specificKey];
                }
            }

            // Fallback a traducción básica
            return billingPlanTranslations[billingPlan] || billingPlan;
        }

        // Función para hacer peticiones AJAX
        async function apiRequest(endpoint) {
            try {
                console.log('Making API request to:', `${API_BASE}${endpoint}`);
                const response = await fetch(`${API_BASE}${endpoint}`, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                const jsonResponse = await response.json();
                console.log('Raw API response:', jsonResponse);
                return jsonResponse;
            } catch (error) {
                console.error('Error en API:', error);
                return { success: false, message: 'Error de conexión' };
            }
        }

        // Cargar categorías
        async function loadCategories() {
            const container = document.getElementById('categoriesContainer');
            const result = await apiRequest('/categories');

            if (result.success && result.data && result.data.length > 0) {
                container.innerHTML = '';
                result.data.forEach(category => {
                    container.innerHTML += `
                        <div class="col-md-3 mb-3">
                            <div class="card category-card h-100" onclick="loadProducts(${category.id}, '${category.name}')">
                                <div class="card-body text-center">
                                    <i class="fas fa-folder fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">${category.name}</h5>
                                    <p class="card-text">${category.description}</p>
                                    <small class="text-muted">${category.active_products_count} productos</small>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                container.innerHTML = '<div class="col-12"><div class="alert alert-danger">Error cargando categorías: ' + (result.message || 'Error desconocido') + '</div></div>';
            }
        }

        // Cargar productos por categoría
        async function loadProducts(categoryId, categoryName, page = 1, resetFilters = true) {
            currentCategoryId = categoryId;
            currentCategoryName = categoryName;
            currentPage = page;
            isSearchMode = false;

            if (resetFilters) {
                document.getElementById('searchInput').value = '';
                currentSearch = '';
            }

            document.getElementById('categoryName').textContent = `- ${categoryName}`;
            document.getElementById('productFilters').style.display = 'block';

            await loadProductsWithFilters();
        }

        // Función nueva para cargar productos con filtros y paginación
        async function loadProductsWithFilters() {
            const container = document.getElementById('productsContainer');
            container.innerHTML = '<div class="col-12"><div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando productos...</div></div>';

            // Construir parámetros de consulta
            let params = new URLSearchParams();

            if (isSearchMode && currentSearch) {
                params.append('search', currentSearch);
            } else if (currentCategoryId) {
                params.append('category_id', currentCategoryId);
            }

            params.append('page', currentPage);
            params.append('per_page', document.getElementById('perPage').value);
            params.append('sort_by', document.getElementById('sortBy').value);
            params.append('sort_order', document.getElementById('sortOrder').value);

            const result = await apiRequest(`/products?${params.toString()}`);

            // Debug: Mostrar la respuesta completa
            console.log('API Response:', result);
            console.log('result.success:', result.success);
            console.log('result.data:', result.data);

            if (result.success && result.data) {
                container.innerHTML = '';

                // Actualizar información de paginación
                if (result.pagination) {
                    totalPages = result.pagination.total_pages;
                    updatePaginationInfo(result.pagination);
                    renderPagination(result.pagination);
                } else {
                    // Fallback si no hay información de paginación
                    totalPages = 1;
                    updateTotalProducts(result.data.length);
                }

                if (result.data.length > 0) {
                    result.data.forEach(product => {
                        console.log('Processing product:', product.product_id, 'with', product.variants.length, 'variants');

                        // Crear opciones de variantes desde las variantes que vienen del backend
                        let variantOptions = '';
                        if (product.variants && product.variants.length > 0) {
                            product.variants.forEach((variant, index) => {
                                const translatedPlan = translateBillingPlan(variant.billing_plan, variant.term_duration);
                                const selected = index === 0 ? 'selected' : '';
                                variantOptions += `<option value="${variant.id}" data-price="${variant.unit_price}" data-erp-price="${variant.erp_price}" ${selected}>
                                    ${translatedPlan} - $${variant.unit_price} USD
                                </option>`;
                                console.log('Added variant option:', translatedPlan, variant.unit_price);
                            });
                        } else {
                            // Fallback si no hay variantes
                            variantOptions = `<option value="${product.id}" data-price="0" data-erp-price="0">
                                Sin variantes - $0 USD
                            </option>`;
                        }

                        console.log('Final variantOptions HTML:', variantOptions);

                        // Obtener el precio inicial (primera variante)
                        const initialVariant = product.variants && product.variants.length > 0 ? product.variants[0] : null;
                        const initialPrice = initialVariant ? initialVariant.unit_price : '0';
                        const initialPlan = initialVariant ? translateBillingPlan(initialVariant.billing_plan, initialVariant.term_duration) : 'N/A';

                        container.innerHTML += `
                            <div class="col-md-4 mb-3">
                                <div class="card product-card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">${product.title}</h6>
                                        <p class="card-text small">${product.description.substring(0, 100)}...</p>

                                        <!-- Información del producto -->
                                        <div class="mb-3">
                                            <div class="row">
                                                <div class="col-12">
                                                    <strong>SKU:</strong> ${product.sku_id || 'undefined'}<br>
                                                    <strong>ID:</strong> ${product.id_field || 'undefined'}
                                                </div>
                                            </div>
                                            <div class="row mt-2">
                                                <div class="col-6">
                                                    <small class="text-muted">Segmento:</small><br>
                                                    <span class="badge bg-secondary">${product.details ? product.details.segment : 'undefined'}</span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Mercado:</small><br>
                                                    <span class="badge bg-info">${product.details ? product.details.market : 'undefined'}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Selector de períodos reales -->
                                        <div class="mb-3">
                                            <label class="form-label small"><strong>Período de facturación:</strong></label>
                                            <select class="form-select form-select-sm" onchange="updateRealPrice(this, '${product.product_id}')" id="period-${product.product_id}">
                                                ${variantOptions}
                                            </select>
                                        </div>

                                        <!-- Cantidad -->
                                        <div class="mb-3">
                                            <div class="row">
                                                <div class="col-8">
                                                    <label class="form-label small"><strong>Cantidad:</strong></label>
                                                    <input type="number" class="form-control form-control-sm" id="qty-${product.product_id}" value="1" min="1" max="999" onchange="updateRealPrice(document.getElementById('period-${product.product_id}'), '${product.product_id}')">
                                                </div>
                                                <div class="col-4 d-flex align-items-end">
                                                    <span class="badge bg-light text-dark">Licencias</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Precio dinámico -->
                                        <div class="mb-3">
                                            <div class="price-display" id="price-${product.product_id}">
                                                <small class="text-muted d-block">1 licencia por</small>
                                                <span class="fs-5 fw-bold text-success">$${initialPrice} USD</span>
                                                <small class="text-muted d-block">${initialPlan}</small>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted">${product.publisher}</small>
                                            <button class="btn btn-outline-primary btn-sm" onclick="showProductDetails('${product.id_field}')">
                                                <i class="fas fa-eye"></i> Ver detalles
                                            </button>
                                        </div>

                                        <div class="d-grid">
                                            <button class="btn btn-primary btn-sm" onclick="addToCartFromList('${product.product_id}')">
                                                <i class="fas fa-shopping-cart"></i> Agregar al carrito
                                            </button>
                                        </div>

                                        <div class="mt-2">
                                            <span class="badge bg-outline-secondary small">${product.sku_id}_${product.id_field}</span>
                                            <span class="badge bg-secondary small ms-1">${product.variants.length} variante${product.variants.length !== 1 ? 's' : ''}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    const message = isSearchMode ?
                        `No se encontraron productos para la búsqueda "${currentSearch}"` :
                        'No se encontraron productos para esta categoría';
                    container.innerHTML = `<div class="col-12"><div class="alert alert-warning">${message}</div></div>`;
                }
            } else {
                container.innerHTML = '<div class="col-12"><div class="alert alert-danger">Error cargando productos: ' + (result.message || 'Error desconocido') + '</div></div>';
            }
        }

        // Función para actualizar precio basado en variante seleccionada REAL
        function updateRealPrice(selectElement, productId) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const billingPlanOriginal = selectedOption.textContent.split(' - ')[0]; // Extraer el período traducido

            const quantity = parseInt(document.getElementById(`qty-${productId}`).value) || 1;
            const totalPrice = (parseFloat(price) * quantity).toFixed(2);

            const priceDisplay = document.getElementById(`price-${productId}`);

            priceDisplay.innerHTML = `
                <small class="text-muted d-block">${quantity} ${quantity === 1 ? 'licencia' : 'licencias'} por</small>
                <span class="fs-5 fw-bold text-success">$${totalPrice} USD</span>
                <small class="text-muted d-block">${billingPlanOriginal}</small>
            `;
        }

        // Función para actualizar precio en el modal
        function updateModalPrice(selectElement, productId) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const billingPlanOriginal = selectedOption.textContent.split(' - ')[0]; // Extraer el período traducido

            const quantity = parseInt(document.getElementById(`modal-qty-${productId}`).value) || 1;
            const totalPrice = (parseFloat(price) * quantity).toFixed(2);

            const priceDisplay = document.getElementById(`modal-price-${productId}`);

            priceDisplay.innerHTML = `
                <small class="text-muted d-block">${quantity} ${quantity === 1 ? 'licencia' : 'licencias'}</small>
                <span class="fs-4 fw-bold text-success">$${totalPrice} USD</span>
                <small class="text-muted d-block">${billingPlanOriginal}</small>
            `;
        }

        // Función para mostrar detalles del producto en modal
        async function showProductDetails(productId) {
            const modal = new bootstrap.Modal(document.getElementById('productModal'));
            const modalBody = document.getElementById('productModalBody');

            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';
            modal.show();

            const result = await apiRequest(`/products/by-product-id/${productId}`);
            console.log('Modal API Response:', result); // Debug

            if (result.success && result.data) {
                const product = result.data;
                console.log('Product data with variants:', product); // Debug

                // Crear selector de variantes si existen múltiples
                let variantOptions = '';
                if (product.variants && product.variants.length > 0) {
                    product.variants.forEach((variant, index) => {
                        const translatedPlan = translateBillingPlan(variant.billing_plan, variant.term_duration);
                        const selected = index === 0 ? 'selected' : '';
                        variantOptions += `<option value="${variant.id}" data-price="${variant.unit_price}" data-erp-price="${variant.erp_price}" ${selected}>
                            ${translatedPlan} - $${variant.unit_price} USD
                        </option>`;
                    });
                } else {
                    // Fallback si no hay variantes
                    const billingPlan = product.details ? product.details.billing_plan : 'Monthly';
                    const translatedPlan = translateBillingPlan(billingPlan);
                    variantOptions = `<option value="${product.id}" data-price="0" data-erp-price="0" selected>
                        ${translatedPlan} - Sin precio USD
                    </option>`;
                }

                const initialVariant = product.variants && product.variants.length > 0 ? product.variants[0] : null;
                const initialPrice = initialVariant ? initialVariant.unit_price : '0';
                const initialPlan = initialVariant ? translateBillingPlan(initialVariant.billing_plan, initialVariant.term_duration) : 'N/A';

                const periodSelector = `
                    <div class="mb-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-shopping-cart"></i> Configurar compra</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label"><strong>Período de facturación:</strong></label>
                                        <select class="form-select" onchange="updateModalPrice(this, '${product.product_id}')" id="modal-period-${product.product_id}">
                                            ${variantOptions}
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label"><strong>Cantidad:</strong></label>
                                        <input type="number" class="form-control" id="modal-qty-${product.product_id}" value="1" min="1" max="999" onchange="updateModalPrice(document.getElementById('modal-period-${product.product_id}'), '${product.product_id}')">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <div class="text-center w-100">
                                            <div class="price-display" id="modal-price-${product.product_id}">
                                                <small class="text-muted d-block">Total</small>
                                                <span class="fs-4 fw-bold text-success">$${initialPrice}</span>
                                                <small class="text-muted d-block">${initialPlan}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button class="btn btn-primary w-100" onclick="addToCartFromModal('${product.product_id}')">
                                            <i class="fas fa-shopping-cart"></i> Agregar al carrito
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Crear tabla de variantes
                let variantsTable = '';
                if (product.variants && product.variants.length > 1) {
                    variantsTable = `
                        <div class="mb-3">
                            <strong>Todas las variantes disponibles:</strong>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Período</th>
                                            <th>Precio Unitario</th>
                                            <th>Precio ERP</th>
                                            <th>SKU ID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${product.variants.map(variant => `
                                            <tr>
                                                <td><span class="badge bg-primary">${translateBillingPlan(variant.billing_plan, variant.term_duration)}</span></td>
                                                <td class="fw-bold text-success">$${variant.unit_price} USD</td>
                                                <td class="text-muted">${variant.erp_price && variant.erp_price !== variant.unit_price ? '$' + variant.erp_price + ' USD' : 'N/A'}</td>
                                                <td><code class="small">${variant.sku_id}</code></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }

                const productInfo = `
                    <div class="mb-3">
                        <strong>Información del producto:</strong>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm">
                                <tr>
                                    <th>Product ID:</th>
                                    <td><code class="small">${product.product_id}</code></td>
                                </tr>
                                <tr>
                                    <th>SKU ID:</th>
                                    <td><code class="small">${product.sku_id}</code></td>
                                </tr>
                                <tr>
                                    <th>ID Field:</th>
                                    <td><code class="small">${product.id_field}</code></td>
                                </tr>
                                ${product.details ? `
                                <tr>
                                    <th>Segmento:</th>
                                    <td><span class="badge bg-secondary">${product.details.segment}</span></td>
                                </tr>
                                <tr>
                                    <th>Mercado:</th>
                                    <td><span class="badge bg-info">${product.details.market}</span></td>
                                </tr>
                                ` : ''}
                                <tr>
                                    <th>Total de variantes:</th>
                                    <td><span class="badge bg-success">${product.variants ? product.variants.length : 0}</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                `;

                modalBody.innerHTML = `
                    <div class="row">
                        <div class="col-md-12">
                            <h4>${product.title}</h4>
                            <p class="text-muted mb-3">Publisher: ${product.publisher}</p>

                            <div class="mb-3">
                                <strong>Descripción:</strong>
                                <p>${product.description}</p>
                            </div>

                            ${periodSelector}

                            ${productInfo}

                            ${variantsTable}

                            <div class="alert alert-info">
                                <strong>Información:</strong> Estos son datos reales de productos Microsoft obtenidos desde la API del marketplace.
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('productModalTitle').textContent = product.title;
            } else {
                modalBody.innerHTML = '<div class="alert alert-danger">Error cargando detalles del producto</div>';
            }
        }

        // Funciones de búsqueda y paginación
        async function searchProducts() {
            const searchTerm = document.getElementById('searchInput').value.trim();

            if (searchTerm.length === 0) {
                alert('Por favor ingresa un término de búsqueda');
                return;
            }

            currentSearch = searchTerm;
            isSearchMode = true;
            currentPage = 1;
            currentCategoryId = null;

            document.getElementById('categoryName').textContent = `- Resultados de búsqueda: "${searchTerm}"`;
            document.getElementById('productFilters').style.display = 'block';
            document.getElementById('searchInfo').textContent = `Búsqueda: "${searchTerm}"`;

            await loadProductsWithFilters();
        }

        function clearSearch() {
            document.getElementById('searchInput').value = '';
            currentSearch = '';
            isSearchMode = false;
            currentPage = 1;

            document.getElementById('categoryName').textContent = '';
            document.getElementById('productFilters').style.display = 'none';
            document.getElementById('searchInfo').textContent = '';
            document.getElementById('paginationContainer').style.display = 'none';

            const container = document.getElementById('productsContainer');
            container.innerHTML = '<div class="col-12"><p class="text-muted">Selecciona una categoría para ver los productos</p></div>';
        }

        function goToPage(page) {
            if (page >= 1 && page <= totalPages && page !== currentPage) {
                currentPage = page;
                loadProductsWithFilters();
            }
        }

        function updatePaginationInfo(pagination) {
            const totalProductsElement = document.getElementById('totalProducts');
            totalProductsElement.textContent = `${pagination.total} productos`;

            const from = pagination.from || 1;
            const to = pagination.to || pagination.total;
            const currentInfo = `Página ${pagination.current_page} de ${pagination.total_pages} - Mostrando ${from} a ${to} de ${pagination.total}`;

            if (isSearchMode) {
                document.getElementById('searchInfo').textContent = `Búsqueda: "${currentSearch}" - ${currentInfo}`;
            } else {
                document.getElementById('searchInfo').textContent = currentInfo;
            }
        }        function updateTotalProducts(count) {
            document.getElementById('totalProducts').textContent = `${count} productos`;
            document.getElementById('searchInfo').textContent = `Mostrando ${count} productos`;
        }

        function renderPagination(pagination) {
            const paginationContainer = document.getElementById('paginationContainer');
            const paginationList = document.getElementById('paginationList');

            if (pagination.total_pages <= 1) {
                paginationContainer.style.display = 'none';
                return;
            }

            paginationContainer.style.display = 'block';
            paginationList.innerHTML = '';

            // Botón anterior
            const prevClass = pagination.current_page === 1 ? 'disabled' : '';
            paginationList.innerHTML += `
                <li class="page-item ${prevClass}">
                    <a class="page-link" href="#" onclick="goToPage(${pagination.current_page - 1})" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            `;

            // Números de página
            const current = pagination.current_page;
            const total = pagination.total_pages;
            let startPage = Math.max(1, current - 2);
            let endPage = Math.min(total, current + 2);

            // Ajustar para mostrar siempre 5 páginas si es posible
            if (endPage - startPage < 4) {
                if (startPage === 1) {
                    endPage = Math.min(total, startPage + 4);
                } else {
                    startPage = Math.max(1, endPage - 4);
                }
            }

            // Primera página si no está visible
            if (startPage > 1) {
                paginationList.innerHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="goToPage(1)">1</a>
                    </li>
                `;
                if (startPage > 2) {
                    paginationList.innerHTML += `
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    `;
                }
            }

            // Páginas principales
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === current ? 'active' : '';
                paginationList.innerHTML += `
                    <li class="page-item ${activeClass}">
                        <a class="page-link" href="#" onclick="goToPage(${i})">${i}</a>
                    </li>
                `;
            }

            // Última página si no está visible
            if (endPage < total) {
                if (endPage < total - 1) {
                    paginationList.innerHTML += `
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    `;
                }
                paginationList.innerHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="goToPage(${total})">${total}</a>
                    </li>
                `;
            }

            // Botón siguiente
            const nextClass = pagination.current_page === pagination.total_pages ? 'disabled' : '';
            paginationList.innerHTML += `
                <li class="page-item ${nextClass}">
                    <a class="page-link" href="#" onclick="goToPage(${pagination.current_page + 1})" aria-label="Siguiente">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            `;
        }

        // Funciones de carrito (placeholder)
        function addToCartFromList(productId) {
            alert(`Producto ${productId} agregado al carrito desde la lista`);
        }

        function addToCartFromModal(productId) {
            alert(`Producto ${productId} agregado al carrito desde el modal`);
        }

        // Cargar categorías al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
        });
    </script>
</body>
</html>
