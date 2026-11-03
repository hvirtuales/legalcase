# Especificación funcional y modelo de datos — Plataforma de Comprobantes / Cuentas de Cobro

> Documento de referencia para **reimplementar la plataforma en CodeIgniter 4 + Tailwind CSS + MySQL**.
> Describe toda la funcionalidad actual (SPA React con IndexedDB) y la traduce a un modelo relacional
> y a una arquitectura MVC de servidor.

---

## Tabla de contenido

1. [Resumen y stack destino](#1-resumen-y-stack-destino)
2. [Arquitectura: actual vs. destino](#2-arquitectura-actual-vs-destino)
3. [Roles, multi-tenant y autenticación](#3-roles-multi-tenant-y-autenticación)
4. [Modelo de datos (DDL MySQL)](#4-modelo-de-datos-ddl-mysql)
5. [Módulos y funcionalidad detallada](#5-módulos-y-funcionalidad-detallada)
6. [Reglas de negocio críticas](#6-reglas-de-negocio-críticas)
7. [Catálogos y datos semilla](#7-catálogos-y-datos-semilla)
8. [Rutas y mapa a controladores CodeIgniter](#8-rutas-y-mapa-a-controladores-codeigniter)
9. [Notas y decisiones de migración](#9-notas-y-decisiones-de-migración)

---

## 1. Resumen y stack destino

**Qué es:** SaaS multi-empresa para gestión de **comprobantes contables colombianos** (Ingreso / Egreso /
Documento Equivalente) y **cuentas de cobro** (solicitudes de pago de colaboradores), con partida doble,
PUC (Plan Único de Cuentas), impuestos (IVA / retención / otros), beneficiarios (terceros), cuentas
bancarias, caja menor y reportes.

| Capa | Actual | Destino |
|---|---|---|
| Frontend | React 19 + Vite + Tailwind v4 + shadcn/ui | **Vistas CodeIgniter + Tailwind CSS** |
| Enrutado | HashRouter (SPA) | **Rutas CI4** (`app/Config/Routes.php`) |
| Datos | IndexedDB en el navegador (4 bases) | **MySQL (InnoDB, utf8mb4)** |
| Lógica | `src/lib/localApi.ts` (cliente) | **Models + Controllers (servidor)** |
| Auth | SHA-256 en cliente + token en localStorage | **Sesión/JWT + `password_hash` (bcrypt)** |
| PDF | `html2canvas` + `jsPDF` (cliente) | Mantener cliente **o** `dompdf`/`mpdf` en servidor |
| QR | `qrcode` (cliente) | `endroid/qr-code` (servidor) o cliente |

**Moneda:** COP. Formato de UI `es-CO`. **Idioma:** español.

---

## 2. Arquitectura: actual vs. destino

La app actual es una SPA que **no usa el backend**: toda la persistencia vive en el navegador en **cuatro
bases IndexedDB** separadas. Para CodeIgniter todo se consolida en **una sola base MySQL**:

| Base IndexedDB actual | Object stores | Se une en MySQL como |
|---|---|---|
| `caja-virtual-db` | users, companies, voucher_types, vouchers, voucher_items, voucher_item_taxes, voucher_entries, beneficiaries, beneficiary_attachments, tax_configs, puc_accounts, payment_requests, payment_request_attachments | tablas del mismo nombre |
| `voucher-saas-attachments` | attachments | `voucher_attachments` |
| `caja-virtual-company-accounts` | company_accounts | `company_accounts` |
| `caja-virtual-cashbox` | cash_boxes, cash_transactions | `cash_boxes`, `cash_transactions` |

Implicaciones de la migración:
- Los **IDs** eran UUID string generados en cliente (`crypto.randomUUID()`). Se conservan como `CHAR(36)`
  o se migran a `BIGINT AUTO_INCREMENT` (recomendado en MySQL; ver §9).
- Los **logos, firmas y adjuntos** se guardaban como *data URL* base64. En servidor conviene guardarlos
  como **archivos** con una columna de ruta (ver §9).
- El **PUC es global** (compartido por todas las empresas); ver §6.

---

## 3. Roles, multi-tenant y autenticación

- **Usuario** (`users`): se registra con nombre, correo y contraseña. Un usuario puede ser dueño de varias
  **empresas** (`companies.owner_id`). No hay roles internos por empresa en la versión actual (el dueño es
  el administrador). Para CI se sugiere al menos roles `admin` / `usuario` si se amplía.
- **Multi-tenant:** casi todo el modelo se filtra por `company_id`. La “empresa activa” se elige en la UI
  y condiciona qué datos se ven.
- **Autenticación actual:** SHA-256 con prefijo de sal fijo `caja-virtual-2024:` + token base64 en
  `localStorage`. **En CodeIgniter reemplazar por** `password_hash()`/`password_verify()` (bcrypt/argon2)
  y sesión de servidor o JWT. No migrar el esquema SHA-256 (inseguro); forzar reset de contraseña o
  rehash en el primer login.
- **Público sin login:** el formulario de cuenta de cobro (`/solicitud/:companyId`) y la verificación de
  comprobante (`/verificar/:id`) son **rutas públicas**.

---

## 4. Modelo de datos (DDL MySQL)

DDL de referencia (MySQL 8, InnoDB, `utf8mb4`). Convenciones: `CHAR(36)` para PK UUID (cambiar a
`BIGINT UNSIGNED AUTO_INCREMENT` si se prefiere — ver §9); dinero `DECIMAL(15,2)`; tasas `DECIMAL(7,4)`
(soporta 0.414 %); banderas `TINYINT(1)`.

```sql
SET NAMES utf8mb4;

-- 3.1 Usuarios
CREATE TABLE users (
  id            CHAR(36) PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password      VARCHAR(255) NOT NULL,     -- bcrypt en destino
  created_at    DATETIME NOT NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.2 Empresas (raíz multi-tenant)
CREATE TABLE companies (
  id            CHAR(36) PRIMARY KEY,
  owner_id      CHAR(36) NULL,
  name          VARCHAR(200) NOT NULL,
  tax_id        VARCHAR(50)  NOT NULL DEFAULT '',   -- NIT
  address       VARCHAR(255) NOT NULL DEFAULT '',
  phone         VARCHAR(50)  NOT NULL DEFAULT '',
  email         VARCHAR(190) NOT NULL DEFAULT '',
  logo_url      MEDIUMTEXT   NULL,                  -- base64 hoy; ruta de archivo en destino
  primary_color VARCHAR(9)   NOT NULL DEFAULT '#000000',
  created_at    DATETIME NULL,
  KEY idx_companies_owner (owner_id),
  CONSTRAINT fk_companies_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.3 Tipos de comprobante (auto-numeración por prefijo)
CREATE TABLE voucher_types (
  id               CHAR(36) PRIMARY KEY,
  company_id       CHAR(36) NOT NULL,
  name             VARCHAR(80) NOT NULL,        -- Ingreso | Egreso | Documento Equivalente | ...
  prefix           VARCHAR(10) NOT NULL,        -- ING | EGR | DE
  current_sequence INT NOT NULL DEFAULT 0,
  KEY idx_vt_company (company_id),
  CONSTRAINT fk_vt_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.4 Comprobantes
CREATE TABLE vouchers (
  id                  CHAR(36) PRIMARY KEY,
  company_id          CHAR(36) NOT NULL,
  type_id             CHAR(36) NOT NULL,
  number              VARCHAR(30) NOT NULL,      -- ING-000001
  date                DATE NOT NULL,
  beneficiary_name    VARCHAR(200) NOT NULL DEFAULT '',
  beneficiary_id      VARCHAR(50)  NOT NULL DEFAULT '',  -- número de identificación del tercero
  description         TEXT NULL,
  payment_method      VARCHAR(50) NOT NULL DEFAULT 'Efectivo',
  source_account_id   CHAR(36) NULL,             -- cuenta bancaria o caja de origen (Egreso)
  source_account_label VARCHAR(200) NULL,
  subtotal            DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_iva           DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_retencion     DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_other_taxes   DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_amount        DECIMAL(15,2) NOT NULL DEFAULT 0,
  status              ENUM('emitido','pagado','anulado') NOT NULL DEFAULT 'emitido',
  signature_url       MEDIUMTEXT NULL,
  created_at          DATETIME NOT NULL,
  KEY idx_v_company (company_id),
  KEY idx_v_type (type_id),
  UNIQUE KEY uq_v_company_number (company_id, number),
  CONSTRAINT fk_v_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_v_type    FOREIGN KEY (type_id)    REFERENCES voucher_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.5 Ítems del comprobante
CREATE TABLE voucher_items (
  id               CHAR(36) PRIMARY KEY,
  voucher_id       CHAR(36) NOT NULL,
  description      TEXT NOT NULL,
  amount           DECIMAL(15,2) NOT NULL DEFAULT 0,   -- base gravable
  iva_rate         DECIMAL(7,4) NOT NULL DEFAULT 0,
  iva_amount       DECIMAL(15,2) NOT NULL DEFAULT 0,
  retencion_rate   DECIMAL(7,4) NOT NULL DEFAULT 0,
  retencion_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  KEY idx_vi_voucher (voucher_id),
  CONSTRAINT fk_vi_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.6 Impuestos adicionales por ítem ("otros": ReteICA, ReteIVA, etc.)
CREATE TABLE voucher_item_taxes (
  id            CHAR(36) PRIMARY KEY,
  item_id       CHAR(36) NOT NULL,
  tax_config_id CHAR(36) NULL,
  name          VARCHAR(120) NOT NULL,
  rate          DECIMAL(7,4) NOT NULL DEFAULT 0,
  amount        DECIMAL(15,2) NOT NULL DEFAULT 0,
  is_additive   TINYINT(1) NOT NULL DEFAULT 0,   -- 1 suma, 0 resta al neto
  KEY idx_vit_item (item_id),
  CONSTRAINT fk_vit_item FOREIGN KEY (item_id) REFERENCES voucher_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.7 Asiento contable (partida doble)
CREATE TABLE voucher_entries (
  id            CHAR(36) PRIMARY KEY,
  voucher_id    CHAR(36) NOT NULL,
  account_code  VARCHAR(20) NOT NULL,
  account_name  VARCHAR(200) NOT NULL,
  debit         DECIMAL(15,2) NOT NULL DEFAULT 0,
  credit        DECIMAL(15,2) NOT NULL DEFAULT 0,
  description   VARCHAR(255) NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  KEY idx_ve_voucher (voucher_id),
  CONSTRAINT fk_ve_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.8 Adjuntos del comprobante
CREATE TABLE voucher_attachments (
  id            CHAR(36) PRIMARY KEY,
  voucher_id    CHAR(36) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mimetype      VARCHAR(100) NOT NULL,
  size          INT NOT NULL,
  file_path     VARCHAR(255) NULL,   -- destino: ruta en disco (base64 'data' hoy)
  uploaded_at   DATETIME NOT NULL,
  KEY idx_va_voucher (voucher_id),
  CONSTRAINT fk_va_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.9 Beneficiarios / terceros
CREATE TABLE beneficiaries (
  id                  CHAR(36) PRIMARY KEY,
  company_id          CHAR(36) NOT NULL,
  name                VARCHAR(200) NOT NULL,
  id_type             VARCHAR(20) NOT NULL DEFAULT 'NIT',   -- NIT|CC|CE|Pasaporte|RUT|TI|Otro
  id_number           VARCHAR(50) NOT NULL DEFAULT '',
  email               VARCHAR(190) NOT NULL DEFAULT '',
  phone               VARCHAR(50)  NOT NULL DEFAULT '',
  address             VARCHAR(255) NOT NULL DEFAULT '',
  city                VARCHAR(100) NOT NULL DEFAULT '',
  contact_name        VARCHAR(150) NOT NULL DEFAULT '',
  notes               TEXT NULL,
  bank_name           VARCHAR(120) NOT NULL DEFAULT '',
  bank_account_type   VARCHAR(30)  NOT NULL DEFAULT 'Ahorros',
  bank_account_number VARCHAR(50)  NOT NULL DEFAULT '',
  created_at          DATETIME NOT NULL,
  KEY idx_b_company (company_id),
  UNIQUE KEY uq_b_company_doc (company_id, id_type, id_number),
  CONSTRAINT fk_b_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.10 Adjuntos del tercero (RUT, cédula, cámara de comercio...)
CREATE TABLE beneficiary_attachments (
  id             CHAR(36) PRIMARY KEY,
  beneficiary_id CHAR(36) NOT NULL,
  original_name  VARCHAR(255) NOT NULL,
  mimetype       VARCHAR(100) NOT NULL,
  size           INT NOT NULL,
  file_path      VARCHAR(255) NULL,
  uploaded_at    DATETIME NOT NULL,
  KEY idx_ba_ben (beneficiary_id),
  CONSTRAINT fk_ba_ben FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.11 Configuración de impuestos por empresa
CREATE TABLE tax_configs (
  id          CHAR(36) PRIMARY KEY,
  company_id  CHAR(36) NOT NULL,
  name        VARCHAR(120) NOT NULL,
  code        VARCHAR(20)  NOT NULL,
  category    ENUM('iva','retencion','other') NOT NULL,
  rate        DECIMAL(7,4) NOT NULL DEFAULT 0,
  is_additive TINYINT(1) NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0,
  KEY idx_tc_company (company_id),
  CONSTRAINT fk_tc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.12 Plan Único de Cuentas (GLOBAL, compartido por todas las empresas)
CREATE TABLE puc_accounts (
  id          CHAR(36) PRIMARY KEY,
  code        VARCHAR(20) NOT NULL,
  name        VARCHAR(200) NOT NULL,
  type        ENUM('activo','pasivo','patrimonio','ingreso','gasto','costo') NOT NULL,
  nature      ENUM('debito','credito') NOT NULL,
  level       TINYINT NOT NULL DEFAULT 1,       -- 1..4
  parent_code VARCHAR(20) NULL,
  description VARCHAR(255) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_puc_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Nota: en la app actual se guarda con company_id='global'. En MySQL el PUC es una
-- tabla única sin company_id (o con un flag is_global). Ver §6.

-- 3.13 Cuentas de cobro / solicitudes de pago
CREATE TABLE payment_requests (
  id                       CHAR(36) PRIMARY KEY,
  company_id               CHAR(36) NOT NULL,
  reference                VARCHAR(40) NOT NULL,         -- SOL-<timestamp>
  collaborator_name        VARCHAR(200) NOT NULL,
  collaborator_id_type     VARCHAR(20)  NOT NULL DEFAULT 'CC',
  collaborator_id          VARCHAR(50)  NOT NULL,
  collaborator_email       VARCHAR(190) NOT NULL DEFAULT '',
  collaborator_phone       VARCHAR(50)  NOT NULL DEFAULT '',
  collaborator_city        VARCHAR(100) NOT NULL DEFAULT '',
  collaborator_address     VARCHAR(255) NOT NULL DEFAULT '',
  collaborator_contact     VARCHAR(150) NOT NULL DEFAULT '',
  collaborator_bank        VARCHAR(120) NOT NULL DEFAULT '',
  collaborator_account_type VARCHAR(30) NOT NULL DEFAULT 'Ahorros',
  collaborator_account     VARCHAR(50)  NOT NULL DEFAULT '',
  concept                  TEXT NULL,                    -- resumen (concatenación de conceptos)
  period                   VARCHAR(60) NULL,
  items_json               JSON NULL,                    -- [{description,amount,iva_rate,iva_amount,retencion_rate,retencion_amount}]
  amount                   DECIMAL(15,2) NOT NULL DEFAULT 0,   -- subtotal
  iva_rate                 DECIMAL(7,4) NULL,
  iva_amount               DECIMAL(15,2) NOT NULL DEFAULT 0,
  retencion_rate           DECIMAL(7,4) NOT NULL DEFAULT 0,    -- la fija el admin
  retencion_amount         DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_amount             DECIMAL(15,2) NOT NULL DEFAULT 0,
  cert_no_iva              TINYINT(1) NOT NULL DEFAULT 0,
  cert_art383              TINYINT(1) NOT NULL DEFAULT 0,
  signature_data           MEDIUMTEXT NULL,              -- firma base64 (o ruta de archivo)
  signed_at                DATETIME NULL,
  status                   ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_notes              TEXT NULL,
  reviewed_by              VARCHAR(100) NULL,
  reviewed_at              DATETIME NULL,
  voucher_id               CHAR(36) NULL,                -- comprobante generado al aprobar (futuro)
  created_at               DATETIME NOT NULL,
  KEY idx_pr_company (company_id),
  UNIQUE KEY uq_pr_reference (reference),
  CONSTRAINT fk_pr_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.14 Adjuntos de la cuenta de cobro
CREATE TABLE payment_request_attachments (
  id                 CHAR(36) PRIMARY KEY,
  payment_request_id CHAR(36) NOT NULL,
  original_name      VARCHAR(255) NOT NULL,
  mimetype           VARCHAR(100) NOT NULL,
  size               INT NOT NULL,
  file_path          VARCHAR(255) NULL,
  uploaded_at        DATETIME NOT NULL,
  KEY idx_pra_pr (payment_request_id),
  CONSTRAINT fk_pra_pr FOREIGN KEY (payment_request_id) REFERENCES payment_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.15 Cuentas bancarias de la empresa (origen de pagos)
CREATE TABLE company_accounts (
  id              CHAR(36) PRIMARY KEY,
  company_id      CHAR(36) NOT NULL,
  bank_name       VARCHAR(120) NOT NULL,
  account_type    VARCHAR(30)  NOT NULL DEFAULT 'Ahorros',
  account_number  VARCHAR(50)  NOT NULL DEFAULT '',
  account_holder  VARCHAR(200) NOT NULL DEFAULT '',
  initial_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
  notes           TEXT NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL,
  KEY idx_ca_company (company_id),
  CONSTRAINT fk_ca_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.16 Cajas menores
CREATE TABLE cash_boxes (
  id              CHAR(36) PRIMARY KEY,
  company_id      CHAR(36) NOT NULL,
  name            VARCHAR(120) NOT NULL,
  description     VARCHAR(255) NULL,
  initial_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL,
  KEY idx_cb_company (company_id),
  CONSTRAINT fk_cb_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.17 Movimientos de caja
CREATE TABLE cash_transactions (
  id            CHAR(36) PRIMARY KEY,
  cash_box_id   CHAR(36) NOT NULL,
  type          ENUM('ingreso','egreso','arqueo','ajuste') NOT NULL,
  concept       VARCHAR(255) NOT NULL DEFAULT '',
  amount        DECIMAL(15,2) NOT NULL DEFAULT 0,
  balance_after DECIMAL(15,2) NOT NULL DEFAULT 0,   -- para arqueo/ajuste = efectivo contado
  date          DATE NOT NULL,
  reference     VARCHAR(60) NULL,
  created_at    DATETIME NOT NULL,
  KEY idx_ct_box (cash_box_id),
  CONSTRAINT fk_ct_box FOREIGN KEY (cash_box_id) REFERENCES cash_boxes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Diagrama de relaciones (resumen)

```
users 1───* companies 1───* voucher_types 1───* vouchers 1───* voucher_items 1───* voucher_item_taxes
                    │                                    └──* voucher_entries
                    │                                    └──* voucher_attachments
                    ├──* beneficiaries 1───* beneficiary_attachments
                    ├──* tax_configs
                    ├──* payment_requests 1───* payment_request_attachments
                    ├──* company_accounts
                    └──* cash_boxes 1───* cash_transactions

puc_accounts  (GLOBAL, sin company_id — jerarquía por parent_code)
```

---

## 5. Módulos y funcionalidad detallada

Cada módulo es una página en la SPA actual → un controlador+vistas en CI.

### 5.1 Autenticación (`/login`, `/register`)
- Registro: nombre, correo, contraseña. Correo único.
- Login: valida credenciales. Guarda usuario/sesión.
- Perfil (en Ajustes): actualizar nombre/correo; cambiar contraseña (verifica la actual).

### 5.2 Dashboard (`/`)
- Tarjetas de resumen de la empresa activa: conteos y totales de ingresos/egresos, IVA, retención, balance.
- Alimentado por el mismo cálculo de Reportes (§6.9).

### 5.3 Empresas (`/companies`)
- CRUD de empresas. Campos: nombre, NIT (`tax_id`), dirección, teléfono, correo, **logo** (imagen
  redimensionada en canvas a máx. 400 px de ancho y guardada como PNG base64), **color de marca**
  (`primary_color`).
- El color de marca **tematiza toda la UI** (variables CSS `--primary`, `--ring`, `--accent`).
- Al crear una empresa se **siembran** automáticamente: tipos de comprobante (Ingreso, Egreso) y la
  configuración de impuestos por defecto (§7).
- La empresa activa se persiste; si la almacenada ya no existe, cae a la primera disponible.

### 5.4 Comprobantes — listado (`/vouchers`)
- Tabla filtrable por **tipo**, **estado** (emitido/pagado/anulado) y **rango de fechas**.
- Cada fila enlaza al detalle. Muestra número, tipo, beneficiario, fecha, total, estado.

### 5.5 Comprobantes — crear/editar (`/vouchers/new`, `/vouchers/:id/edit`)
- **Cabecera:** tipo de comprobante, fecha, medio de pago, beneficiario (selector con búsqueda), y para
  **Egreso**: cuenta/caja de origen (de `company_accounts` o `cash_boxes`). Concepto general.
- **Conceptos y valores (ítems):** descripción (con toggle **texto / párrafo**), valor base, IVA (%),
  retención (%), y **otros impuestos** por ítem (ReteICA, ReteIVA…) desde `tax_configs` categoría `other`.
  El neto por ítem = base + IVA − retención ± otros.
- **Totales** calculados en servidor a partir de los ítems (§6.3): subtotal, total IVA, total retención,
  total otros, total neto.
- **Asiento contable (partida doble):** líneas cuenta PUC / débito / crédito / concepto. Botón
  **Auto-generar** que arma el asiento estándar según tipo (Ingreso/Egreso) y medio de pago (§6.5).
  Indicador de cuadre Debe = Haber.
- **Validaciones al emitir Egreso:**
  - Debe haber **saldo a favor** de la empresa (balance = ingresos − egresos, comprobantes no anulados).
  - El egreso no puede superar el balance disponible.
  - Si el origen es una **caja menor**, valida saldo de esa caja.
- **Numeración automática** al crear (§6.1). En edición no cambia el número (tipo bloqueado). No se puede
  editar un comprobante en estado `pagado` o `anulado`.

### 5.6 Comprobante — detalle / impresión (`/vouchers/:id`)
- Documento imprimible en pantalla (sin plantilla aparte). `@media print` oculta la UI (`.no-print`).
- **Código QR** en el pie que enlaza a la URL de verificación pública.
- Exportación a **PDF**. Cambios de estado (emitido → pagado/anulado). Adjuntos.
- Etiqueta de estado: `pagado` se muestra como **“Recibido”** en comprobantes de Ingreso.

### 5.7 Verificación pública (`/verificar/:id`, `/verificar`)
- Página pública que valida un comprobante por **id** o por **número**. Muestra empresa, tipo, fecha,
  beneficiario, totales y estado.

### 5.8 Beneficiarios / Terceros (`/beneficiaries`)
- CRUD con: nombre/razón social, tipo y número de ID, correo, teléfono, ciudad, contacto, dirección,
  notas internas, y **datos bancarios** (banco, tipo y número de cuenta). Adjuntos (RUT, cédula…).
- **ID único** por empresa (`id_type + id_number`) — error descriptivo si se duplica.
- **Importación CSV** (delimitador `;`) con cabeceras:
  `nombre;tipo_id;numero_id;email;telefono;ciudad;direccion;contacto;notas;banco;tipo_cuenta;numero_cuenta`
  en modo `add` (solo nuevos) o `upsert` (actualiza existentes). Plantilla descargable.

### 5.9 Impuestos (`/taxes`)
- CRUD de `tax_configs` por empresa: nombre, código, categoría (`iva`/`retencion`/`other`), tasa,
  `is_additive`, `is_active`, orden. Botón para **restaurar** los valores por defecto (§7).

### 5.10 PUC (`/puc`)
- CRUD del Plan Único de Cuentas **global** (compartido). Campos: código, nombre, tipo, naturaleza,
  nivel (1–4), código padre, descripción. Importación y siembra de un PUC colombiano estándar (§7).

### 5.11 Cuentas de cobro — admin (`/payment-requests`)
- Listado con métricas (total/pendientes/aprobadas/rechazadas) y filtro por estado.
- **Enlace público** por empresa para compartir con colaboradores:
  `…/#/solicitud/{companyId}`.
- **Detalle:** datos del colaborador, datos bancarios, conceptos, totales, firma, adjuntos.
- **Definir retención (nuevo):** mientras está `pending`, el admin fija la **tasa de retención** (con
  atajos desde las tasas configuradas). Se aplica a cada concepto y **recalcula** `retencion_amount` y
  `total_amount` (§6.7).
- **Aprobar / Rechazar** (con notas). *Nota:* el texto de la UI dice que al aprobar se genera un
  comprobante de Egreso; hoy **no** se genera automáticamente (pendiente — ver §9).
- **Descarga PDF** de la cuenta de cobro.

### 5.12 Cuentas de cobro — formulario público (`/solicitud/:companyId`)
- Página pública, sin login, con la marca de la empresa. Flujo:
  1. **Tipo y número de documento** → al salir del campo, **valida si el tercero existe** (por número,
     priorizando el tipo; si coincide adopta el tipo guardado) y **precarga** toda su info (§6.8).
  2. **Nombre completo**, correo, teléfono, **ciudad, dirección, contacto**.
  3. **Datos bancarios** (banco con autocompletar, tipo y número de cuenta).
  4. **Conceptos y valores:** descripción (toggle **texto / párrafo**), valor e **IVA** (la retención la
     define la empresa después; el colaborador no la ve).
  5. **Adjuntos** de soporte.
  6. **Certificaciones** tributarias (opcionales): no responsable de IVA, Art. 383 E.T.
  7. **Firma digital:** dibujar con mouse/dedo **o importar una imagen** (PNG/JPG/WebP).
- Al enviar: crea la cuenta de cobro (referencia `SOL-…`), guarda adjuntos, genera pantalla de éxito con
  estado y **PDF descargable**, y **registra/actualiza el tercero** en el directorio (upsert, §6.8).

### 5.13 Cuentas bancarias de la empresa (`/company-accounts`)
- CRUD de `company_accounts`: banco, tipo y número de cuenta, titular, saldo inicial, notas, activo.
  Se usan como origen de pago en Egresos.

### 5.14 Caja menor (`/cash-box`)
- CRUD de cajas (`cash_boxes`) con saldo inicial.
- **Movimientos** (`cash_transactions`): `ingreso`, `egreso`, `arqueo`, `ajuste`.
- **Saldo por reproducción** de movimientos en orden cronológico (§6.6): ingreso suma, egreso resta;
  **arqueo/ajuste fijan** el saldo al efectivo contado (`balance_after`).

### 5.15 Reportes (`/reports`)
- Resumen de ingresos/egresos/IVA/retención/balance por rango de fechas, con gráficos.

### 5.16 Ajustes (`/settings`) y Ayuda (`/help`)
- Perfil y contraseña. Página de ayuda.

---

## 6. Reglas de negocio críticas

### 6.1 Numeración automática de comprobantes
Al crear (sin `number` explícito): se toma el `voucher_type`, se incrementa `current_sequence` y el número
es `PREFIX-` + secuencia con **6 dígitos** (`padStart(6,'0')`). Ej.: `ING-000001`.
> En MySQL hacerlo dentro de una **transacción** con bloqueo del `voucher_type` para evitar colisiones.

### 6.2 Referencia de cuenta de cobro
`reference = 'SOL-' + timestamp`. Debe ser única. En MySQL usar un contador por empresa o `SOL-`+id/año.

### 6.3 Totales del comprobante (calculados en servidor)
```
subtotal          = Σ item.amount
total_iva         = Σ item.iva_amount
total_retencion   = Σ item.retencion_amount
total_other_taxes = Σ (por ítem: Σ tax.is_additive ? +tax.amount : −tax.amount)
total_amount      = subtotal + total_iva − total_retencion + total_other_taxes
```
El formulario **no** envía los totales; se recalculan a partir de los ítems (fuente de verdad).
`iva_amount = amount * iva_rate / 100`, `retencion_amount = amount * retencion_rate / 100` (redondeo 2 dec).

### 6.4 Auto-siembra y deduplicado de tipos de comprobante
Al leer los tipos de una empresa se **deduplica por nombre** (conservando el de mayor `current_sequence`)
y se crean los faltantes: **Ingreso (ING), Egreso (EGR), Documento Equivalente (DE)**. Idempotente.

### 6.5 Auto-generación del asiento contable
Según tipo y medio de pago se arma el asiento estándar (cuentas PUC entre paréntesis):
- **Ingreso:** Debe Caja/Banco (1105/1110) por el neto; Debe Anticipo Rte. Fuente (136005) si hay
  retención; Haber Ingresos (4145) por el subtotal; Haber IVA Generado (240805) por el IVA.
- **Egreso:** Debe Gasto (5110) por el subtotal; Debe IVA Descontable (240810) por el IVA; Haber
  Caja/Banco por el neto; Haber Rte. Fuente practicada (236515) por la retención.
- Mapa medio de pago→cuenta: Efectivo→1105; Transferencia/Consignación/Cheque/Débito/PSE→1110;
  Tarjeta crédito→2380.

### 6.6 Saldo de caja menor (reproducción)
Orden estable por `created_at` y, en empate, por `id`. Recorrido:
`ingreso: bal += amount`, `egreso: bal -= amount`, `arqueo|ajuste: bal = balance_after` (efectivo contado).
No se confía en el `balance_after` de ingreso/egreso (se recalcula). Redondeo a 2 decimales.

### 6.7 Retención de la cuenta de cobro (definida por el admin)
El admin fija una **tasa**; se aplica a la base de **cada concepto** en `items_json`
(`retencion_amount = base * tasa/100`, redondeo 2 dec), y se recalculan:
`retencion_amount(total) = Σ conceptos`, `total_amount = amount + iva_amount − retencion_amount`.

### 6.8 Validación y sincronización del tercero (formulario público)
- **Búsqueda:** por `id_number` (prioriza el `id_type` seleccionado; si no, cae a solo número). Si hay
  match, precarga nombre, correo, teléfono, ciudad, dirección, contacto, banco/cuenta y **adopta el
  `id_type` guardado**.
- **Al enviar (upsert):** si el tercero **no existe** → se crea con toda la info (incl. bancaria); si
  **existe** → se **actualizan** sus campos con lo diligenciado. Un campo vacío **no** borra el valor
  previo (se conserva). Las **notas internas** no se exponen en el formulario público.

### 6.9 Resumen de reportes / balance
Sobre comprobantes **no anulados**, uniendo el nombre del tipo:
```
neto_ingresos = Σ total_amount (tipo Ingreso)
neto_egresos  = Σ total_amount (tipo Egreso)
balance       = neto_ingresos − neto_egresos
```
El **balance** también gobierna la validación de saldo al emitir Egresos (§5.5).

### 6.10 Estado y etiqueta
Estados: `emitido | pagado | anulado`. En comprobantes de **Ingreso**, `pagado` se muestra como
**“Recibido”**.

---

## 7. Catálogos y datos semilla

### 7.1 Enumeraciones (constantes de UI)
- **Tipos de ID:** `NIT, CC, CE, Pasaporte, RUT, TI, Otro`.
- **Tipos de cuenta:** `Ahorros, Corriente, Nómina, Fiduciaria, Otro`.
- **Medios de pago:** `Efectivo, Transferencia bancaria, Consignación bancaria, Cheque, Tarjeta débito,
  Tarjeta crédito, PSE`.
- **Bancos:** lista de ~70 entidades colombianas (ver `src/lib/constants.ts` → `BANKS`).

### 7.2 Impuestos por defecto (`tax_configs`, se siembran al crear empresa)
| code | name | category | rate | is_additive |
|---|---|---|---|---|
| IVA0 | IVA 0% – Excluido | iva | 0 | 1 |
| IVA5 | IVA 5% – Reducido | iva | 5 | 1 |
| IVA19 | IVA 19% – General | iva | 19 | 1 |
| RFTE0 | Rte. Fuente 0% – No aplica | retencion | 0 | 0 |
| RFTE1 | Rte. Fuente 1% – Compras | retencion | 1 | 0 |
| RFTE25 | Rte. Fuente 2.5% | retencion | 2.5 | 0 |
| RFTE35 | Rte. Fuente 3.5% – Servicios | retencion | 3.5 | 0 |
| RFTE4 | Rte. Fuente 4% – Arrend. muebles | retencion | 4 | 0 |
| RFTE6 | Rte. Fuente 6% – Arrend. inmuebles | retencion | 6 | 0 |
| RFTE11 | Rte. Fuente 11% – Honorarios | retencion | 11 | 0 |
| RFTE15 | Rte. Fuente 15% – Dividendos | retencion | 15 | 0 |
| RFTE20 | Rte. Fuente 20% – Honorarios P.N. | retencion | 20 | 0 |
| RICA | ReteICA 0.414% | other | 0.414 | 0 |
| RIVA | ReteIVA 15% | other | 15 | 0 |

### 7.3 PUC por defecto (`puc_accounts`, global)
PUC colombiano estándar (~200 cuentas) con estructura jerárquica por `parent_code` y niveles 1–4.
Clases raíz: **1 Activos, 2 Pasivos, 3 Patrimonio, 4 Ingresos, 5 Gastos, 6 Costos de producción,
7 Costos de ventas**. El listado completo está en `src/lib/localApi.ts` → `PUC_DEFAULTS`; conviene
**exportarlo a un seeder/CSV** de CodeIgniter. Cuentas clave usadas por la auto-generación de asientos:
`1105, 1110, 136005, 236515, 240805, 240810, 2380, 4145, 5110`.

---

## 8. Rutas y mapa a controladores CodeIgniter

| Ruta actual (SPA) | Acceso | Controlador CI sugerido |
|---|---|---|
| `/login`, `/register` | público | `Auth::login/register` |
| `/` (dashboard) | privado | `Dashboard::index` |
| `/companies` | privado | `Companies` (CRUD) |
| `/vouchers` | privado | `Vouchers::index` |
| `/vouchers/new`, `/vouchers/:id/edit` | privado | `Vouchers::create/edit` |
| `/vouchers/:id` | privado | `Vouchers::show` (detalle/PDF) |
| `/verificar/:id`, `/verificar` | **público** | `VoucherVerify::show` |
| `/beneficiaries` | privado | `Beneficiaries` (CRUD + importar) |
| `/taxes` | privado | `TaxConfigs` (CRUD + seed) |
| `/puc` | privado | `Puc` (CRUD global + importar/seed) |
| `/payment-requests` | privado | `PaymentRequests` (listar, detalle, retención, aprobar/rechazar) |
| `/solicitud/:companyId` | **público** | `PublicPaymentRequest::form/submit` |
| `/company-accounts` | privado | `CompanyAccounts` (CRUD) |
| `/cash-box` | privado | `CashBox` (cajas + movimientos) |
| `/reports` | privado | `Reports::summary` |
| `/settings`, `/help` | privado | `Settings`, `Help` |

**Endpoints/acciones clave** (equivalentes a las funciones `api*` de `localApi.ts`):
`getCompanies/createCompany/updateCompany/deleteCompany`, `getVoucherTypes`,
`getVouchers/getVoucher/getVoucherByNumber/createVoucher/updateVoucher/patchVoucherStatus`,
`getBeneficiaries/createBeneficiary/updateBeneficiary/deleteBeneficiary/importBeneficiaries`,
`getTaxConfigs/createTaxConfig/updateTaxConfig/deleteTaxConfig/seedTaxConfigs`,
`getPucAccounts/createPucAccount/updatePucAccount/deletePucAccount/importPucAccounts/seedPucAccounts`,
`getPaymentRequests/getPaymentRequest/createPaymentRequest/approve/reject/setRetencion/saveAttachment`,
`getReportsSummary`, `getPublicCompany`.

---

## 9. Notas y decisiones de migración

1. **IDs:** se puede conservar `CHAR(36)` (UUID) para mantener compatibilidad, o cambiar a
   `BIGINT AUTO_INCREMENT` (mejor rendimiento/índices). Si se cambia, ajustar todas las FKs.
2. **Archivos base64 → disco:** `logo_url`, `signature_data`, y los `data` de adjuntos hoy son *data URL*
   base64. En servidor, guardarlos como **archivos** (carpeta `writable/uploads/…`) y persistir solo la
   **ruta** (`file_path`). Reduce enormemente el tamaño de la BD.
3. **Contraseñas:** no migrar el hash SHA-256 (sal fija, inseguro). Usar `password_hash()` y forzar
   restablecimiento o rehash transparente en el primer login.
4. **PUC global:** en la app es `company_id='global'`. En MySQL usar **una tabla única** `puc_accounts`
   sin `company_id` (como en el DDL). Si en el futuro se quiere PUC por empresa, añadir `company_id` NULL
   = global + override por empresa.
5. **Transacciones:** envolver en transacción las operaciones compuestas: crear/editar comprobante
   (voucher + items + item_taxes + entries), numeración (bloqueo de `voucher_types`), y el upsert de
   tercero desde el formulario público.
6. **Cálculos en servidor:** replicar §6.3–§6.9 en los Models/Services (no confiar en el cliente).
7. **Bugs/pendientes detectados a corregir durante la migración:**
   - **Otros impuestos por ítem no se persisten:** al guardar el comprobante el código lee `item.taxes`
     pero el formulario envía `extra_taxes`, por lo que los “otros impuestos” no se escriben en
     `voucher_item_taxes` (aunque el total sí los considera). Unificar en `extra_taxes`.
   - **`source_account_id`/`source_account_label`** se capturan en el formulario pero **no se guardan** al
     *crear* el comprobante (sí en editar). Persistirlos siempre.
   - **Aprobar cuenta de cobro no genera el comprobante de Egreso** pese a que la UI lo anuncia.
     Implementar la generación automática (usando la retención ya definida) y enlazar `voucher_id`.
8. **Índices/únicos recomendados:** `vouchers(company_id, number)` único; `beneficiaries(company_id,
   id_type, id_number)` único; `payment_requests(reference)` único; `puc_accounts(code)` único;
   `users(email)` único.
9. **PDF y QR:** se pueden dejar en el cliente (mismo enfoque `jsPDF`/`qrcode`) o pasar a servidor
   (`dompdf`/`mpdf` + `endroid/qr-code`). La verificación por QR apunta a la ruta pública `/verificar/:id`.
10. **Tailwind:** portar los tokens de tema (variables CSS `--primary/--ring/--accent`) para conservar el
    **color de marca por empresa** aplicado dinámicamente.

---

*Generado a partir del código fuente actual (`src/lib/localApi.ts`, `src/lib/db.ts`, `src/types/index.ts`,
`src/lib/*Db.ts`, `src/pages/*`). Última actualización del modelo incluye: retención de cuentas de cobro
definida por el admin, campos ciudad/dirección/contacto en el formulario público, y sincronización
(upsert) de terceros.*
