# Prompt de Réplica — Plataforma de Carnetización Digital Corporativa (SaaS Multi-Tenant)

> Documento generado a partir del análisis del proyecto `corporate-admin-pro` (carpeta `carnetr`).
> Úsalo como **prompt maestro** para que un modelo de IA (o un equipo de desarrollo) reconstruya la plataforma desde cero, con el mismo stack, servicios y arquitectura.

---

## 1. Resumen ejecutivo

Construye una **plataforma SaaS multi-tenant de carnetización digital corporativa**. Permite a múltiples empresas (tenants) emitir, gestionar y verificar **carnets/credenciales digitales** con código QR, además de ofrecer módulos complementarios de comunicación interna (boletines), atención al usuario (PQRS) y micro-aprendizaje (e-learning) con evaluaciones.

El sistema tiene **3 niveles de roles**:
- **Super Admin** → administra toda la plataforma: empresas, planes, usuarios admin, métricas globales.
- **Admin** → administra su empresa: carnets, áreas, plantillas, boletines, PQRS, e-learning, configuración.
- **User / Público** → consulta carnets, verifica credenciales por QR, accede a cápsulas de aprendizaje (rutas públicas sin login).

---

## 2. Stack tecnológico (obligatorio para réplica fiel)

### Frontend
- **React 19** + **TypeScript 5.8**
- **Vite 6** como bundler (puerto dev `3000`, host `0.0.0.0`)
- **TailwindCSS** vía CDN (`https://cdn.tailwindcss.com`) con `darkMode: 'class'` y color de marca dinámico (`--brand-color`)
- Fuente **Inter** (Google Fonts)
- **react-router-dom 7** (aunque el ruteo principal es manual por `window.location.pathname` con `match` de regex)
- **lucide-react** para iconos
- **recharts 3** para gráficas/analítica
- **@tiptap/react** (StarterKit + Link + Placeholder) como editor de texto enriquecido (HTML)
- **qrcode** (generación) + **react-qr-scanner** (escaneo de QR)
- **date-fns 3** para fechas

### Backend / Servicios (BaaS)
- **Firebase 11**:
  - **Firebase Auth** → autenticación email/contraseña (login, registro, recuperación de contraseña)
  - **Cloud Firestore** → base de datos NoSQL (inicializado con `ignoreUndefinedProperties: true`)
  - **Firebase Storage** → almacenamiento (secundario)
  - **Firebase Cloud Messaging** → notificaciones push (inicializado solo si `isSupported()`)
- **Cloudflare R2** (compatible con S3, vía `@aws-sdk/client-s3`) → almacenamiento principal de imágenes (logos, fotos de carnets) con URL pública
- **Google Gemini** (`@google/genai`, modelo `gemini-1.5-flash`) → generación de resúmenes ejecutivos inteligentes en el dashboard

> **Nota de modelo IA:** El proyecto original usa `gemini-1.5-flash`. Si se desea un asistente de mayor calidad, considerar modelos Claude (p.ej. `claude-haiku-4-5` para resúmenes rápidos y económicos, o `claude-sonnet-4-6` para análisis más profundos) vía la API de Anthropic. El servicio de IA es modular y aislado, así que es intercambiable.

---

## 3. Variables de entorno (`.env.local`)

Prefijo `VITE_` para exponerlas al cliente:

```env
# IA (resúmenes del dashboard)
VITE_GEMINI_API_KEY=PLACEHOLDER_API_KEY

# Firebase
VITE_FIREBASE_API_KEY=...
VITE_FIREBASE_AUTH_DOMAIN=<project>.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=<project-id>
VITE_FIREBASE_STORAGE_BUCKET=<project>.firebasestorage.app
VITE_FIREBASE_MESSAGING_SENDER_ID=...
VITE_FIREBASE_APP_ID=1:...:web:...

# Cloudflare R2 (almacenamiento de imágenes)
VITE_R2_ACCESS_KEY_ID=...
VITE_R2_SECRET_ACCESS_KEY=...
VITE_R2_ENDPOINT=https://<account>.r2.cloudflarestorage.com
VITE_R2_BUCKET_NAME=carnet
VITE_R2_PUBLIC_URL=https://pub-<hash>.r2.dev
```

> ⚠️ **Seguridad:** En el proyecto original las credenciales de R2 y Firebase están expuestas en el cliente. Para producción, mover las operaciones de subida/firma a un backend o Cloudflare Worker, y restringir reglas de Firestore/Storage por rol y `companyId`.

