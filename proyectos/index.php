<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Gestión comercial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="app.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h5 style="display:flex; align-items:center; gap:8px;">
                     Gestión comercial
                     <a href="https://herramientasvirtuales.com" target="_blank" rel="noopener noreferrer" style="display: flex; align-items: center; justify-content: flex-end;">
                    <img src="hv.png" alt="HV" style="width: 50px; height: 32px; object-fit: contain;">
                </a>
                </h5>
                
            </div>
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-section="crear-cotizacion">
                    <i class="bi bi-plus-circle-fill"></i> Crear Cotización
                </a>
                <a href="#" class="nav-item" data-section="cotizaciones">
                    <i class="bi bi-file-earmark-text"></i> Cotizaciones
                </a>
                <div class="nav-divider"></div>
                <a href="#" class="nav-item" data-section="clientes">
                    <i class="bi bi-people"></i> Clientes
                </a>
                <a href="#" class="nav-item" data-section="productos">
                    <i class="bi bi-box-seam"></i> Productos
                </a>
                <a href="#" class="nav-item" data-section="vendedores">
                    <i class="bi bi-person-badge"></i> Vendedores
                </a>
                <div class="nav-divider"></div>
                <a href="#" class="nav-item" data-section="empresa">
                    <i class="bi bi-building"></i> Configuración Empresa
                </a>
                <a href="#" class="nav-item" data-section="ayuda">
                    <i class="bi bi-question-circle"></i> Manual de Usuario
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Crear Cotización Section -->
            <section id="crear-cotizacion-section" class="content-section active">
                <div class="cotizador-wrapper">
                    <div class="main-container">
                        <!-- Header -->
                        <div class="header-section">
                            <h1 class="main-title">
                                <span id="docTipo">Documento</span>
                                <span class="version" id="docNumero"></span>
                            </h1>
                            <!-- Actions Bar -->
                            <div class="actions-bar">
                                <button type="button" class="btn btn-primary-custom" id="btnGuardar">
                                    <i class="bi bi-save"></i> <span class="btn-label">Guardar</span>
                                </button>
                                <button type="button" class="btn btn-danger-custom" id="btnCerrar">
                                    <i class="bi bi-x-circle"></i> <span class="btn-label">Limpiar</span>
                                </button>
                            </div>
                        </div>

                        <!-- Form Section -->
                        <div class="form-container">
                            <div class="row g-3">
                                <!-- Días de V. -->
                                <div class="col-md-2">
                                    <label class="form-label">Días de V.</label>
                                    <input type="number" class="form-control" id="diasValidez" value="0">
                                </div>

                                <!-- Forma de Pago -->
                                <div class="col-md-3">
                                    <label class="form-label">Forma de Pago</label>
                                    <select class="form-select" id="formaPago">
                                        <option>[1] - Contado</option>
                                        <option>[2] - Crédito</option>
                                    </select>
                                </div>

                                <!-- Método de Pago -->
                                <div class="col-md-4">
                                    <label class="form-label">Método de Pago</label>
                                    <select class="form-select" id="metodoPago">
                                        <option>[42] - Consignación bancaria</option>
                                        <option>[10] - Efectivo</option>
                                        <option>[48] - Transferencia bancaria</option>
                                    </select>
                                </div>

                                <!-- Fecha de Generación -->
                                <div class="col-md-3">
                                    <label class="form-label">Fecha de Generación</label>
                                    <input type="date" class="form-control" id="fechaGeneracion">
                                </div>
                            </div>
   <!-- Introducción Section -->
                        <div class="introduccion-container">
                            <label class="form-label fw-bold">Introducción <span class="text-muted">(Opcional)</span></label>
                            <div class="editor-container-intro">
                                <div class="editor-toolbar" id="toolbarIntroduccion">
                                    <button type="button" class="editor-btn" data-command="bold" title="Negrita"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" class="editor-btn" data-command="italic" title="Cursiva"><i class="bi bi-type-italic"></i></button>
                                    <button type="button" class="editor-btn" data-command="underline" title="Subrayado"><i class="bi bi-type-underline"></i></button>
                                    <span class="editor-separator"></span>
                                    <button type="button" class="editor-btn" data-command="insertUnorderedList" title="Lista"><i class="bi bi-list-ul"></i></button>
                                    <button type="button" class="editor-btn" data-command="insertOrderedList" title="Lista numerada"><i class="bi bi-list-ol"></i></button>
                                    <span class="editor-separator"></span>
                                    <button type="button" class="editor-btn" data-command="createLink" title="Insertar enlace"><i class="bi bi-link-45deg"></i></button>
                                </div>
                                <div class="editor-content" id="introduccionEditor" contenteditable="true" data-placeholder="Escriba una introducción para su cotización..."></div>
                            </div>
                        </div>
                            <div class="row g-3 mt-2">
                                <!-- Cliente -->
                                <div class="col-md-4">
                                    <label class="form-label">Cliente</label>
                                    <div class="input-group" style="position: relative;">
                                        <input type="text" class="form-control" id="searchCliente" placeholder="Buscar cliente..." autocomplete="off">
                                        <button class="btn btn-add" type="button" id="addCliente" title="Crear nuevo cliente">+</button>
                                        <div id="clientesAutocomplete" class="autocomplete-dropdown"></div>
                                        <input type="hidden" id="clienteSeleccionado">
                                    </div>
                                </div>

                                <!-- Vendedor -->
                                <div class="col-md-4">
                                    <label class="form-label">Vendedor</label>
                                    <select class="form-select" id="vendedor">
                                        <option value="">[Sin asignar]</option>
                                    </select>
                                </div>

                                <!-- Añadir Producto -->
                                <div class="col-md-4">
                                    <label class="form-label">Añadir Producto</label>
                                    <div class="input-group" style="position: relative;">
                                        <input type="text" class="form-control" id="searchProducto" placeholder="Buscar por código o nombre..." autocomplete="off">
                                        <button class="btn btn-add" type="button" id="addProducto" title="Crear nuevo producto">+</button>
                                        <div id="productosAutocomplete" class="autocomplete-dropdown"></div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Toggle para mostrar/ocultar detalle de la propuesta -->
                        <div class="form-check form-switch mb-3 mt-3">
                            <input class="form-check-input" type="checkbox" id="mostrarDetalle" checked>
                            <label class="form-check-label" for="mostrarDetalle">
                                <strong>Mostrar detalle de la propuesta</strong>
                                <span class="text-muted ms-1">(tabla de costos y resumen)</span>
                            </label>
                        </div>

                        <!-- Table Section -->
                        <div class="table-container" id="detalleContainer">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Descripción</th>
                                        <th>V. Unitario</th>
                                        <th>Cantidad</th>
                                        <th>Descuento</th>
                                        <th>Impuestos</th>
                                        <th>Total</th>
                                        <th>Opciones</th>
                                    </tr>
                                </thead>
                                <tbody id="productosTable">
                                    <!-- Los productos se agregarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>

                        <div class="introduccion-container mt-4">
                            <label class="form-label fw-bold">Términos y Condiciones</label>
                            <div class="editor-container-intro">
                                <div class="editor-toolbar" id="toolbarNotas">
                                    <button type="button" class="editor-btn" data-command="bold" title="Negrita"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" class="editor-btn" data-command="italic" title="Cursiva"><i class="bi bi-type-italic"></i></button>
                                    <button type="button" class="editor-btn" data-command="underline" title="Subrayado"><i class="bi bi-type-underline"></i></button>
                                    <span class="editor-separator"></span>
                                    <button type="button" class="editor-btn" data-command="insertUnorderedList" title="Lista"><i class="bi bi-list-ul"></i></button>
                                    <button type="button" class="editor-btn" data-command="insertOrderedList" title="Lista numerada"><i class="bi bi-list-ol"></i></button>
                                    <span class="editor-separator"></span>
                                    <button type="button" class="editor-btn" data-command="createLink" title="Insertar enlace"><i class="bi bi-link-45deg"></i></button>
                                </div>
                                <div class="editor-content" contenteditable="true" id="notasEditor" data-placeholder="Escriba los términos y condiciones...">
                                    <p><strong>Términos y Condiciones:</strong></p>
                                </div>
                            </div>
                        </div>

                        

                        <!-- Summary Section -->
                        <div class="summary-section" id="resumenContainer">
                            <div class="summary-card">
                                <div class="summary-label">Moneda</div>
                                <select class="form-select summary-select" id="moneda">
                                    <option>[COP] - Peso colombiano</option>
                                    <option>[USD] - Dólar estadounidense</option>
                                    <option>[EUR] - Euro</option>
                                </select>
                            </div>
                            <div class="summary-card">
                                <div class="row">
                                    <div class="col-6 summary-label">Subtotal</div>
                                    <div class="col-6 summary-value text-end" id="subtotal">$ 0.00</div>
                                </div>
                                <div class="row">
                                    <div class="col-6 summary-label">- Descuento</div>
                                    <div class="col-6 summary-value text-end" id="descuento">$ 0.00</div>
                                </div>
                                <div class="row">
                                    <div class="col-6 summary-label">Base Gravable</div>
                                    <div class="col-6 summary-value text-end" id="baseGravable">$ 0.00</div>
                                </div>
                                <div class="row">
                                    <div class="col-6 summary-label">+ Impuestos</div>
                                    <div class="col-6 summary-value text-end" id="impuestos">$ 0.00</div>
                                </div>
                                <div class="row">
                                    <div class="col-6 summary-label">- Retenciones</div>
                                    <div class="col-6 summary-value text-end" id="retenciones">$ 0.00</div>
                                </div>
                                <div class="row summary-total">
                                    <div class="col-6 summary-label">Total Neto</div>
                                    <div class="col-6 summary-value text-end" id="totalNeto">$ 0.00</div>
                                </div>
                            </div>
                        </div>

                        
                    </div>
                </div>

                <!-- Modal para agregar cliente desde cotizador -->
                <div class="modal fade" id="clienteModalCotizador" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Agregar Cliente</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Razón Social *</label>
                                        <input type="text" class="form-control" id="newCliente_razonSocial" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">NIT *</label>
                                        <input type="text" class="form-control" id="newCliente_nit" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Ciudad *</label>
                                        <input type="text" class="form-control" id="newCliente_ciudad" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Dirección *</label>
                                        <input type="text" class="form-control" id="newCliente_direccion" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Nombre del Contacto</label>
                                        <input type="text" class="form-control" id="newCliente_contacto">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="newCliente_telefono">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Correo 1</label>
                                        <input type="email" class="form-control" id="newCliente_correo1">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Correo 2</label>
                                        <input type="email" class="form-control" id="newCliente_correo2">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="btnGuardarNuevoCliente">Guardar Cliente</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal para agregar producto desde cotizador -->
                <div class="modal fade" id="productoModalCotizador" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Agregar Producto</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Código *</label>
                                        <input type="text" class="form-control" id="modalCodigo" required>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">Nombre del Producto *</label>
                                        <input type="text" class="form-control" id="modalProducto" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Descripción</label>
                                        <input type="text" class="form-control" id="modalDescripcion">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Valor Unitario *</label>
                                        <input type="number" class="form-control" id="modalVUnitario" step="0.01" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Cantidad</label>
                                        <input type="number" class="form-control" id="modalCantidad" value="1">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Descuento</label>
                                        <div class="input-group">
                                            <select class="form-select" id="modalDescuentoTipo" style="max-width:64px">
                                                <option value="%">%</option>
                                                <option value="$">$</option>
                                            </select>
                                            <input type="number" class="form-control" id="modalDescuento" value="0" step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Impuesto (%)</label>
                                        <input type="number" class="form-control" id="modalImpuesto" value="0" step="0.01">
                                    </div>
                                    <div class="col-md-12 mt-3">
                                        <label class="form-label">Enlace a Detalles <span class="text-muted">(Opcional)</span></label>
                                        <input type="url" class="form-control" id="modalEnlaceDetalles" placeholder="https://ejemplo.com/producto">
                                        <small class="text-muted">URL con más información del producto. El nombre será clickeable en la cotización.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="btnAgregarProducto">Agregar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Empresa Section -->
            <section id="empresa-section" class="content-section">
                <div class="section-header">
                    <h1>Información de la Empresa</h1>
                    <p>Configure los datos de su empresa que aparecerán en las cotizaciones</p>
                </div>

                <div class="card">
                    <form id="empresaForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Razón Social *</label>
                                <input type="text" class="form-control" id="emp_razonSocial" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">NIT *</label>
                                <input type="text" class="form-control" id="emp_nit" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ciudad *</label>
                                <input type="text" class="form-control" id="emp_ciudad" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dirección *</label>
                                <input type="text" class="form-control" id="emp_direccion" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Página Web</label>
                                <input type="url" class="form-control" id="emp_web" placeholder="https://ejemplo.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nombre del Contacto</label>
                                <input type="text" class="form-control" id="emp_contacto">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="emp_telefono">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Correo 1 *</label>
                                <input type="email" class="form-control" id="emp_correo1" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Color de la Interfaz</label>
                                <input type="color" class="form-control" id="emp_color" value="#3498db">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Logo (Base64)</label>
                                <input type="file" class="form-control" id="emp_logo" accept="image/*">
                                <small class="text-muted">Será convertido a Base64</small>
                            </div>
                            <div class="col-md-6" id="logoPreview">
                                <label class="form-label">Vista Previa del Logo</label>
                                <div class="logo-preview-box">
                                    <img id="logoPreviewImg" src="" alt="Logo Preview">
                                </div>
                            </div>

                            <!-- Configuración de Documentos -->
                            <div class="col-md-12 mt-4">
                                <h5 class="mb-3">Configuración de Documentos</h5>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo de Documento *</label>
                                <select class="form-select" id="emp_tipoDocumento" required>
                                    <option value="Cotización">Cotización</option>
                                    <option value="Presupuesto">Presupuesto</option>
                                    <option value="Propuesta">Propuesta</option>
                                    <option value="Oferta Comercial">Oferta Comercial</option>
                                    <option value="Proforma">Proforma</option>
                                    <option value="Factura">Factura</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Prefijo del Documento *</label>
                                <input type="text" class="form-control" id="emp_prefijo" placeholder="Ej: COT" required>
                                <small class="text-muted">Se usará como COT-001</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Número Inicial *</label>
                                <input type="number" class="form-control" id="emp_numeroInicial" value="1" min="1" required>
                                <small class="text-muted">Número consecutivo inicial</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Próximo número</label>
                                <input type="text" class="form-control" id="emp_proximoNumero" readonly>
                                <small class="text-muted">Calculado según prefijo y último número usado</small>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Términos y Condiciones</label>
                                <textarea class="form-control" id="emp_terminos" rows="4" placeholder="Ingrese los términos y condiciones que aparecerán en los documentos..."></textarea>
                                <small class="text-muted">Estos términos aparecerán al final de cada documento</small>
                            </div>
                            
                            <!-- Carta de Presentación -->
                            <div class="col-md-12 mt-4">
                                <h5 class="mb-3">Carta de Presentación</h5>
                                                                <small class="text-muted d-block mb-2">Esta carta aparecerá en una página separada antes de la cotización</small>

                            </div>
                            <div class="col-md-12">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="emp_habilitarCarta" checked>
                                    <label class="form-check-label" for="emp_habilitarCarta">
                                        <strong>Habilitar Carta de Presentación</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Título de la Carta <span class="text-muted">(Opcional)</span></label>
                                <input type="text" class="form-control" id="emp_tituloCarta" placeholder="Ej: Carta de Presentación, Propuesta Comercial, etc.">
                            </div>
                            <div class="col-md-12 mt-3">
                                <label class="form-label">Contenido de la Carta de Presentación</label>
                                <div class="editor-container">
                                    <div class="editor-toolbar">
                                        <button type="button" class="editor-btn" data-command="bold" data-editor="cartaPresentacion"><strong>B</strong></button>
                                        <button type="button" class="editor-btn" data-command="italic" data-editor="cartaPresentacion"><em>I</em></button>
                                        <button type="button" class="editor-btn" data-command="underline" data-editor="cartaPresentacion"><u>U</u></button>
                                        <span class="toolbar-separator">|</span>
                                        <select class="editor-select" id="fontSizeCarta" data-editor="cartaPresentacion">
                                            <option value="">Tamaño</option>
                                            <option value="1">Pequeño</option>
                                            <option value="3">Normal</option>
                                            <option value="5">Grande</option>
                                            <option value="7">Muy Grande</option>
                                        </select>
                                        <span class="toolbar-separator">|</span>
                                        <button type="button" class="editor-btn" data-command="insertUnorderedList" data-editor="cartaPresentacion">•</button>
                                        <button type="button" class="editor-btn" data-command="insertOrderedList" data-editor="cartaPresentacion">1.</button>
                                        <span class="toolbar-separator">|</span>
                                        <button type="button" class="editor-btn" data-command="justifyLeft" data-editor="cartaPresentacion">
                                            <i class="bi bi-text-left"></i>
                                        </button>
                                        <button type="button" class="editor-btn" data-command="justifyCenter" data-editor="cartaPresentacion">
                                            <i class="bi bi-text-center"></i>
                                        </button>
                                        <button type="button" class="editor-btn" data-command="justifyRight" data-editor="cartaPresentacion">
                                            <i class="bi bi-text-right"></i>
                                        </button>
                                        <span class="toolbar-separator">|</span>
                                        <button type="button" class="editor-btn" id="btnLinkCarta" data-editor="cartaPresentacion" title="Insertar enlace">
                                            <i class="bi bi-link-45deg"></i>
                                        </button>
                                        <span class="toolbar-separator">|</span>
                                        <button type="button" class="editor-btn" id="btnImagenCarta" title="Insertar imagen">
                                            <i class="bi bi-image"></i>
                                        </button>
                                        <input type="file" id="inputImagenCarta" accept="image/*" style="display:none;">
                                    </div>
                                    <div class="editor-content" contenteditable="true" id="emp_cartaPresentacion" style="min-height: 150px;">
                                        <p>Estimado cliente,</p>
                                        <p>Es un placer presentarle nuestra propuesta comercial...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Guardar Información</button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Clientes Section -->
            <section id="clientes-section" class="content-section">
                <div class="section-header">
                    <h1>Gestión de Clientes</h1>
                    <button class="btn btn-primary" id="btnNuevoCliente">
                        <i class="bi bi-plus-circle"></i> Nuevo Cliente
                    </button>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Razón Social</th>
                                    <th>NIT</th>
                                    <th>Ciudad</th>
                                    <th>Contacto</th>
                                    <th>Teléfono</th>
                                    <th>Correo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="clientesTable"></tbody>
                        </table>
                    </div>
                    <!-- Paginación -->
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Mostrando <span id="clientesRangoInicio">0</span>-<span id="clientesRangoFin">0</span> de <span id="clientesTotal">0</span>
                        </div>
                        <div class="pagination-controls">
                            <select id="clientesPerPage" class="form-select form-select-sm" style="width: 70px;">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <div class="pagination-buttons">
                                <button class="btn btn-sm btn-outline-secondary" id="clientesPrevPage" disabled>
                                    <i class="bi bi-chevron-left"></i> Anterior
                                </button>
                                <span class="pagination-current">Página <span id="clientesPaginaActual">1</span> de <span id="clientesTotalPaginas">1</span></span>
                                <button class="btn btn-sm btn-outline-secondary" id="clientesNextPage">
                                    Siguiente <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Productos Section -->
            <section id="productos-section" class="content-section">
                <div class="section-header">
                    <h1>Gestión de Productos</h1>
                    <button class="btn btn-primary" id="btnNuevoProducto">
                        <i class="bi bi-plus-circle"></i> Nuevo Producto
                    </button>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Valor Unitario</th>
                                    <th>Cantidad</th>
                                    <th>Descuento</th>
                                    <th>Impuesto %</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productosAdminTable"></tbody>
                        </table>
                    </div>
                    <!-- Paginación -->
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Mostrando <span id="productosRangoInicio">0</span>-<span id="productosRangoFin">0</span> de <span id="productosTotal">0</span>
                        </div>
                        <div class="pagination-controls">
                            <select id="productosPerPage" class="form-select form-select-sm" style="width: 70px;">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <div class="pagination-buttons">
                                <button class="btn btn-sm btn-outline-secondary" id="productosPrevPage" disabled>
                                    <i class="bi bi-chevron-left"></i> Anterior
                                </button>
                                <span class="pagination-current">Página <span id="productosPaginaActual">1</span> de <span id="productosTotalPaginas">1</span></span>
                                <button class="btn btn-sm btn-outline-secondary" id="productosNextPage">
                                    Siguiente <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Vendedores Section -->
            <section id="vendedores-section" class="content-section">
                <div class="section-header">
                    <h1>Gestión de Vendedores</h1>
                    <button class="btn btn-primary" id="btnNuevoVendedor">
                        <i class="bi bi-plus-circle"></i> Nuevo Vendedor
                    </button>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="vendedoresTable"></tbody>
                        </table>
                    </div>
                    <!-- Paginación -->
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Mostrando <span id="vendedoresRangoInicio">0</span>-<span id="vendedoresRangoFin">0</span> de <span id="vendedoresTotal">0</span>
                        </div>
                        <div class="pagination-controls">
                            <select id="vendedoresPerPage" class="form-select form-select-sm" style="width: 70px;">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <div class="pagination-buttons">
                                <button class="btn btn-sm btn-outline-secondary" id="vendedoresPrevPage" disabled>
                                    <i class="bi bi-chevron-left"></i> Anterior
                                </button>
                                <span class="pagination-current">Página <span id="vendedoresPaginaActual">1</span> de <span id="vendedoresTotalPaginas">1</span></span>
                                <button class="btn btn-sm btn-outline-secondary" id="vendedoresNextPage">
                                    Siguiente <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Cotizaciones Section -->
            <section id="cotizaciones-section" class="content-section">
                <div class="section-header">
                    <h1>Gestión de Cotizaciones</h1>
                    <button class="btn btn-primary" id="btnNuevaCotizacion">
                        <i class="bi bi-plus-circle"></i> Nueva Cotización
                    </button>
                </div>

                <!-- Filtros y Totalizadores -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="filters-container">
                        <div class="filters-row" style="display: flex; gap: 20px;">
                            <div class="filter-group" style="flex: 0 0 calc(25% - 15px);">
                                <label><i class="bi bi-calendar3"></i> Período</label>
                                <select id="filtroMes" class="form-select">
                                    <option value="todos">Todos los meses</option>
                                    <option value="mes-actual">Este mes</option>
                                    <option value="mes-anterior">Mes anterior</option>
                                    <option value="ultimos-3">Últimos 3 meses</option>
                                    <option value="ultimos-6">Últimos 6 meses</option>
                                    <option value="este-año">Este año</option>
                                </select>
                            </div>

                            <div class="filter-group" style="flex: 0 0 calc(25% - 15px);">
                                <label><i class="bi bi-person"></i> Vendedor</label>
                                <select id="filtroVendedor" class="form-select">
                                    <option value="todos">Todos los vendedores</option>
                                </select>
                            </div>

                            <div class="filter-group" style="flex: 0 0 calc(50% - 10px);">
                                <label><i class="bi bi-tag"></i> Estado</label>
                                <div class="filter-buttons">
                                    <button class="btn btn-sm btn-outline-secondary filter-btn active" data-estado="todos">Todos</button>
                                    <button class="btn btn-sm btn-outline-warning filter-btn" data-estado="pendiente">Pendientes</button>
                                    <button class="btn btn-sm btn-outline-success filter-btn" data-estado="aprobada">Aprobadas</button>
                                    <button class="btn btn-sm btn-outline-primary filter-btn" data-estado="facturada">Facturadas</button>
                                    <button class="btn btn-sm btn-outline-danger filter-btn" data-estado="rechazada">Rechazadas</button>
                                </div>
                            </div>
                        </div>

                        <!-- Totalizadores -->
                        <div class="totalizadores-row">
                            <div class="totalizador-card">
                                <div class="totalizador-icon">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                                <div class="totalizador-content">
                                    <span class="totalizador-label">Total Documentos</span>
                                    <span class="totalizador-value" id="totalDocumentos">0</span>
                                </div>
                            </div>

                            <div class="totalizador-card">
                                <div class="totalizador-icon success">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="totalizador-content">
                                    <span class="totalizador-label">Aprobadas</span>
                                    <span class="totalizador-value" id="totalAprobadas">0</span>
                                </div>
                            </div>

                            <div class="totalizador-card">
                                <div class="totalizador-icon primary">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <div class="totalizador-content">
                                    <span class="totalizador-label">Total Cotizado</span>
                                    <span class="totalizador-value" id="totalCotizado">$ 0</span>
                                </div>
                            </div>

                            <div class="totalizador-card">
                                <div class="totalizador-icon warning">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <div class="totalizador-content">
                                    <span class="totalizador-label">Tasa Conversión</span>
                                    <span class="totalizador-value" id="tasaConversion">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cotizacionesTable"></tbody>
                        </table>
                    </div>
                    <!-- Paginación -->
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Mostrando <span id="cotizacionesRangoInicio">0</span>-<span id="cotizacionesRangoFin">0</span> de <span id="cotizacionesTotal">0</span>
                        </div>
                        <div class="pagination-controls">
                            <select id="cotizacionesPerPage" class="form-select form-select-sm" style="width: 70px;">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <div class="pagination-buttons">
                                <button class="btn btn-sm btn-outline-secondary" id="cotizacionesPrevPage" disabled>
                                    <i class="bi bi-chevron-left"></i> Anterior
                                </button>
                                <span class="pagination-current">Página <span id="cotizacionesPaginaActual">1</span> de <span id="cotizacionesTotalPaginas">1</span></span>
                                <button class="btn btn-sm btn-outline-secondary" id="cotizacionesNextPage">
                                    Siguiente <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Manual de Usuario Section -->
            <section id="ayuda-section" class="content-section">
                <div class="section-header">
                    <h1><i class="bi bi-book"></i> Manual de Usuario</h1>
                    <p>Guía completa para usar el sistema de gestión comercial</p>
                </div>

                <div class="card">
                    <div class="manual-accordion">
                        <!-- Paso 1 -->
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <div class="accordion-title">
                                    <span class="step-number">1</span>
                                    <span>Configurar tu Empresa</span>
                                </div>
                                <i class="bi bi-chevron-down accordion-icon"></i>
                            </div>
                            <div class="accordion-content">
                                <p><strong>Este es el primer paso y el más importante.</strong> Configura los datos de tu empresa que aparecerán en todas las cotizaciones.</p>
                                
                                <h4>¿Cómo hacerlo?</h4>
                                <ol>
                                    <li>Haz clic en <strong>"Configuración Empresa"</strong> en el menú lateral.</li>
                                    <li>Completa la <strong>información básica</strong>:
                                        <ul>
                                            <li>Razón Social (nombre de tu empresa)</li>
                                            <li>NIT o número de identificación</li>
                                            <li>Ciudad y Dirección</li>
                                            <li>Contacto, Teléfono y Correos electrónicos</li>
                                            <li>Sitio Web (opcional)</li>
                                        </ul>
                                    </li>
                                    <li><strong>Personaliza tu marca</strong>:
                                        <ul>
                                            <li><strong>Logo:</strong> Sube el logo de tu empresa (aparecerá en los PDFs)</li>
                                            <li><strong>Color corporativo:</strong> Selecciona el color de tu marca (se aplicará a toda la interfaz)</li>
                                        </ul>
                                    </li>
                                    <li><strong>Configura tus documentos</strong>:
                                        <ul>
                                            <li><strong>Tipo de Documento:</strong> Define el nombre (Cotización, Propuesta, Presupuesto, etc.)</li>
                                            <li><strong>Prefijo:</strong> Prefijo para la numeración (ej: COT, PROP, PRE)</li>
                                            <li><strong>Número Inicial:</strong> Número consecutivo inicial (ej: 1, 100, 1000)</li>
                                            <li><strong>Términos y Condiciones:</strong> Texto que aparecerá al final de cada documento</li>
                                        </ul>
                                    </li>
                                    <li>Haz clic en <strong>"Guardar Información"</strong>.</li>
                                </ol>
                                
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i> <strong>¡Listo!</strong> Tu empresa está configurada. Ahora puedes continuar con el siguiente paso.
                                </div>
                            </div>
                        </div>

                        <!-- Paso 2 -->
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <div class="accordion-title">
                                    <span class="step-number">2</span>
                                    <span>Registrar Productos</span>
                                </div>
                                <i class="bi bi-chevron-down accordion-icon"></i>
                            </div>
                            <div class="accordion-content">
                                <p>Antes de crear cotizaciones, necesitas tener productos registrados en el sistema.</p>
                                
                                <h4>¿Cómo agregar productos?</h4>
                                <ol>
                                    <li>Ve a la sección <strong>"Productos"</strong> en el menú lateral.</li>
                                    <li>Haz clic en el botón <strong>"Nuevo Producto"</strong>.</li>
                                    <li>Completa la información del producto:
                                        <ul>
                                            <li><strong>Código:</strong> Identificador único (ej: PROD001, SRV001)</li>
                                            <li><strong>Nombre:</strong> Nombre del producto o servicio</li>
                                            <li><strong>Descripción:</strong> Detalles adicionales</li>
                                            <li><strong>Precio Unitario:</strong> Precio base del producto</li>
                                            <li><strong>Descuento (%):</strong> Descuento por defecto (opcional)</li>
                                            <li><strong>Impuesto (%):</strong> IVA u otro impuesto (ej: 19 para IVA del 19%)</li>
                                        </ul>
                                    </li>
                                    <li>Haz clic en <strong>"Guardar"</strong>.</li>
                                    <li>Repite el proceso para todos tus productos.</li>
                                </ol>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-lightbulb"></i> <strong>Tip:</strong> Usa códigos descriptivos para identificar rápidamente tus productos. Puedes editar o eliminar productos en cualquier momento.
                                </div>
                            </div>
                        </div>

                        <!-- Paso 3 -->
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <div class="accordion-title">
                                    <span class="step-number">3</span>
                                    <span>Registrar Clientes</span>
                                </div>
                                <i class="bi bi-chevron-down accordion-icon"></i>
                            </div>
                            <div class="accordion-content">
                                <p>Registra los clientes a quienes enviarás cotizaciones.</p>
                                
                                <h4>¿Cómo agregar clientes?</h4>
                                <ol>
                                    <li>Ve a la sección <strong>"Clientes"</strong> en el menú lateral.</li>
                                    <li>Haz clic en <strong>"Nuevo Cliente"</strong>.</li>
                                    <li>Completa los datos del cliente:
                                        <ul>
                                            <li><strong>Razón Social:</strong> Nombre de la empresa o persona</li>
                                            <li><strong>NIT:</strong> Número de identificación</li>
                                            <li><strong>Ciudad:</strong> Ciudad donde se encuentra</li>
                                            <li><strong>Dirección:</strong> Dirección completa</li>
                                            <li><strong>Contacto:</strong> Nombre de la persona de contacto</li>
                                            <li><strong>Teléfono:</strong> Número de contacto</li>
                                            <li><strong>Correo:</strong> Email del cliente</li>
                                        </ul>
                                    </li>
                                    <li>Haz clic en <strong>"Guardar"</strong>.</li>
                                    <li>Agrega todos los clientes que necesites.</li>
                                </ol>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-lightbulb"></i> <strong>Tip:</strong> Puedes editar la información de tus clientes en cualquier momento desde la tabla de clientes.
                                </div>
                            </div>
                        </div>

                        <!-- Paso 4 -->
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <div class="accordion-title">
                                    <span class="step-number">4</span>
                                    <span>Registrar Vendedores</span>
                                </div>
                                <i class="bi bi-chevron-down accordion-icon"></i>
                            </div>
                            <div class="accordion-content">
                                <p>Registra los vendedores que aparecerán en las cotizaciones.</p>
                                
                                <h4>¿Cómo agregar vendedores?</h4>
                                <ol>
                                    <li>Ve a <strong>"Vendedores"</strong> en el menú lateral.</li>
                                    <li>Haz clic en <strong>"Nuevo Vendedor"</strong>.</li>
                                    <li>Ingresa la información:
                                        <ul>
                                            <li><strong>Nombre:</strong> Nombre completo del vendedor</li>
                                            <li><strong>Email:</strong> Correo electrónico</li>
                                            <li><strong>Teléfono:</strong> Número de contacto</li>
                                        </ul>
                                    </li>
                                    <li>Haz clic en <strong>"Guardar"</strong>.</li>
                                    <li>Agrega todos los vendedores de tu equipo.</li>
                                </ol>
                                
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i> <strong>¡Perfecto!</strong> Ya tienes todo configurado. Ahora puedes crear tu primera cotización.
                                </div>
                            </div>
                        </div>

                        <!-- Paso 5 -->
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <div class="accordion-title">
                                    <span class="step-number">5</span>
                                    <span>Crear tu Primera Cotización</span>
                                </div>
                                <i class="bi bi-chevron-down accordion-icon"></i>
                            </div>
                            <div class="accordion-content">
                                <p>Ahora que tienes todo configurado, es momento de crear tu primera cotización.</p>
                                
                                <h4>Proceso paso a paso:</h4>
                                <ol>
                                    <li>Haz clic en <strong>"Crear Cotización"</strong> en el menú lateral o en el botón flotante "+".</li>
                                    <li><strong>Selecciona el Cliente:</strong>
                                        <ul>
                                            <li>Escribe el nombre del cliente en el buscador</li>
                                            <li>Selecciona el cliente de la lista</li>
                                            <li>Si no está registrado, haz clic en "+" para agregarlo</li>
                                        </ul>
                                    </li>
                                    <li><strong>Agrega Productos:</strong>
                                        <ul>
                                            <li>Haz clic en <strong>"Agregar Producto"</strong></li>
                                            <li>Busca y selecciona el producto</li>
                                            <li>Ajusta la <strong>cantidad</strong></li>
                                            <li>Modifica el <strong>descuento</strong> si es necesario</li>
                                            <li>Verifica el <strong>impuesto</strong></li>
                                            <li>Haz clic en "Agregar"</li>
                                            <li>Repite para agregar más productos</li>
                                        </ul>
                                    </li>
                                    <li><strong>Configura los Detalles:</strong>
                                        <ul>
                                            <li><strong>Moneda:</strong> Selecciona COP, USD o EUR</li>
                                            <li><strong>Vendedor:</strong> Elige el vendedor responsable</li>
                                            <li><strong>Centro de Costo:</strong> Opcional</li>
                                            <li><strong>Fecha:</strong> Fecha de la cotización</li>
                                        </ul>
                                    </li>
                                    <li><strong>Agrega Notas (opcional):</strong>
                                        <ul>
                                            <li>Usa el editor de texto para agregar términos y condiciones</li>
                                            <li>Puedes incluir notas especiales para el cliente</li>
                                        </ul>
                                    </li>
                                    <li>Haz clic en <strong>"Guardar"</strong> para almacenar la cotización.</li>
                                </ol>
                            </div>
                        </div>

                        <!-- Paso 6 -->
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <div class="accordion-title">
                                    <span class="step-number">6</span>
                                    <span>Generar y Descargar PDF</span>
                                </div>
                                <i class="bi bi-chevron-down accordion-icon"></i>
                            </div>
                            <div class="accordion-content">
                                <p>Una vez creada la cotización, puedes generar el documento PDF profesional.</p>
                                
                                <h4>Opciones disponibles:</h4>
                                
                                <h4>1. Vista Previa (HTML)</h4>
                                <ul>
                                    <li>Haz clic en <strong>"Vista Previa"</strong></li>
                                    <li>Se abrirá una nueva ventana con el documento</li>
                                    <li>Útil para revisar antes de descargar</li>
                                    <li>Puedes imprimirlo directamente desde el navegador</li>
                                </ul>
                                
                                <h4>2. Descargar PDF</h4>
                                <ul>
                                    <li>Haz clic en <strong>"Descargar PDF"</strong></li>
                                    <li>Se generará un PDF profesional con:
                                        <ul>
                                            <li>Logo y colores de tu empresa</li>
                                            <li>Encabezado con datos de tu empresa</li>
                                            <li>Información completa del cliente</li>
                                            <li>Tabla detallada de productos</li>
                                            <li>Resumen de totales (Subtotal, Descuento, IVA, Total)</li>
                                            <li>Términos y condiciones</li>
                                            <li>Pie de página con contacto</li>
                                        </ul>
                                    </li>
                                    <li>El archivo se descargará automáticamente</li>
                                </ul>
                                
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i> <strong>¡Felicitaciones!</strong> Has creado tu primera cotización profesional. Ahora puedes enviarla a tu cliente.
                                </div>
                            </div>
                        </div>

                        <!-- Funciones Adicionales -->
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <div class="accordion-title">
                                    <i class="bi bi-gear"></i>
                                    <span>Funciones Adicionales</span>
                                </div>
                                <i class="bi bi-chevron-down accordion-icon"></i>
                            </div>
                            <div class="accordion-content">
                                <h4>Gestionar Cotizaciones</h4>
                                <ul>
                                    <li>Ve a <strong>"Cotizaciones"</strong> para ver todas las cotizaciones guardadas</li>
                                    <li>Puedes <strong>editar</strong> cotizaciones existentes</li>
                                    <li>Cambiar el <strong>estado</strong> (Pendiente, Aprobada, Rechazada, Facturada)</li>
                                    <li><strong>Eliminar</strong> cotizaciones que ya no necesites</li>
                                    <li>Generar PDFs de cotizaciones anteriores</li>
                                </ul>
                                
                                <h4>Editar Datos</h4>
                                <ul>
                                    <li>Puedes editar clientes, productos y vendedores en cualquier momento</li>
                                    <li>Los cambios no afectan las cotizaciones ya creadas</li>
                                    <li>Usa los botones de acción en cada tabla</li>
                                </ul>
                                
                                <h4>Personalización</h4>
                                <ul>
                                    <li>Cambia el color de tu empresa en cualquier momento</li>
                                    <li>Actualiza tu logo cuando lo necesites</li>
                                    <li>Modifica los términos y condiciones</li>
                                    <li>Ajusta el prefijo y numeración de documentos</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Soporte -->
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <div class="accordion-title">
                                    <i class="bi bi-question-circle"></i>
                                    <span>Soporte y Contacto</span>
                                </div>
                                <i class="bi bi-chevron-down accordion-icon"></i>
                            </div>
                            <div class="accordion-content">
                                <p>Si necesitas ayuda adicional o tienes sugerencias, visita:</p>
                                <p><a href="https://herramientasvirtuales.com" target="_blank" class="btn btn-primary">
                                    <i class="bi bi-globe"></i> Herramientas Virtuales
                                </a></p>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal Cliente -->
    <div class="modal fade" id="clienteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clienteModalTitle">Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="clienteForm">
                        <input type="hidden" id="cliente_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Razón Social *</label>
                                <input type="text" class="form-control" id="cliente_razonSocial" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">NIT *</label>
                                <input type="text" class="form-control" id="cliente_nit" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ciudad *</label>
                                <input type="text" class="form-control" id="cliente_ciudad" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Dirección *</label>
                                <input type="text" class="form-control" id="cliente_direccion" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nombre del Contacto</label>
                                <input type="text" class="form-control" id="cliente_contacto">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="cliente_telefono">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Correo 1</label>
                                <input type="email" class="form-control" id="cliente_correo1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Correo 2</label>
                                <input type="email" class="form-control" id="cliente_correo2">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarCliente">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Vendedor -->
    <div class="modal fade" id="vendedorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vendedorModalTitle">Nuevo Vendedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="vendedorForm">
                        <input type="hidden" id="vendedor_id">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" id="vendedor_nombre" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" id="vendedor_email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="vendedor_telefono">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estado *</label>
                                <select class="form-select" id="vendedor_estado" required>
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Tarjeta de Presentación Digital</label>
                                <input type="url" class="form-control" id="vendedor_tarjeta" placeholder="https://ejemplo.com/mi-tarjeta">
                                <small class="text-muted">URL de tu tarjeta de presentación digital (opcional)</small>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarVendedor">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Producto -->
    <div class="modal fade" id="productoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productoModalTitle">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="productoForm">
                        <input type="hidden" id="producto_id">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Código *</label>
                                <input type="text" class="form-control" id="producto_codigo" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="producto_nombre" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" id="producto_descripcion" rows="2"></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Valor Unitario *</label>
                                <input type="number" class="form-control" id="producto_valorUnitario" step="0.01" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cantidad</label>
                                <input type="number" class="form-control" id="producto_cantidad" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Descuento</label>
                                <div class="input-group">
                                    <select class="form-select" id="producto_descuentoTipo" style="max-width:64px">
                                        <option value="%">%</option>
                                        <option value="$">$</option>
                                    </select>
                                    <input type="number" class="form-control" id="producto_descuento" step="0.01" value="0" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Impuesto %</label>
                                <input type="number" class="form-control" id="producto_impuesto" step="0.01" value="0">
                            </div>
                            <div class="col-md-12 mt-3">
                                <label class="form-label">Enlace a Detalles <span class="text-muted">(Opcional)</span></label>
                                <input type="url" class="form-control" id="producto_enlaceDetalles" placeholder="https://ejemplo.com/producto">
                                <small class="text-muted">URL con más información del producto. El nombre será clickeable en la cotización.</small>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarProducto">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Cambiar Estado -->
    <div class="modal fade" id="estadoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Estado de Cotización</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cotizacion_estado_id">
                    <div class="mb-3">
                        <label class="form-label">Número de Cotización</label>
                        <input type="text" class="form-control" id="cotizacion_numero" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nuevo Estado *</label>
                        <select class="form-select" id="cotizacion_nuevo_estado">
                            <option value="pendiente">Pendiente</option>
                            <option value="aprobada">Aprobada</option>
                            <option value="facturada">Facturada</option>
                            <option value="rechazada">Rechazada</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarEstado">Guardar Estado</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav">
    <button type="button" class="mobile-nav-item" data-section="empresa">
            <i class="bi bi-gear"></i>
            <span>Empresa</span>
        </button>    

        <button type="button" class="mobile-nav-item" data-section="clientes">
            <i class="bi bi-people"></i>
            <span>Clientes</span>
        </button>
        <button type="button" class="mobile-nav-item" data-section="productos">
            <i class="bi bi-box-seam"></i>
            <span>Productos</span>
        </button>
    <button type="button" class="mobile-nav-item" data-section="vendedores">
            <i class="bi bi-person-badge"></i>
            <span>Vendedores</span>
        </button>
        <button type="button" class="mobile-nav-item" data-section="cotizaciones">
            <i class="bi bi-file-earmark-text"></i>
            <span>Cotizaciones</span>
        </button>
        
        
    </nav>

    <!-- Floating Action Button (Mobile Only) -->
    <button type="button" class="fab-button" data-section="crear-cotizacion">
        <i class="bi bi-plus-lg"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="db.js"></script>
    <script src="script.js"></script>
</body>
</html>
