# Especificación del Módulo "Gestión de Cobros" para CodeIgniter 4

> Documento base de desarrollo. Describe en detalle el proyecto actual (React 19 + Express + Firebase) y cómo trasladarlo a un **módulo de CodeIgniter 4** con **MySQL/MariaDB**, **vistas PHP + Tailwind** y **cURL** para la IA. Todo el texto de la interfaz y los prompts va en **español (Colombia/LATAM, moneda COP)**.

---

## 1. Resumen funcional

Aplicación SaaS **multiempresa** de **gestión de cartera y cobranzas** con asistencia de IA. Un usuario se registra, crea una o varias **empresas** (tenants) y, dentro de cada empresa, gestiona **facturas** (cartera). Sobre esas facturas puede:

1. **Importar** cartera desde Excel/CSV (mapeo automático de cabeceras).
2. **Ver KPIs** de cartera (recaudado, vencido/mora, pendiente, eficiencia de recaudo).
3. **Filtrar, buscar, ordenar y paginar** las facturas.
4. **Editar seguimiento** de cada factura (estado, contacto, notas, observaciones).
5. **Generar con IA** (Gemini/OpenAI):
   - Recordatorios de cobro personalizados (correo/WhatsApp, 3 tonos).
   - Un plan estratégico de cobros de toda la cartera.
   - Una propuesta de cotización de renovación a partir de una factura.
6. **Registrar el envío** de recordatorios (historial/log) y abrir `mailto:`/WhatsApp con el mensaje.
7. **Configurar canales** de comunicación por empresa (SMTP, WhatsApp API, SMS) y el **proveedor de IA**.
8. **Exportar** la cartera a Excel/CSV.

---

## 2. Stack actual → equivalente en CodeIgniter 4

| Pieza actual | Tecnología actual | Equivalente en CI4 |
|---|---|---|
| SPA | React 19 + Vite + TypeScript | Vistas PHP (`app/Views/`) + Tailwind + JS/AJAX (fetch) o HTMX/Alpine |
| Estilos | Tailwind v4 | Tailwind (ya instalado en tu CI4) |
| Servidor / proxy IA | Express (`server.ts`), 3 endpoints | Controllers de CI4 + `CURLRequest`/Guzzle |
| Persistencia | Firebase Firestore (NoSQL) | MySQL/MariaDB con Migrations + Models |
| Autenticación | Firebase Auth (email/clave) | **CodeIgniter Shield** (recomendado) |
| Multiempresa | Campo `companyId` en cada doc | Columna `company_id` + filtros por tenant |
| Config canales | Colección `channels_config` | Tabla `channels_config` (1 fila por empresa) |
| Provider IA | `localStorage` (`cobros_ai_provider`) | Columna en `channels_config` o sesión |
| Importar/exportar Excel | SheetJS (`xlsx`) en navegador | `PhpSpreadsheet` en servidor (o `xlsx` en cliente) |

> **Decisión asumida en este documento:** frontend en vistas PHP + Tailwind, base de datos MySQL, auth con Shield. Si finalmente conservas React como SPA "headless", los controllers y el esquema SQL de este documento siguen siendo válidos; solo cambiarían las vistas por respuestas JSON.

---

## 3. Modelo de datos

### 3.1 Entidades (derivadas de `src/types.ts` y `firebaseSync.ts`)

- **user** — usuario del sistema (lo aporta Shield).
- **company** — empresa/tenant. Un usuario puede tener varias.
- **invoice** — factura/cartera. Pertenece a una empresa.
- **reminder_log** — registro de recordatorio enviado. Pertenece a una empresa (y referencia una factura).
- **channels_config** — configuración de canales + IA. Una fila por empresa.

Relaciones:
```
user (1) ──< company (N)
company (1) ──< invoice (N)
company (1) ──< reminder_log (N)
company (1) ── channels_config (1)
```

### 3.2 Esquema SQL (Migrations de CI4)