---

## 4. Arquitectura de carpetas

```
/
├── App.tsx                  # Layout principal + ruteo manual (público vs autenticado) + render por rol
├── AppContext.tsx           # Contexto global: settings, user, sidebar, empresa seleccionada
├── index.tsx / index.html   # Entry point (Tailwind CDN, importmap, fuente Inter)
├── types.ts                 # Todos los modelos de datos (interfaces TS)
├── vite.config.ts           # Config Vite (puerto 3000, alias @, define GEMINI key)
├── config/
│   └── firebase.ts          # Init de Firebase (auth, db, storage, messaging)
├── services/
│   ├── auth.ts              # Login/registro/reset/observer (Firebase Auth) + mensajes de error en español
│   ├── firestore.ts         # CRUD completo de TODAS las colecciones + operaciones complejas/batch
│   ├── storage.ts           # Subida/borrado de archivos a Cloudflare R2 (S3 SDK)
│   └── gemini.ts            # generateSmartReport() — resumen IA del dashboard
├── hooks/
│   ├── useApp.ts            # Acceso al AppContext
│   └── useRoleAccess.ts     # isSuperAdmin / isAdmin / isUser / companyId resuelto
├── components/
│   ├── Sidebar.tsx, Header.tsx, FloatingContact.tsx, ProtectedRoute.tsx
│   ├── TemplateRenderer.tsx # Renderiza el diseño visual del carnet
│   ├── Admin/  SuperAdmin/  # Componentes específicos por rol
├── pages/
│   ├── Login, Register, ForgotPassword, Dashboard, Profile, Settings, Users
│   ├── Admin/      → Areas, Carnets, Bulletins, CompanySettings, Templates, PQRS, Learning, Analytics
│   ├── SuperAdmin/ → AdminUsers, Plans, SuperDashboard, CompanyManagement
│   └── Public/     → PublicCarnetView, PublicCheckView, LearningView
└── utils/
    ├── imageOptimizer.ts    # Optimización de imágenes antes de subir
    ├── qrGenerator.ts       # Generación de QR
    └── tokenGenerator.ts    # Tokens de seguridad para empresas
```

---

## 5. Modelo de datos (Firestore)

Colecciones y sus interfaces (ver `types.ts`):

| Colección | Descripción | Campos clave |
|---|---|---|
| `users` | Usuarios del sistema | `id, name, email, phone, role (superadmin/admin/user), companyId` |
| `companies` | Empresas/tenants | `id, name, logo, planId, ownerId, securityToken, usage{}, subscription{}, customLimits?, enabledServices{bulletins,pqrs,learning}` |
| `plans` | Planes de suscripción | `id, name, limits{maxCompanies,maxTemplates,maxCarnets,maxAreas,serviceDays}, pricing{amount,currency}, features[], isActive` |
| `subscriptions` | Registro de suscripciones | `companyId, planId, startDate, endDate, status` |
| `areas` | Áreas/departamentos | `id, companyId, name, color, icon, notificationSettings{pushEnabled,emailEnabled}` |
| `carnets` | Credenciales digitales | `id, companyId, templateId?, areaId?, dni, firstName, lastName, photo, email, phone, role, validFrom, validUntil, qrCode, status(active/expired/suspended), photoStatus, customFields{}` |
| `templates` | Plantillas de diseño de carnet | `id, companyId, name, layout{backgroundColor,textColor,accentColor,logoPosition,showPhoto,showQR}, fields{nombre,apellido,dni,cargo,area,empresa,vencimiento,customFields[]}` |
| `bulletin_categories` | Categorías de boletines | `id, companyId, name, color` |
| `bulletins` | Boletines/comunicados | `id, companyId, title, content(HTML), imageUrl, linkUrl, areaId, categoryId, status(draft/published/trash), isHighlighted, startDate, endDate` |
| `pqrs_subjects` | Asuntos PQRS | `id, companyId, name, responsibleEmail` |
| `pqrs` | Peticiones/Quejas/Reclamos/Sugerencias | `id, companyId, type(peticion/queja/reclamo/sugerencia/felicitacion), subjectId, description, carnetId, status(pendiente/en_proceso/resuelto/cerrado), priority(baja/media/alta), responses[]` |
| `learning_topics` | Tópicos de e-learning | `id, companyId, name, color, icon` |
| `learning_capsules` | Cápsulas de aprendizaje | `id, companyId, topicId, title, objective, content(HTML), imageUrl, videoUrl(YouTube), externalUrl, questions[{question,options[],correctIndex}], status(draft/published)` |
| `learning_progress` | Trazabilidad de aprendizaje | `userId(DNI), companyId, capsuleId, type(view/evaluation), timestamp, score, total, answers[]` |
| `metrics/global` | Métricas globales | `totalUsers, totalCompanies, totalCarnets, totalAreas, totalTemplates, activeSubscriptions, revenue, newSignups, planDistribution{}` |

**Patrones de Firestore aplicados:**
- Todo documento lleva `createdAt` / `updatedAt` con `serverTimestamp()`.
- Al crear carnets/áreas/plantillas se **incrementa** el contador `usage.currentX` de la empresa (`increment(1)`), y se decrementa al borrar.
- Operaciones masivas con `writeBatch` (`bulkDeleteCarnets`, `bulkUpdateCarnets`).
- Consultas multi-tenant siempre filtradas por `where('companyId', '==', companyId)`.
- `registerAdminWithCompany()` y `createCompanyBase()`: crean empresa + suscripción (+ admin) con trial de 30 días por defecto.

---

## 6. Funcionalidades por rol

### Super Admin
- Dashboard global con métricas agregadas (usuarios, empresas, carnets, ingresos, distribución de planes).
- CRUD de **planes** (límites, precios, features).
- CRUD/gestión de **empresas** (asignar plan, límites personalizados, servicios habilitados).
- Gestión de **usuarios admin**.
- Selector de empresa activa (`selectedCompanyId`) para operar "como" cualquier tenant.

### Admin (por empresa)
- **Carnets:** crear/editar/eliminar, carga masiva, asignar plantilla y área, estado ocupado/disponible, aprobación de fotos pendientes (`photoStatus: none/pending/rejected`), generación de QR.
- **Plantillas:** diseñador visual (colores, posición de logo, mostrar foto/QR, campos visibles + campos personalizados).
- **Áreas:** departamentos con color/icono y settings de notificación.
- **Boletines:** editor TipTap (HTML), categorías, destacados, programación (start/end), estados draft/published/trash.
- **PQRS:** asuntos con email responsable, gestión de solicitudes, respuestas con adjuntos, estados y prioridad.
- **E-Learning:** tópicos → cápsulas (contenido HTML, video YouTube, imagen, evaluación con preguntas de opción múltiple), trazabilidad de progreso.
- **Analítica:** gráficas con recharts.
- **Configuración de empresa:** identidad, branding, contacto.
- **Asistente IA:** resumen ejecutivo del dashboard generado con Gemini.

### Público (sin autenticación)
- `/v/:carnetId` → **PublicCarnetView**: vista pública del carnet digital con QR.
- `/check/:companyId` → **PublicCheckView**: verificación/escaneo de credenciales por la empresa.
- `/learning/:companyId/:dni` → **LearningView**: acceso del empleado a las cápsulas de aprendizaje y evaluaciones, identificándose con su DNI.

---

## 7. Lógica de autenticación y sesión

- `AppContext` escucha `onAuthStateChanged` de Firebase. Al autenticarse, busca el perfil en Firestore (`users/{uid}`).
- Hay **admin hardcodeados** por email (`admin@carnetdigital.com`, `admin@nova-corporate.com`, `admin`) que se elevan a `superadmin`. *(Reemplazar por claims/roles reales en producción.)*
- Si no existe perfil en Firestore, crea un usuario temporal desde Auth con rol `user`.
- `settings` (branding/tema) se persisten en `localStorage` (`corporate_settings`); aplican dark mode (`class`) y `--brand-color` al `documentElement`.
- `selectedCompanyId` también persiste en `localStorage`.
- El ruteo decide: rutas públicas (regex sobre pathname) → vistas públicas; sin `user` → login/register/forgot; con `user` → layout con sidebar y render por `activeTab` validado contra el rol (`useRoleAccess`).

---

## 8. Servicio de IA (intercambiable)

`services/gemini.ts` expone `generateSmartReport(dataSummary: string)`:
- Lee `VITE_GEMINI_API_KEY`; si es placeholder/vacía devuelve mensaje "no configurado".
- Llama a `gemini-1.5-flash` con un system-style prompt: *"Eres un asistente corporativo de alto nivel. Resume estos datos en 3 puntos clave accionables y breves: {datos}"*.
- Maneja errores devolviendo mensajes amables (nunca lanza al UI).