```sql
-- companies
CREATE TABLE companies (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,              -- dueño (Shield users.id)
  name          VARCHAR(255) NOT NULL,              -- Razón social
  nit           VARCHAR(50)  NOT NULL,              -- NIT / identificación fiscal
  address       VARCHAR(255) NULL,
  email         VARCHAR(255) NULL,                  -- correo de cobranzas
  phone         VARCHAR(50)  NULL,
  created_at    DATETIME NULL,
  updated_at    DATETIME NULL,
  INDEX idx_companies_user (user_id)
);

-- invoices
CREATE TABLE invoices (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id        INT UNSIGNED NOT NULL,
  invoice_number    VARCHAR(50)  NOT NULL,          -- "id" en React (ej. FE344)
  type              VARCHAR(120) NULL,              -- Tipo de documento
  emission_date     DATE NULL,                      -- YYYY-MM-DD
  due_date          DATE NULL,                      -- YYYY-MM-DD (calculada)
  nit               VARCHAR(50)  NULL,              -- NIT del receptor/cliente
  client_name       VARCHAR(255) NOT NULL,
  subtotal          DECIMAL(15,2) NOT NULL DEFAULT 0,
  tax               DECIMAL(15,2) NOT NULL DEFAULT 0,  -- IVA
  total             DECIMAL(15,2) NOT NULL DEFAULT 0,
  payment_method    VARCHAR(60)  NULL,              -- Contado / Crédito
  electronic_status VARCHAR(60)  NULL,              -- Estado DIAN (Distribuido, Aprobado...)
  client_email      VARCHAR(255) NULL,
  client_phone      VARCHAR(60)  NULL,
  description        TEXT NULL,                     -- Detalle productos/servicios
  issuer_nit        VARCHAR(50)  NULL,             -- NIT emisor
  payment_status    ENUM('paid','pending','overdue','cancelled') NOT NULL DEFAULT 'pending',
  notes             TEXT NULL,                      -- Comentarios privados de seguimiento
  observations      TEXT NULL,                      -- Observaciones internas
  reminders_count   INT UNSIGNED NOT NULL DEFAULT 0,
  last_reminder_date DATE NULL,
  created_at        DATETIME NULL,
  updated_at        DATETIME NULL,
  UNIQUE KEY uq_invoice (company_id, invoice_number),  -- doc id = company_id + invoice_number
  INDEX idx_invoices_company (company_id),
  INDEX idx_invoices_status (company_id, payment_status)
);

-- reminder_logs
CREATE TABLE reminder_logs (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id     INT UNSIGNED NOT NULL,
  invoice_number VARCHAR(50) NOT NULL,              -- referencia a invoices.invoice_number
  client_name    VARCHAR(255) NOT NULL,
  sent_date      DATE NOT NULL,
  channel        ENUM('email','whatsapp') NOT NULL,
  tone           ENUM('friendly','firm','urgent') NOT NULL,
  content        TEXT NOT NULL,
  status         ENUM('sent','pending') NOT NULL DEFAULT 'sent',
  created_at     DATETIME NULL,
  INDEX idx_logs_company (company_id),
  INDEX idx_logs_date (company_id, sent_date)
);

-- channels_config (1 fila por empresa)
CREATE TABLE channels_config (
  company_id        INT UNSIGNED PRIMARY KEY,
  -- SMTP
  smtp_host         VARCHAR(255) NULL,
  smtp_port         INT NULL,
  smtp_user         VARCHAR(255) NULL,
  smtp_pass         VARCHAR(255) NULL,              -- cifrar (ver §9)
  smtp_sender_name  VARCHAR(255) NULL,
  smtp_sender_email VARCHAR(255) NULL,
  smtp_security     ENUM('ssl','starttls','none') NULL DEFAULT 'starttls',
  -- WhatsApp
  wa_provider       ENUM('meta','twilio','other') NULL DEFAULT 'meta',
  wa_endpoint       VARCHAR(255) NULL,
  wa_phone_id       VARCHAR(120) NULL,
  wa_token          VARCHAR(500) NULL,              -- cifrar
  -- SMS
  sms_provider      ENUM('twilio','infobip','other') NULL DEFAULT 'twilio',
  sms_account_sid   VARCHAR(120) NULL,
  sms_auth_token    VARCHAR(255) NULL,              -- cifrar
  sms_sender_id     VARCHAR(60)  NULL,
  -- IA
  ai_provider       ENUM('gemini','openai') NOT NULL DEFAULT 'gemini',
  updated_at        DATETIME NULL
);
```

> **Nota de multiempresa:** cada consulta debe filtrarse por `company_id` de la empresa activa del usuario. En React esto lo hacía cada función de `firebaseSync.ts` con `where('companyId','==',...)`. En CI4 conviene un método base en el Model (ej. `forCompany($companyId)`) o un filtro/trait que aplique el `where` automáticamente.