**Para replicar con Claude (Anthropic):** sustituir el cliente por `@anthropic-ai/sdk`, usar `claude-haiku-4-5` (rápido/económico) o `claude-sonnet-4-6`, manteniendo la misma firma de función y el mismo manejo de errores/placeholder.

---

## 9. Scripts y ejecución

```jsonc
// package.json
"scripts": {
  "dev": "vite",
  "build": "vite build",
  "preview": "vite preview"
}
```

```bash
npm install
# Configurar .env.local con Firebase + R2 + clave de IA
npm run dev      # http://localhost:3000
```

---

## 10. Prompt accionable (copiar/pegar a un modelo generador)

> **Construye una plataforma SaaS multi-tenant de carnetización digital corporativa** con **React 19 + TypeScript + Vite 6 + TailwindCSS (CDN, dark mode por clase, color de marca dinámico vía `--brand-color`)**. Usa **Firebase** (Auth email/contraseña, Cloud Firestore con `ignoreUndefinedProperties`, Storage y Cloud Messaging) como backend, **Cloudflare R2** (vía AWS S3 SDK) como almacenamiento principal de imágenes con URL pública, y un **servicio de IA aislado** (Gemini `gemini-1.5-flash` o Claude `claude-haiku-4-5`) que genera resúmenes ejecutivos del dashboard en 3 puntos accionables.
>
> Implementa **3 roles** (superadmin, admin, user) con un hook `useRoleAccess` y un `AppContext` global (settings persistidos en localStorage, usuario de Firebase, empresa seleccionada por el superadmin). El ruteo es manual por `window.location.pathname` con rutas públicas `/v/:carnetId`, `/check/:companyId`, `/learning/:companyId/:dni`.
>
> Modela en Firestore las colecciones: `users, companies, plans, subscriptions, areas, carnets, templates, bulletin_categories, bulletins, pqrs_subjects, pqrs, learning_topics, learning_capsules, learning_progress, metrics/global` — todas con `createdAt/updatedAt` (`serverTimestamp`), filtradas por `companyId`, con contadores `usage.currentX` incrementados/decrementados al crear/borrar y operaciones masivas con `writeBatch`.
>
> Construye los módulos: **Carnets** (con QR generado por `qrcode`, escaneo con `react-qr-scanner`, plantillas de diseño, aprobación de fotos), **Plantillas** (diseñador visual con campos configurables), **Áreas**, **Boletines** (editor TipTap → HTML, categorías, destacados, programación), **PQRS** (peticiones/quejas/reclamos/sugerencias con asuntos, responsables, respuestas, estados y prioridad), **Micro-eLearning** (tópicos → cápsulas con contenido HTML, video YouTube, evaluaciones de opción múltiple y trazabilidad de progreso por DNI), **Analítica** (recharts), y paneles de **Super Admin** (planes, empresas, usuarios admin, métricas globales). Usa **lucide-react** para iconos, **date-fns** para fechas, y la fuente **Inter**. Maneja todos los errores de Auth con mensajes en español. Configura el dev server en el puerto 3000.

---

## 11. Checklist de réplica

- [ ] Proyecto Vite + React 19 + TS con Tailwind CDN e Inter.
- [ ] Proyecto Firebase creado (Auth + Firestore + Storage + Messaging) y `config/firebase.ts`.
- [ ] Bucket Cloudflare R2 + credenciales en `.env.local` y `services/storage.ts`.
- [ ] Clave de IA configurada (`gemini.ts` o equivalente Claude).
- [ ] `types.ts` con todas las interfaces.
- [ ] `services/firestore.ts` con CRUD + operaciones complejas + batch.
- [ ] `services/auth.ts` con mensajes de error en español.
- [ ] `AppContext` + `useApp` + `useRoleAccess`.
- [ ] Ruteo manual (público vs autenticado vs por rol) en `App.tsx`.
- [ ] Páginas Admin, SuperAdmin y Public.
- [ ] Componentes Sidebar, Header, FloatingContact, TemplateRenderer.
- [ ] Utils: optimizador de imágenes, generador de QR, generador de tokens.
- [ ] Reglas de seguridad de Firestore/Storage por rol y `companyId` (producción).
```