---

## 4. Reglas de negocio clave (portar con exactitud)

### 4.1 Cálculo de días de mora (`daysOverdue`)
Se recalcula **dinámicamente** en varias vistas. Lógica (de `InvoiceTable.tsx`, `InvoiceDetails.tsx`, `App.tsx`):

```
si payment_status es 'paid' o 'cancelled' → daysOverdue = 0
si no:
  diff = HOY - due_date   (en días, redondeado hacia arriba)
  daysOverdue = max(diff, 0)
```

⚠️ **Importante:** el proyecto actual **fija "HOY" a `2026-07-05`** (hardcodeado) en `App.tsx`, `InvoiceTable.tsx` e `InvoiceDetails.tsx`, mientras que el parser usa la fecha real del sistema. En CI4 usar `Time::now()` real y **eliminar el hardcode**, salvo que se quiera reproducir el comportamiento demo.

### 4.2 Estado "activo" para KPIs
Al calcular los KPIs (`App.tsx` → `summary`), una factura `pending` cuya `due_date` ya pasó se cuenta como **overdue** para el resumen (aunque su `payment_status` almacenado siga siendo `pending`):

```
activeStatus = (daysOverdue > 0 && status != 'paid' && status != 'cancelled') ? 'overdue' : status
```

KPIs a calcular (ver `StatsOverview.tsx`):
- **Recaudado** = Σ total de `paid`; conteo de `paid`.
- **Vencido/Mora** = Σ total de `overdue`; conteo.
- **Pendiente** = Σ total de `pending` (por vencer); conteo.
- **Eficiencia de recaudo** = `paidAmount / (paid + pending + overdue) * 100`.
- **% mora** = `overdueAmount / (paid + pending + overdue) * 100`.
- `cancelled` **no** entra en montos, solo se cuenta.

### 4.3 Importación CSV/Excel → Facturas (`parser.ts`)
Al importar, replicar `mapRowsToInvoices`:

1. **Normalizar cabeceras**: minúsculas, quitar acentos (NFD), quitar todo lo no alfanumérico. Así `"Número de Factura"` → `numerodefactura`.
2. **Mapear por alias** (primer alias que exista gana). Columnas y alias:

   | Campo | Alias aceptados |
   |---|---|
   | invoice_number | `Numero de Factura`, `Factura`, `Invoice Number`, `No`, `Numero`, `Id` |
   | type | `Tipo de Documento`, `Tipo Documento`, `Document Type`, `Tipo` |
   | emission_date | `Fecha de Emision`, `Fecha Emision`, `Fecha`, `Emission Date`, `Date` |
   | nit | `NIT`, `Nit Receptor`, `NIT Cliente`, `Identification` |
   | client_name | `Receptor`, `Cliente`, `Client`, `Customer`, `Nombre Receptor` |
   | subtotal | `Subtotal`, `Base`, `Base Imponible` |
   | tax | `IVA`, `Tax`, `Impuesto` |
   | total | `Importe Total`, `Total`, `Monto Total`, `Importe`, `Amount` |
   | payment_method | `Forma de Pago`, `Forma Pago`, `Payment Method`, `Metodo Pago` |
   | electronic_status | `Estado`, `Estado DIAN`, `DIAN Status`, `Status` |
   | client_email | `Correo Cliente`, `Email Cliente`, `Correo`, `Email`, `Mail` |
   | client_phone | `Telefono Cliente`, `Telefono`, `Phone`, `Celular` |
   | description | `Detalle Productos/Servicios`, `Detalle`, `Descripcion`, `Concepto`, `Services` |
   | issuer_nit | `NIT Emisor`, `NIT Empresa`, `Issuer NIT` |

3. **Saltar** filas sin `invoice_number` o totalmente vacías.
4. **Limpiar números**: quitar todo lo que no sea dígito, punto o guion, luego `parseFloat` (NaN → 0).
5. **Limpiar teléfono**: quitar espacios (`"3 1 5 ..."` → `"315..."`).
6. **Parsear fecha de emisión**: soporta `DD/MM/YYYY`, `DD-MM-YYYY` y `YYYY-MM-DD` (si el primer segmento tiene 4 dígitos es año primero).
7. **Calcular vencimiento**: `due_date = emission_date + (payment_method contiene "credito" ? 30 : 15) días`.
8. **Estado inicial** (`payment_status`):
   - `type` contiene "nota de credito" → `cancelled`.
   - `electronic_status` == "aprobado" → `paid`.
   - Si `due_date` < hoy → heurística demo por último dígito del número: `%3==0`→`paid`, `%3==1`→`pending`, resto→`overdue`.
   - Si no → `pending`.
9. **Valores por defecto**: `client_name`→"Cliente Desconocido", `payment_method`→"Contado", `electronic_status`→"Procesado", `client_email`→"correo@cliente.com", `client_phone`→"0000000000", `description`→"Sin descripción de servicios", `issuer_nit`→"900719705", `reminders_count`→0, `last_reminder_date`→null.

> En CI4, esto es un **servicio** `InvoiceImporter` que recibe el archivo (CSV con parser propio o `PhpSpreadsheet` para xlsx) y devuelve un array de facturas listo para `insertBatch`. El parser CSV actual maneja comillas y comillas dobles escapadas (ver `parser.ts` líneas 8-49); PHP puede usar `fgetcsv`/`str_getcsv`.

### 4.4 Auto-seed de datos demo
Cuando una empresa no tiene facturas, se cargan datos demo desde `src/data/rawCSV.ts` (14 columnas, facturas `FE3xx`). En CI4 esto puede ser un **Seeder** opcional o un botón "Restablecer datos iniciales" (`FileImporter` → `onResetToDefault`). Mantenerlo es opcional; útil para pruebas.

### 4.5 Cálculos de renovación (`RenewalQuoteModal.tsx`)
```
adjustmentAmount = subtotal * (incrementPercent / 100)
newSubtotal      = max(0, subtotal + adjustmentAmount)
newTax           = newSubtotal * 0.19        -- IVA 19%
newTotal         = newSubtotal + newTax
startDate por defecto = due_date + 1 día
incrementPercent por defecto = 5 (%); presets: 0, 5, 10, -5
```

---

## 5. Rutas y controllers (CI4)

Prefijo sugerido de módulo: `/cobros`. Todas las rutas protegidas por auth (Shield) + filtro de empresa activa.

### 5.1 Vistas (páginas)
| Método | Ruta | Controller::método | Descripción |
|---|---|---|---|
| GET | `/cobros` | `Cobros::index` | Dashboard: importador + KPIs + tabla + detalle |
| GET | `/cobros/estrategia` | `Cobros::strategy` | Pestaña "Plan Estratégico AI" |

### 5.2 Facturas (CRUD + acciones)
| Método | Ruta | Método | Descripción |
|---|---|---|---|
| GET | `/cobros/invoices` | `Invoices::list` | Listado JSON (filtros, orden, paginación) para AJAX |
| POST | `/cobros/invoices/import` | `Invoices::import` | Subir CSV/Excel y mapear |
| POST | `/cobros/invoices/{num}/update` | `Invoices::update` | Actualizar estado/contacto/notas/observaciones |
| POST | `/cobros/invoices/reset` | `Invoices::resetToSeed` | Restablecer datos demo |
| GET | `/cobros/invoices/export/{fmt}` | `Invoices::export` | Exportar xlsx/csv |

### 5.3 Empresas (tenants)
| Método | Ruta | Método |
|---|---|---|
| GET | `/cobros/companies` | `Companies::list` |
| POST | `/cobros/companies` | `Companies::create` |
| POST | `/cobros/companies/select` | `Companies::select` (guarda empresa activa en sesión/perfil) |

### 5.4 Configuración de canales
| Método | Ruta | Método |
|---|---|---|
| GET | `/cobros/config` | `ChannelsConfig::show` |
| POST | `/cobros/config` | `ChannelsConfig::save` |
| POST | `/cobros/config/test` | `ChannelsConfig::test` (simulado, ver nota) |

### 5.5 IA (proxy) — equivalentes a `server.ts`
| Método | Ruta | Método | Reemplaza a |
|---|---|---|---|
| POST | `/cobros/ai/reminder` | `Ai::reminder` | `POST /api/gemini/reminder` |
| POST | `/cobros/ai/strategy` | `Ai::strategy` | `POST /api/gemini/strategy` |
| POST | `/cobros/ai/renewal` | `Ai::renewal` | `POST /api/gemini/renewal` |

> **Reminders/logs:** al "enviar" un recordatorio (`InvoiceDetails.handleRecordSentReminder`) se crea un `reminder_log` y se incrementa `reminders_count` + `last_reminder_date` de la factura. El envío real abre `mailto:` o `https://api.whatsapp.com/send?phone=...&text=...` en el navegador (esto se queda en JS del cliente; el backend solo persiste el log).
>
> **Test de conexión de canales:** en el proyecto actual es **simulado** (siempre responde éxito tras 1.5s, ver `ChannelConfigModal.handleTestConnection`). Puedes dejarlo simulado o implementar pruebas reales de SMTP/API.

---

## 6. Especificación de la IA (portar los 3 endpoints)

El servidor actual (`server.ts`) tiene un despachador `generateAIResponse(prompt, provider, temperature)`:
- **Gemini** (default): modelo `gemini-3.5-flash`.
- **OpenAI**: modelo `gpt-4o-mini`, `messages:[{role:'user',content:prompt}]`.
- El `provider` viene en el body de cada request (lo elige el usuario en la config de IA).

En CI4: un servicio `AiService` que, según `ai_provider`, hace `POST` con `CURLRequest` a la API correspondiente usando `GEMINI_API_KEY` / `OPENAI_API_KEY` del `.env`. **Las claves nunca se exponen al navegador.**

### 6.1 `POST /cobros/ai/reminder` — Recordatorio de cobro
**Entrada:** `invoiceId, clientName, total, dueDate, description, channel('email'|'whatsapp'), tone('friendly'|'firm'|'urgent'), daysOverdue, provider`
**Salida:** `{ "text": "<mensaje>" }`  · Temperatura `0.7`

Construcción del prompt (resumen; texto literal en `server.ts` líneas 62-135):
- Formatear `total` como COP (`es-CO`, sin decimales).
- `isOverdue = daysOverdue > 0`.
- **Instrucción de tono** (friendly/firm/urgent): párrafos definidos en `server.ts`.
- **Formato de canal**:
  - `whatsapp`: máx 150 palabras, conciso, saltos de línea, emojis profesionales (📝 📅 💳 🔔), negritas con `*...*`, sin asunto de correo.
  - `email`: estructura de correo formal (Asunto, saludo, cuerpo, detalles de factura, instrucciones de pago, despedida/firma).
- **Reglas**: español natural de Colombia/LATAM; no inventar cuentas bancarias (usar marcadores `[Insertar Cuenta Bancaria aquí]`); datos de la factura con precisión; devolver SOLO el mensaje (sin "Aquí tienes...").

### 6.2 `POST /cobros/ai/strategy` — Plan estratégico de cartera
**Entrada:** `totalOverdue, totalPending, totalPaid, overdueCount, pendingCount, criticalClients[], provider`
- `criticalClients`: top 5 clientes por monto vencido, cada uno `{name, amount, daysMaxOverdue, invoiceCount}` (calculado en el cliente, ver `AIStrategyPanel.tsx` líneas 15-75).
**Salida:** `{ "strategyMarkdown": "<markdown>" }`  · Temperatura `0.6`

Prompt (server.ts 165-183): reporte en **Markdown** con 4 secciones: (1) Análisis de estado de cartera, (2) Prioridades de acción, (3) Plan de acción por antigüedad (1-15 amigable, 16-45 firme, +45 especial), (4) Recomendación operativa.

> El frontend renderiza ese Markdown con un mini-parser propio (`AIStrategyPanel.renderMarkdown`, soporta `#/##/###`, `**negrita**`, listas `-` y `1.`). En CI4 puedes usar un parser Markdown de PHP (ej. `Parsedown`) o replicar el mini-parser.

### 6.3 `POST /cobros/ai/renewal` — Propuesta de renovación
**Entrada:** `invoiceId, clientName, description, total, incrementPercent, period, duration, startDate, notes, provider`
**Salida:** `{ "proposalText": "<markdown>" }`  · Temperatura `0.7`

Prompt (server.ts 216-242): propuesta comercial formal por correo ejecutivo, persuasiva, con saludo, agradecimiento, condiciones (servicios, frecuencia, tarifa propuesta, fecha de inicio), llamado a la acción, despedida/firma. Calcula `newTotal = total * (1 + incrementPercent/100)`.

**Manejo de errores IA:** los endpoints devuelven `500 { error: "..." }` con mensaje en español; el frontend lo muestra en un banner rojo. Validar parámetros obligatorios y devolver `400` si faltan (ver reminder: exige `invoiceId, clientName, total`).

---

## 7. Interfaz (vistas a construir con Tailwind)

Reaprovechar el HTML/clases Tailwind de los componentes React (son casi 1:1). Pantallas y componentes:

1. **Auth** (`AuthScreen.tsx`) → login/registro con Shield. Campos registro: nombre, teléfono, email, contraseña. Mensajes de error localizados en español.
2. **Onboarding primera empresa** (`App.tsx` 317-397) → formulario nombre + NIT cuando el usuario no tiene empresas.
3. **Header** (`App.tsx` 417-464) → logo "Cobros Inteligentes", selector de empresa (`CompanyManager`), botón "Ajustes e IA", "Salir".
4. **Importador** (`FileImporter.tsx`) → zona drag & drop (.csv/.xlsx/.xls), botón "Restablecer datos iniciales", estados idle/loading/success/error.
5. **KPIs / Bento** (`StatsOverview.tsx`) → 4 tarjetas con barra de progreso: Recaudado, Cartera vencida, Pendiente, Eficiencia.
6. **Tabla de facturas** (`InvoiceTable.tsx`):
   - Búsqueda por id/cliente/NIT/descripción.
   - Filtro por forma de pago (Todos/Contado/Crédito).
   - Pestañas por estado con conteos: Todas, Pagadas, En Mora, Pendientes, Anuladas.
   - Orden por: id, cliente, fecha emisión, total, días de mora (asc/desc).
   - Paginación: **8 por página**.
   - Columnas: Factura (+forma de pago), Cliente (+NIT), Fechas (emisión/venc.), Importe (+IVA), Mora (badge días), Estado (badge).
7. **Detalle de factura** (`InvoiceDetails.tsx`):
   - Estado + total; datos emisor/receptor.
   - Editor de contacto (email/teléfono).
   - Detalle de servicios + subtotal/IVA/total + botón "Generar Cotización de Renovación".
   - Formulario de seguimiento: estado (select), observaciones, notas privadas, guardar.
   - **Generador de recordatorios IA**: canal (correo/WhatsApp), tono (amigable/firme/urgente), botón generar, salida, copiar, enviar (mailto/WhatsApp), registrar envío manual.
8. **Panel estrategia AI** (`AIStrategyPanel.tsx`) → diagnóstico rápido + clientes críticos + botón generar plan + render Markdown.
9. **Modal cotización renovación** (`RenewalQuoteModal.tsx`) → parámetros (ajuste %, período, duración, fecha inicio, notas) + preview de cotización imprimible (membrete "Sistemas Digitales S.A.S.") + pestaña "Carta de Propuesta AI" + imprimir/guardar PDF (`window.print`).
10. **Modal config de canales** (`ChannelConfigModal.tsx`) → pestañas SMTP / WhatsApp / SMS / Motor de IA; botón "Probar Conexión" (simulado) y "Guardar".
11. **Historial de recordatorios** (`App.tsx` 573-605) → lista de `reminder_logs` recientes.

> **Interactividad:** las partes dinámicas (generar IA sin recargar, filtros/orden/paginación de tabla, modales) requieren JS. Opciones: fetch/AJAX puro, **Alpine.js** (ligero, encaja con Tailwind) o **HTMX**. La generación de IA debe llamar a `/cobros/ai/*` y pintar el resultado sin recargar.

---

## 8. Formato de exportación (`exporters.ts`)

**Excel** (`exportInvoicesToExcel`) — hoja "Facturas", cabeceras en español:
`Número de Factura, Tipo de Documento, Fecha de Emisión, Fecha de Vencimiento, NIT Cliente, Receptor / Cliente, Subtotal, IVA, Importe Total, Forma de Pago, Estado DIAN, Estado de Cobro, Correo Cliente, Teléfono Cliente, Detalle de Productos/Servicios, Recordatorios Enviados, Notas de Seguimiento`. Archivo: `Gestion_Cobros_Facturas.xlsx`.

**CSV** (`exportInvoicesToCSV`) — cabeceras: `Numero de Factura, Tipo de Documento, Fecha de Emision, Fecha de Vencimiento, NIT, Receptor, Subtotal, IVA, Importe Total, Forma de Pago, Estado DIAN, Correo Cliente, Telefono Cliente, Detalle Productos/Servicios, NIT Emisor, Estado Cobro, Recordatorios Enviados, Notas`. Con **BOM UTF-8** para acentos en Excel. Archivo: `Gestion_Cobros_Facturas.csv`.

En CI4: `PhpSpreadsheet` para xlsx; para CSV, generar con `\xEF\xBB\xBF` (BOM) + `fputcsv`.

---

## 9. Configuración, entorno y seguridad

`.env` de CI4:
```
GEMINI_API_KEY=...        # requerida para Gemini
OPENAI_API_KEY=...        # opcional, solo si ai_provider='openai'
```
- Las claves de IA viven **solo en el servidor** (nunca en vistas/JS).
- **Cifrar** en BD los secretos de canales (`smtp_pass`, `wa_token`, `sms_auth_token`) con el `Encryption` de CI4.
- **Aislamiento multiempresa**: validar SIEMPRE que la empresa activa pertenece al usuario autenticado antes de leer/escribir facturas, logs o config. Nunca confiar en un `company_id` recibido del cliente sin verificar propiedad.
- El `firebase-applet-config.json` y toda dependencia de Firebase/Node **se eliminan** en la versión CI4.
- Formato de moneda: COP, locale `es-CO`, sin decimales (equivalente a `Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0})`). En PHP: `NumberFormatter('es_CO', NumberFormatter::CURRENCY)` (ext-intl) o formateo manual con separador de miles.

---

## 10. Plan de implementación por fases

**Fase 0 — Andamiaje**
- Instalar Shield; configurar conexión MySQL; crear el módulo `Cobros` (namespace/rutas/filtros).

**Fase 1 — Datos y multiempresa**
- Migrations (`companies`, `invoices`, `reminder_logs`, `channels_config`).
- Models + relación con usuario; selector de empresa activa (sesión) + onboarding.

**Fase 2 — Facturas (core)**
- CRUD, tabla con filtros/orden/paginación (8/pág), detalle, edición de seguimiento.
- KPIs (§4.2) y badges de estado/mora (§4.1).

**Fase 3 — Importación / Exportación**
- Servicio de importación CSV/Excel (§4.3) + subida; exportadores xlsx/csv (§8).
- Seed/reset demo opcional (§4.4).

**Fase 4 — IA (proxy)**
- `AiService` (Gemini/OpenAI) + los 3 endpoints (§6) + integración en detalle y estrategia.
- Registro de `reminder_logs` y apertura de mailto/WhatsApp desde el cliente.

**Fase 5 — Config de canales + renovación**
- Modal/pantalla de canales (SMTP/WhatsApp/SMS/IA), cifrado de secretos, test (simulado o real).
- Cotización de renovación (§4.5) + impresión PDF.

**Fase 6 — Pulido**
- Interactividad (Alpine/HTMX/AJAX), validaciones, mensajes en español, revisión de aislamiento por tenant.

---

## 11. Referencia rápida de archivos fuente (proyecto React actual)

| Archivo | Qué contiene (para consultar detalles al portar) |
|---|---|
| `src/types.ts` | Tipos: `Invoice`, `Company`, `UserProfile`, `ReminderLog`, `ChannelsConfig`, `CollectionSummary` |
| `server.ts` | Los 3 prompts de IA y el despachador Gemini/OpenAI (texto literal de prompts) |
| `src/utils/parser.ts` | Parser CSV, lectura Excel, `mapRowsToInvoices` (mapeo de cabeceras y reglas) |
| `src/utils/exporters.ts` | Cabeceras y formato de export xlsx/csv |
| `src/App.tsx` | Orquestación, KPIs (`summary`), auto-seed, flujo auth/empresa |
| `src/components/InvoiceTable.tsx` | Filtros, orden, paginación, cálculo de mora |
| `src/components/InvoiceDetails.tsx` | Edición de seguimiento, generación de recordatorio, logs |
| `src/components/AIStrategyPanel.tsx` | Cálculo de clientes críticos + render Markdown |
| `src/components/RenewalQuoteModal.tsx` | Cálculos de renovación + plantilla imprimible |
| `src/components/ChannelConfigModal.tsx` | Estructura de config de canales + selección de IA |
| `src/components/FileImporter.tsx` | Flujo de importación (drag & drop) |
| `src/components/CompanyManager.tsx` / `AuthScreen.tsx` | Multiempresa y autenticación |
| `src/data/rawCSV.ts` | Dataset demo (14 columnas, facturas FE3xx) |
```
