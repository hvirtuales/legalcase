# Sistema de Autenticación Multi-Método y Gestión de Usuarios - CodeIgniter 4

Sistema completo de autenticación con **múltiples métodos de autenticación**, gestión de usuarios, roles, permisos (RBAC) y configuración personalizable de la interfaz de login, siguiendo las especificaciones de diseño "Executive Clean" con Tailwind CSS v4.

## Métodos de Autenticación Soportados

1. **Autenticación Tradicional (Password)**: Login con usuario/email y contraseña
2. **Passwordless (Magic Link)**: Login sin contraseña mediante enlace único enviado por email
3. **Token/JWT para APIs**: Autenticación basada en tokens para consumo de APIs REST
4. **Microsoft Entra ID (Azure AD)**: Single Sign-On con OAuth2 de Microsoft
5. **LDAP/Active Directory**: Integración con directorios corporativos
6. **Autenticación de Dos Factores (2FA/TOTP)**: Capa adicional de seguridad con Google Authenticator/Authy


## Revisión del Usuario Requerida

> [!IMPORTANT]
> **Configuración de Encriptación**: Se generará una clave de encriptación segura para el archivo `.env`. Esta clave es crítica para la seguridad de las contraseñas.

> [!IMPORTANT]
> **Estructura de Permisos**: Se implementará un sistema RBAC (Role-Based Access Control) con los siguientes roles iniciales:
> - **Super Admin**: Acceso total al sistema
> - **Admin**: Gestión de usuarios y configuración
> - **Auditor**: Validación y consulta de registros de auditoría (solo lectura)
> - **Usuario**: Acceso básico al sistema
> 
> Los permisos se asignarán a nivel de roles, no directamente a usuarios.

> [!IMPORTANT]
> **Dependencias de Composer**: El sistema multi-autenticación requiere las siguientes librerías:
> - `firebase/php-jwt`: Para autenticación JWT/Token
> - `phpmailer/phpmailer`: Para envío de Magic Links y 2FA
> - `sonata-project/google-authenticator`: Para 2FA/TOTP
> - `thenetworg/oauth2-azure`: Para Microsoft Entra ID (opcional)
> 
> Para LDAP se requiere habilitar la extensión `php_ldap` en `php.ini`.

> [!WARNING]
> **Configuración de OAuth y LDAP**: Para usar Microsoft Entra ID o LDAP, necesitarás:
> - **Entra ID**: Client ID, Client Secret, Tenant ID de Azure Portal
> - **LDAP**: Host, puerto, base DN, credenciales de bind del servidor LDAP
> 
> Estos valores deben configurarse en el archivo `.env` antes de usar estos métodos.

> [!WARNING]
> **Archivos de Configuración**: Se modificará el archivo `.env` para agregar la clave de encriptación y configuraciones de autenticación. Asegúrate de hacer backup antes de proceder.


## Cambios Propuestos

### Base de Datos

#### [NEW] Migraciones

##### [NEW] [2024_12_16_000001_create_users_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000001_create_users_table.php)
Tabla principal de usuarios con campos:
- `id` (PK, auto-increment)
- `username` (único, varchar 50)
- `email` (único, varchar 100)
- `password` (hash, varchar 255)
- `first_name` (varchar 50)
- `last_name` (varchar 50)
- `is_active` (boolean, default true)
- `created_at`, `updated_at`, `deleted_at` (timestamps)
- `created_by` (FK → users, nullable) - usuario que creó el registro
- `updated_by` (FK → users, nullable) - último usuario que modificó
- `deleted_by` (FK → users, nullable) - usuario que eliminó (soft delete)

##### [NEW] [2024_12_16_000002_create_roles_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000002_create_roles_table.php)
Tabla de roles del sistema:
- `id` (PK, auto-increment)
- `name` (único, varchar 50)
- `description` (text)
- `created_at`, `updated_at`
- `created_by` (FK → users, nullable)
- `updated_by` (FK → users, nullable)

##### [NEW] [2024_12_16_000003_create_permissions_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000003_create_permissions_table.php)
Tabla de permisos disponibles:
- `id` (PK, auto-increment)
- `name` (único, varchar 100)
- `description` (text)
- `module` (varchar 50) - agrupa permisos por módulo
- `created_at`, `updated_at`
- `created_by` (FK → users, nullable)
- `updated_by` (FK → users, nullable)

##### [NEW] [2024_12_16_000004_create_role_permissions_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000004_create_role_permissions_table.php)
Tabla pivote roles-permisos:
- `id` (PK, auto-increment)
- `role_id` (FK → roles)
- `permission_id` (FK → permissions)
- Índice único compuesto (role_id, permission_id)

##### [NEW] [2024_12_16_000005_create_user_roles_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000005_create_user_roles_table.php)
Tabla pivote usuarios-roles:
- `id` (PK, auto-increment)
- `user_id` (FK → users)
- `role_id` (FK → roles)
- Índice único compuesto (user_id, role_id)

##### [NEW] [2024_12_16_000006_create_app_settings_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000006_create_app_settings_table.php)
Tabla de configuración personalizable:
- `id` (PK, auto-increment)
- `setting_key` (único, varchar 100)
- `setting_value` (text)
- `setting_type` (enum: 'text', 'image', 'boolean')
- `updated_at`

Configuraciones iniciales:
- `app_name`: Nombre del proyecto
- `app_description`: Descripción del proyecto
- `login_image`: Ruta de la imagen de login
- `login_background`: Color/imagen de fondo
- `favicon`: Ruta del archivo favicon.ico
- `auth_methods_enabled`: JSON con métodos de autenticación habilitados

##### [NEW] [2024_12_16_000007_create_auth_providers_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000007_create_auth_providers_table.php)
Tabla para vincular usuarios con proveedores externos (OAuth, LDAP):
- `id` (PK, auto-increment)
- `user_id` (FK → users)
- `provider` (varchar 50) - 'entra_id', 'ldap', 'google', etc.
- `provider_user_id` (varchar 255) - ID del usuario en el proveedor externo
- `provider_data` (JSON) - datos adicionales del proveedor
- `created_at`, `updated_at`
- `created_by` (FK → users, nullable)
- Índice único compuesto (user_id, provider)

##### [NEW] [2024_12_16_000008_create_magic_links_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000008_create_magic_links_table.php)
Tabla para tokens de Magic Link (passwordless):
- `id` (PK, auto-increment)
- `user_id` (FK → users, nullable) - puede ser null si el usuario aún no existe
- `email` (varchar 100)
- `token` (varchar 64, único) - token único generado
- `expires_at` (datetime)
- `used_at` (datetime, nullable)
- `created_at`

##### [NEW] [2024_12_16_000009_create_api_tokens_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000009_create_api_tokens_table.php)
Tabla para tokens de API (JWT):
- `id` (PK, auto-increment)
- `user_id` (FK → users)
- `name` (varchar 100) - nombre descriptivo del token
- `token` (varchar 500, único) - JWT token
- `abilities` (JSON) - permisos específicos del token
- `last_used_at` (datetime, nullable)
- `expires_at` (datetime, nullable)
- `created_at`, `updated_at`
- `created_by` (FK → users, nullable)

##### [NEW] [2024_12_16_000010_create_two_factor_auth_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000010_create_two_factor_auth_table.php)
Tabla para 2FA/TOTP:
- `id` (PK, auto-increment)
- `user_id` (FK → users, único)
- `secret` (varchar 255) - secreto encriptado para TOTP
- `recovery_codes` (JSON) - códigos de recuperación encriptados
- `enabled` (boolean, default false)
- `created_at`, `updated_at`
- `created_by` (FK → users, nullable)
- `updated_by` (FK → users, nullable)

##### [NEW] [2024_12_16_000011_add_auth_fields_to_users.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000011_add_auth_fields_to_users.php)
Migración para agregar campos de autenticación a la tabla users:
- `password_nullable` - hacer el campo password nullable (para usuarios passwordless/OAuth)
- `auth_method` (enum: 'password', 'magic_link', 'oauth', 'ldap', default 'password')
- `two_factor_enabled` (boolean, default false)
- `last_login_at` (datetime, nullable)
- `last_login_ip` (varchar 45, nullable)
- `failed_login_attempts` (int, default 0)
- `locked_until` (datetime, nullable) - para bloqueo temporal por intentos fallidos

##### [NEW] [2024_12_16_000012_create_audit_logs_table.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000012_create_audit_logs_table.php)
Tabla para registros de auditoría:
- `id` (PK, auto-increment)
- `user_id` (FK → users, nullable) - usuario que realizó la acción
- `event` (varchar 50) - tipo de evento: 'created', 'updated', 'deleted', 'login', 'logout', etc.
- `auditable_type` (varchar 100) - tipo de entidad afectada (nombre del modelo)
- `auditable_id` (bigint, nullable) - ID de la entidad afectada
- `old_values` (JSON, nullable) - valores anteriores (para updates/deletes)
- `new_values` (JSON, nullable) - valores nuevos (para creates/updates)
- `url` (varchar 255, nullable) - URL de la petición
- `ip_address` (varchar 45, nullable) - IP del usuario
- `user_agent` (text, nullable) - User agent del navegador
- `created_at` (timestamp)
- Índices: (user_id), (auditable_type, auditable_id), (event), (created_at)

**Nota**: Esta tabla NO tiene campos created_by/updated_by ya que es de solo inserción.

#### [NEW] Seeders

##### [NEW] [RoleSeeder.php](file:///c:/xampp/htdocs/code/app/Database/Seeds/RoleSeeder.php)
Crea roles iniciales: Super Admin, Admin, Auditor, Usuario

##### [NEW] [PermissionSeeder.php](file:///c:/xampp/htdocs/code/app/Database/Seeds/PermissionSeeder.php)
Crea permisos por módulos:
- **Usuarios**: `users.view`, `users.create`, `users.edit`, `users.delete`
- **Roles**: `roles.view`, `roles.create`, `roles.edit`, `roles.delete`
- **Permisos**: `permissions.view`, `permissions.create`, `permissions.edit`, `permissions.delete`
- **Configuración**: `settings.view`, `settings.edit`
- **Auditoría**: `audit.view`, `audit.export` - para el rol Auditor

##### [NEW] [UserSeeder.php](file:///c:/xampp/htdocs/code/app/Database/Seeds/UserSeeder.php)
Crea usuario Super Admin inicial:
- Username: `admin`
- Email: `admin@example.com`
- Password: `admin123` (debe cambiarse en primer login)

---

### Modelos

##### [NEW] [UserModel.php](file:///c:/xampp/htdocs/code/app/Models/UserModel.php)
Modelo de usuarios con:
- Validaciones de campos
- Hashing automático de contraseñas (beforeInsert, beforeUpdate)
- Método `getUserWithRoles($userId)` - obtiene usuario con sus roles
- Método `hasPermission($userId, $permission)` - verifica si usuario tiene permiso

##### [NEW] [RoleModel.php](file:///c:/xampp/htdocs/code/app/Models/RoleModel.php)
Modelo de roles con:
- Método `getRoleWithPermissions($roleId)` - obtiene rol con permisos
- Método `assignPermission($roleId, $permissionId)` - asigna permiso a rol
- Método `removePermission($roleId, $permissionId)` - remueve permiso de rol

##### [NEW] [PermissionModel.php](file:///c:/xampp/htdocs/code/app/Models/PermissionModel.php)
Modelo de permisos con:
- Método `getByModule($module)` - obtiene permisos por módulo

##### [NEW] [AppSettingModel.php](file:///c:/xampp/htdocs/code/app/Models/AppSettingModel.php)
Modelo de configuración con:
- Método `getSetting($key, $default = null)` - obtiene valor de configuración
- Método `setSetting($key, $value, $type = 'text')` - guarda configuración
- Método `getAllSettings()` - obtiene todas las configuraciones como array asociativo

##### [NEW] [MagicLinkModel.php](file:///c:/xampp/htdocs/code/app/Models/MagicLinkModel.php)
Modelo para Magic Links:
- Método `generateLink($email)` - genera token único y lo guarda
- Método `validateToken($token)` - valida token y retorna email/user_id
- Método `markAsUsed($token)` - marca token como usado
- Método `cleanExpired()` - elimina tokens expirados

##### [NEW] [ApiTokenModel.php](file:///c:/xampp/htdocs/code/app/Models/ApiTokenModel.php)
Modelo para tokens de API:
- Método `createToken($userId, $name, $abilities = [])` - crea nuevo JWT token
- Método `validateToken($token)` - valida JWT y retorna datos del usuario
- Método `revokeToken($tokenId)` - revoca un token
- Método `updateLastUsed($tokenId)` - actualiza última vez usado

##### [NEW] [TwoFactorAuthModel.php](file:///c:/xampp/htdocs/code/app/Models/TwoFactorAuthModel.php)
Modelo para 2FA:
- Método `generateSecret($userId)` - genera secreto TOTP
- Método `verifyCode($userId, $code)` - verifica código TOTP
- Método `generateRecoveryCodes($userId)` - genera códigos de recuperación
- Método `useRecoveryCode($userId, $code)` - usa código de recuperación

##### [NEW] [AuthProviderModel.php](file:///c:/xampp/htdocs/code/app/Models/AuthProviderModel.php)
Modelo para proveedores externos:
- Método `linkProvider($userId, $provider, $providerUserId, $data = [])` - vincula proveedor
- Método `getUserByProvider($provider, $providerUserId)` - obtiene usuario por proveedor externo
- Método `unlinkProvider($userId, $provider)` - desvincula proveedor

##### [NEW] [AuditLogModel.php](file:///c:/xampp/htdocs/code/app/Models/AuditLogModel.php)
Modelo para registros de auditoría:
- Método `log($event, $auditableType, $auditableId, $oldValues, $newValues)` - registra evento
- Método `getByUser($userId, $filters = [])` - obtiene logs de un usuario con filtros
- Método `getByEntity($type, $id)` - obtiene logs de una entidad específica
- Método `getByDateRange($startDate, $endDate)` - obtiene logs por rango de fechas
- Método `exportToCsv($filters = [])` - exporta logs a CSV para auditoría
- Método `getStatistics()` - obtiene estadísticas de eventos


---

### Librerías de Autenticación

##### [NEW] [Libraries/Auth/JwtHandler.php](file:///c:/xampp/htdocs/code/app/Libraries/Auth/JwtHandler.php)
Librería para manejo de JWT:
- Método `encode($payload, $expiresIn = 3600)` - genera JWT token
- Método `decode($token)` - decodifica y valida JWT
- Método `refresh($token)` - renueva token expirado
- Usa `firebase/php-jwt` internamente

##### [NEW] [Libraries/Auth/MagicLinkHandler.php](file:///c:/xampp/htdocs/code/app/Libraries/Auth/MagicLinkHandler.php)
Librería para Magic Links:
- Método `sendMagicLink($email)` - genera token y envía email
- Método `validateAndLogin($token)` - valida token y autentica usuario
- Integración con PHPMailer para envío de emails
- Templates de email personalizables

##### [NEW] [Libraries/Auth/OAuthHandler.php](file:///c:/xampp/htdocs/code/app/Libraries/Auth/OAuthHandler.php)
Librería para OAuth (Microsoft Entra ID):
- Método `getAuthorizationUrl()` - genera URL de autorización
- Método `handleCallback($code)` - procesa callback de OAuth
- Método `getUserInfo($accessToken)` - obtiene info del usuario
- Soporta múltiples proveedores (extensible)

##### [NEW] [Libraries/Auth/LdapHandler.php](file:///c:/xampp/htdocs/code/app/Libraries/Auth/LdapHandler.php)
Librería para LDAP/Active Directory:
- Método `authenticate($username, $password)` - autentica contra LDAP
- Método `getUserAttributes($username)` - obtiene atributos del usuario
- Método `syncUser($username)` - sincroniza usuario LDAP con BD local
- Configuración de conexión LDAPS segura

##### [NEW] [Libraries/Auth/TotpHandler.php](file:///c:/xampp/htdocs/code/app/Libraries/Auth/TotpHandler.php)
Librería para 2FA/TOTP:
- Método `generateSecret()` - genera secreto TOTP
- Método `getQrCodeUrl($secret, $username)` - genera URL para QR
- Método `verifyCode($secret, $code)` - verifica código TOTP
- Usa `sonata-project/google-authenticator`

##### [NEW] [Libraries/Auth/AuthMethodFactory.php](file:///c:/xampp/htdocs/code/app/Libraries/Auth/AuthMethodFactory.php)
Factory para instanciar el método de autenticación apropiado:
- Método `create($method)` - retorna handler según método
- Centraliza la lógica de selección de método
- Facilita extensión con nuevos métodos

---

### Traits (Comportamientos Reutilizables)

##### [NEW] [Traits/Auditable.php](file:///c:/xampp/htdocs/code/app/Traits/Auditable.php)
Trait para auditoría automática en modelos:
- Hook `beforeInsert()` - establece `created_by` con el usuario actual
- Hook `beforeUpdate()` - establece `updated_by` con el usuario actual
- Hook `beforeDelete()` - establece `deleted_by` con el usuario actual (si usa soft delete)
- Hook `afterInsert()` - registra evento 'created' en audit_logs
- Hook `afterUpdate()` - registra evento 'updated' en audit_logs con old/new values
- Hook `afterDelete()` - registra evento 'deleted' en audit_logs
- Método `getCurrentUserId()` - obtiene ID del usuario autenticado actual
- Método `getAuditableAttributes()` - retorna atributos a auditar (excluye password, tokens, etc.)

**Uso**: Los modelos que requieran auditoría deben usar este trait:
```php
class UserModel extends Model {
    use \App\Traits\Auditable;
    // ...
}
```

---


### Controladores

##### [NEW] [AuthController.php](file:///c:/xampp/htdocs/code/app/Controllers/AuthController.php)
Controlador de autenticación tradicional:
- `login()` - GET: muestra formulario con opciones de autenticación, POST: procesa login tradicional
- `register()` - GET: muestra formulario, POST: procesa registro
- `logout()` - cierra sesión y redirige a login
- `verify2FA()` - GET/POST: verifica código 2FA si está habilitado
- Validaciones con reglas de CodeIgniter 4
- Mensajes flash para feedback al usuario
- Protección contra brute force (bloqueo temporal)

##### [NEW] [MagicLinkController.php](file:///c:/xampp/htdocs/code/app/Controllers/MagicLinkController.php)
Controlador para autenticación Passwordless:
- `request()` - GET: formulario para solicitar magic link, POST: envía email con link
- `verify($token)` - valida token y autentica usuario
- `resend()` - reenvía magic link si expiró
- Limpieza automática de tokens expirados

##### [NEW] [ApiAuthController.php](file:///c:/xampp/htdocs/code/app/Controllers/ApiAuthController.php)
Controlador para autenticación de APIs (REST):
- `login()` - POST: autentica y retorna JWT token
- `refresh()` - POST: renueva token expirado
- `logout()` - POST: revoca token actual
- `me()` - GET: obtiene datos del usuario autenticado
- Respuestas en formato JSON
- Rate limiting para prevenir abuso

##### [NEW] [OAuthController.php](file:///c:/xampp/htdocs/code/app/Controllers/OAuthController.php)
Controlador para OAuth (Microsoft Entra ID):
- `redirect($provider)` - redirige a proveedor OAuth para autorización
- `callback($provider)` - procesa callback de OAuth
- `link($provider)` - vincula cuenta OAuth a usuario existente
- `unlink($provider)` - desvincula cuenta OAuth
- Soporta múltiples proveedores (extensible)

##### [NEW] [LdapAuthController.php](file:///c:/xampp/htdocs/code/app/Controllers/LdapAuthController.php)
Controlador para autenticación LDAP:
- `login()` - POST: autentica contra LDAP y sincroniza usuario
- `sync()` - sincroniza datos de usuario desde LDAP
- `test()` - prueba conexión LDAP (solo admin)
- Manejo de errores de conexión LDAP

##### [NEW] [TwoFactorController.php](file:///c:/xampp/htdocs/code/app/Controllers/TwoFactorController.php)
Controlador para gestión de 2FA:
- `enable()` - GET: muestra QR code, POST: habilita 2FA
- `disable()` - POST: deshabilita 2FA
- `verify()` - POST: verifica código TOTP
- `recoveryCodes()` - GET: muestra códigos de recuperación
- `regenerateRecoveryCodes()` - POST: regenera códigos de recuperación

##### [NEW] [AuthMethodController.php](file:///c:/xampp/htdocs/code/app/Controllers/AuthMethodController.php)
Controlador para gestión de métodos de autenticación (admin):
- `index()` - GET: muestra métodos habilitados/deshabilitados
- `toggle($method)` - POST: habilita/deshabilita método de autenticación
- `configure($method)` - GET/POST: configura parámetros del método
- Solo accesible por Super Admin


##### [NEW] [DashboardController.php](file:///c:/xampp/htdocs/code/app/Controllers/DashboardController.php)
Controlador del panel principal (requiere autenticación):
- `index()` - muestra dashboard según rol del usuario

##### [NEW] [UserController.php](file:///c:/xampp/htdocs/code/app/Controllers/UserController.php)
Controlador CRUD de usuarios (requiere permiso `users.*`):
- `index()` - lista usuarios con paginación
- `create()` - GET: formulario, POST: crea usuario
- `edit($id)` - GET: formulario, POST: actualiza usuario
- `delete($id)` - elimina usuario (soft delete)
- `assignRole($userId)` - asigna/remueve roles a usuario

##### [NEW] [RoleController.php](file:///c:/xampp/htdocs/code/app/Controllers/RoleController.php)
Controlador CRUD de roles (requiere permiso `roles.*`):
- `index()` - lista roles
- `create()` - GET: formulario, POST: crea rol
- `edit($id)` - GET: formulario, POST: actualiza rol
- `delete($id)` - elimina rol
- `permissions($roleId)` - GET: muestra permisos, POST: asigna/remueve permisos

##### [NEW] [PermissionController.php](file:///c:/xampp/htdocs/code/app/Controllers/PermissionController.php)
Controlador CRUD de permisos (requiere permiso `permissions.*`):
- `index()` - lista permisos agrupados por módulo
- `create()` - GET: formulario, POST: crea permiso
- `edit($id)` - GET: formulario, POST: actualiza permiso
- `delete($id)` - elimina permiso

##### [NEW] [SettingsController.php](file:///c:/xampp/htdocs/code/app/Controllers/SettingsController.php)
Controlador de configuración (requiere permiso `settings.edit`):
- `index()` - GET: muestra formulario de configuración, POST: guarda cambios
- `uploadLoginImage()` - procesa carga de imagen de login
- `uploadFavicon()` - procesa carga de favicon.ico
- Validación de tipos de archivo (jpg, png, webp para imágenes; ico para favicon)
- Redimensionamiento de imágenes si es necesario

##### [NEW] [AuditController.php](file:///c:/xampp/htdocs/code/app/Controllers/AuditController.php)
Controlador de auditoría (requiere permiso `audit.view`):
- `index()` - GET: lista registros de auditoría con filtros avanzados
- `view($id)` - GET: muestra detalles de un registro específico
- `export()` - POST: exporta registros a CSV según filtros
- `statistics()` - GET: muestra estadísticas de eventos de auditoría
- Filtros disponibles: usuario, tipo de evento, entidad, rango de fechas
- Paginación y búsqueda
- Solo accesible por usuarios con rol Auditor o superior

##### [NEW] [LanguageController.php](file:///c:/xampp/htdocs/code/app/Controllers/LanguageController.php)
Controlador para cambio de idioma:
- `switch($locale)` - cambia el idioma de la aplicación
- Valida que el idioma esté disponible (es, en)
- Guarda preferencia en sesión
- Si el usuario está autenticado, actualiza `preferred_language` en BD
- Redirige a la página anterior
- Registra evento en audit_logs si está autenticado

---

### Filtros (Middleware)

##### [NEW] [AuthFilter.php](file:///c:/xampp/htdocs/code/app/Filters/AuthFilter.php)
Filtro de autenticación:
- Verifica si el usuario está autenticado
- Redirige a login si no está autenticado
- Guarda URL de intento de acceso para redirección post-login

##### [NEW] [PermissionFilter.php](file:///c:/xampp/htdocs/code/app/Filters/PermissionFilter.php)
Filtro de autorización:
- Verifica si el usuario tiene el permiso requerido
- Configurable por ruta en `app/Config/Filters.php`
- Muestra error 403 si no tiene permiso

---

### Vistas

##### [NEW] Layout Base

###### [NEW] [layouts/main.php](file:///c:/xampp/htdocs/code/app/Views/layouts/main.php)
Layout principal con:
- Tailwind CSS v4 compilado (sin CDN)
- Iconos SVG Lucide auto-hospedados
- Fuente Inter auto-hospedada
- CSS personalizado con paleta "Executive Clean"
- Navbar responsivo con menú hamburguesa
- Sidebar con navegación según permisos del usuario
- Footer corporativo

###### [NEW] [layouts/auth.php](file:///c:/xampp/htdocs/code/app/Views/layouts/auth.php)
Layout para páginas de autenticación (login/registro):
- Diseño centrado con tarjeta
- Imagen personalizable desde configuración
- Fondo con color corporativo
- Diseño responsivo mobile-first

##### [NEW] Autenticación

###### [NEW] [auth/login.php](file:///c:/xampp/htdocs/code/app/Views/auth/login.php)
Vista de login con:
- Formulario con campos username/email y password
- Checkbox "Recordarme"
- Link a registro
- Validaciones client-side y server-side
- Mensajes de error personalizados
- Imagen y textos personalizables desde configuración

###### [NEW] [auth/register.php](file:///c:/xampp/htdocs/code/app/Views/auth/register.php)
Vista de registro con:
- Formulario con username, email, password, confirmación, nombre, apellido
- Validaciones en tiempo real (JavaScript)
- Indicador de fortaleza de contraseña
- Link a login

###### [NEW] [auth/magic_link_request.php](file:///c:/xampp/htdocs/code/app/Views/auth/magic_link_request.php)
Vista para solicitar Magic Link:
- Formulario simple con solo campo email
- Mensaje de confirmación después de envío
- Instrucciones claras para el usuario

###### [NEW] [auth/magic_link_sent.php](file:///c:/xampp/htdocs/code/app/Views/auth/magic_link_sent.php)
Vista de confirmación de envío de Magic Link:
- Mensaje de éxito
- Instrucciones para revisar email
- Opción para reenviar link

###### [NEW] [auth/verify_2fa.php](file:///c:/xampp/htdocs/code/app/Views/auth/verify_2fa.php)
Vista para verificar código 2FA:
- Campo para ingresar código de 6 dígitos
- Opción para usar código de recuperación
- Diseño limpio y enfocado

###### [NEW] [auth/oauth_buttons.php](file:///c:/xampp/htdocs/code/app/Views/auth/oauth_buttons.php)
Componente reutilizable con botones OAuth:
- Botón "Continuar con Microsoft"
- Botón "Continuar con Google" (si está configurado)
- Diseño con iconos de marca
- Se incluye en login.php si OAuth está habilitado


##### [NEW] Dashboard

###### [NEW] [dashboard/index.php](file:///c:/xampp/htdocs/code/app/Views/dashboard/index.php)
Panel principal con:
- Tarjetas de estadísticas (total usuarios, roles, etc.)
- Accesos rápidos según permisos
- Gráficos con Chart.js (opcional)

##### [NEW] Gestión de Usuarios

###### [NEW] [users/index.php](file:///c:/xampp/htdocs/code/app/Views/users/index.php)
Lista de usuarios con:
- Tabla responsiva con DataTables
- Búsqueda y filtros
- Acciones: editar, eliminar, asignar roles
- Paginación

###### [NEW] [users/form.php](file:///c:/xampp/htdocs/code/app/Views/users/form.php)
Formulario de creación/edición de usuarios

###### [NEW] [users/assign_roles.php](file:///c:/xampp/htdocs/code/app/Views/users/assign_roles.php)
Modal/vista para asignar roles a usuario

##### [NEW] Gestión de Roles

###### [NEW] [roles/index.php](file:///c:/xampp/htdocs/code/app/Views/roles/index.php)
Lista de roles

###### [NEW] [roles/form.php](file:///c:/xampp/htdocs/code/app/Views/roles/form.php)
Formulario de creación/edición de roles

###### [NEW] [roles/permissions.php](file:///c:/xampp/htdocs/code/app/Views/roles/permissions.php)
Vista para asignar permisos a rol (checkboxes agrupados por módulo)

##### [NEW] Gestión de Permisos

###### [NEW] [permissions/index.php](file:///c:/xampp/htdocs/code/app/Views/permissions/index.php)
Lista de permisos agrupados por módulo

###### [NEW] [permissions/form.php](file:///c:/xampp/htdocs/code/app/Views/permissions/form.php)
Formulario de creación/edición de permisos

##### [NEW] Configuración

###### [NEW] [settings/index.php](file:///c:/xampp/htdocs/code/app/Views/settings/index.php)
Vista de configuración personalizable con:
- Campo para nombre del proyecto
- Campo para descripción
- Upload de imagen de login con preview
- Upload de favicon.ico con preview
- Selector de color de fondo
- Botón guardar cambios
- Preview en tiempo real de cómo se verá el login

##### [NEW] Autenticación de Dos Factores

###### [NEW] [two_factor/enable.php](file:///c:/xampp/htdocs/code/app/Views/two_factor/enable.php)
Vista para habilitar 2FA:
- QR code para escanear con Google Authenticator/Authy
- Código secreto manual (por si no puede escanear)
- Campo para verificar código antes de habilitar
- Códigos de recuperación generados

###### [NEW] [two_factor/recovery_codes.php](file:///c:/xampp/htdocs/code/app/Views/two_factor/recovery_codes.php)
Vista para mostrar códigos de recuperación:
- Lista de códigos de recuperación
- Advertencia para guardarlos en lugar seguro
- Opción para regenerar códigos
- Opción para deshabilitar 2FA

##### [NEW] Tokens de API

###### [NEW] [api_tokens/index.php](file:///c:/xampp/htdocs/code/app/Views/api_tokens/index.php)
Vista para gestión de tokens de API:
- Lista de tokens activos con nombre, fecha de creación, última vez usado
- Botón para crear nuevo token
- Botón para revocar tokens
- Documentación básica de uso de API

###### [NEW] [api_tokens/create.php](file:///c:/xampp/htdocs/code/app/Views/api_tokens/create.php)
Vista para crear token de API:
- Campo para nombre del token
- Selector de permisos/abilities
- Fecha de expiración opcional
- Muestra el token generado (solo una vez)

##### [NEW] Métodos de Autenticación

###### [NEW] [auth_methods/index.php](file:///c:/xampp/htdocs/code/app/Views/auth_methods/index.php)
Vista para gestión de métodos de autenticación (admin):
- Tarjetas para cada método con toggle on/off
- Indicador de estado (habilitado/deshabilitado/configurado)
- Botón de configuración para cada método
- Descripción de cada método

###### [NEW] [auth_methods/configure.php](file:///c:/xampp/htdocs/code/app/Views/auth_methods/configure.php)
Vista para configurar método específico:
- Formulario dinámico según el método seleccionado
- Para OAuth: Client ID, Client Secret, Tenant ID
- Para LDAP: Host, puerto, base DN, credenciales
- Para Magic Link: configuración de email
- Botón para probar configuración

##### [NEW] Auditoría

###### [NEW] [audit/index.php](file:///c:/xampp/htdocs/code/app/Views/audit/index.php)
Vista principal de auditoría (solo Auditor y superiores):
- Tabla de registros de auditoría con paginación
- Filtros avanzados:
  - Por usuario (select con autocompletado)
  - Por tipo de evento (created, updated, deleted, login, logout)
  - Por tipo de entidad (users, roles, permissions, etc.)
  - Por rango de fechas (date pickers)
- Columnas: Fecha/Hora, Usuario, Evento, Entidad, IP, Acciones
- Botón para ver detalles de cada registro
- Botón para exportar resultados a CSV
- Diseño con DataTables para búsqueda y ordenamiento

###### [NEW] [audit/view.php](file:///c:/xampp/htdocs/code/app/Views/audit/view.php)
Vista de detalle de registro de auditoría:
- Información completa del evento
- Comparación lado a lado de valores antiguos vs nuevos (para updates)
- Información del usuario que realizó la acción
- Metadata: IP, User Agent, URL, timestamp
- Botón para volver a la lista

###### [NEW] [audit/statistics.php](file:///c:/xampp/htdocs/code/app/Views/audit/statistics.php)
Vista de estadísticas de auditoría:
- Gráficos de eventos por tipo (Chart.js)
- Eventos por usuario (top 10)
- Línea de tiempo de actividad
- Resumen de eventos por día/semana/mes
- Filtros por rango de fechas


---

### Archivos Estáticos

##### [NEW] [public/assets/css/custom.css](file:///c:/xampp/htdocs/code/public/assets/css/custom.css)
Estilos personalizados siguiendo especificaciones "Executive Clean":

**ARQUITECTURA DE VARIABLES CSS (Fuente Única de Verdad)**:
```css
:root {
  /* ============================================ */
  /* COLORES CORPORATIVOS - PRIMARIOS */
  /* ============================================ */
  --color-primary: #e10e30;
  --color-primary-hover: #b90b26;
  --color-primary-light: #fff1f2;
  
  /* ============================================ */
  /* COLORES DE SUPERFICIE Y FONDO */
  /* ============================================ */
  --bg-body: #f3f4f6;
  --bg-surface: #ffffff;
  --bg-surface-hover: #f9fafb;
  
  /* ============================================ */
  /* COLORES DE TEXTO */
  /* ============================================ */
  --text-primary: #1e293b;
  --text-secondary: #64748b;
  --text-muted: #94a3b8;
  --text-on-primary: #ffffff;
  
  /* ============================================ */
  /* BORDES */
  /* ============================================ */
  --border-color: #e2e8f0;
  --border-color-hover: var(--color-primary);
  --border-radius-card: 16px;
  --border-radius-button: 8px;
  --border-radius-input: 8px;
  
  /* ============================================ */
  /* SOMBRAS */
  /* ============================================ */
  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
  --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
  --shadow-card: var(--shadow-lg);
  
  /* ============================================ */
  /* TIPOGRAFÍA */
  /* ============================================ */
  --font-family: 'Inter', sans-serif;
  --font-weight-light: 300;
  --font-weight-regular: 400;
  --font-weight-medium: 500;
  --font-weight-semibold: 600;
  --font-weight-bold: 700;
  
  /* ============================================ */
  /* ESPACIADO */
  /* ============================================ */
  --spacing-xs: 0.25rem;
  --spacing-sm: 0.5rem;
  --spacing-md: 1rem;
  --spacing-lg: 1.5rem;
  --spacing-xl: 2rem;
  
  /* ============================================ */
  /* TRANSICIONES */
  /* ============================================ */
  --transition-fast: 150ms ease-in-out;
  --transition-normal: 300ms ease-in-out;
}
```

**COMPONENTES (Sin valores hardcodeados)**:
- Cards: `background: var(--bg-surface); border-radius: var(--border-radius-card); box-shadow: var(--shadow-card);`
- Buttons: `background: var(--color-primary); color: var(--text-on-primary); border-radius: var(--border-radius-button);`
- Inputs: `border: 1px solid var(--border-color); border-radius: var(--border-radius-input);`
- Hover effects: `transition: var(--transition-normal);`

**REGLA CRÍTICA**: Ningún componente debe tener colores, tamaños o valores hardcodeados. TODO debe referenciar variables CSS.

##### [NEW] [public/assets/js/app.js](file:///c:/xampp/htdocs/code/public/assets/js/app.js)
JavaScript global:
- Validaciones de formularios
- Confirmaciones de eliminación
- Manejo de mensajes flash
- Funciones auxiliares

##### [NEW] [public/uploads/settings/](file:///c:/xampp/htdocs/code/public/uploads/settings/)
Directorio para imágenes de configuración (se creará automáticamente)

---

### Internacionalización (i18n)

##### [NEW] [Language/es/](file:///c:/xampp/htdocs/code/app/Language/es/)
Directorio para archivos de idioma español

###### [NEW] [Language/es/App.php](file:///c:/xampp/htdocs/code/app/Language/es/App.php)
Traducciones generales en español:
- Títulos de páginas
- Mensajes del sistema
- Etiquetas de formularios
- Botones y acciones comunes
- Mensajes de validación
- Mensajes de éxito/error

###### [NEW] [Language/es/Auth.php](file:///c:/xampp/htdocs/code/app/Language/es/Auth.php)
Traducciones de autenticación en español:
- Mensajes de login/logout
- Mensajes de registro
- Errores de autenticación
- Mensajes de 2FA
- Mensajes de Magic Link
- Mensajes de OAuth

###### [NEW] [Language/es/Validation.php](file:///c:/xampp/htdocs/code/app/Language/es/Validation.php)
Mensajes de validación personalizados en español

##### [NEW] [Language/en/](file:///c:/xampp/htdocs/code/app/Language/en/)
Directorio para archivos de idioma inglés

###### [NEW] [Language/en/App.php](file:///c:/xampp/htdocs/code/app/Language/en/App.php)
Traducciones generales en inglés (equivalente a es/App.php)

###### [NEW] [Language/en/Auth.php](file:///c:/xampp/htdocs/code/app/Language/en/Auth.php)
Traducciones de autenticación en inglés (equivalente a es/Auth.php)

###### [NEW] [Language/en/Validation.php](file:///c:/xampp/htdocs/code/app/Language/en/Validation.php)
Mensajes de validación personalizados en inglés

##### [NEW] [Helpers/language_helper.php](file:///c:/xampp/htdocs/code/app/Helpers/language_helper.php)
Helper para funciones de idioma:
- `lang($key, $args = [])` - función abreviada para traducción
- `get_current_language()` - obtiene idioma actual
- `set_language($locale)` - establece idioma en sesión
- `get_available_languages()` - retorna array de idiomas disponibles
- `get_language_name($locale)` - retorna nombre del idioma en su propio idioma

##### [NEW] [2024_12_16_000013_add_language_to_users.php](file:///c:/xampp/htdocs/code/app/Database/Migrations/2024_12_16_000013_add_language_to_users.php)
Migración para agregar campo de idioma preferido:
- `preferred_language` (varchar 5, default 'es') - idioma preferido del usuario

##### [NEW] [Views/components/language_switcher.php](file:///c:/xampp/htdocs/code/app/Views/components/language_switcher.php)
Componente selector de idioma:
- Dropdown con banderas de España e Inglaterra
- Cambia idioma y recarga página
- Se incluye en navbar del layout principal
- Guarda preferencia en sesión y base de datos (si está autenticado)


### Configuración

##### [MODIFY] [Config/Routes.php](file:///c:/xampp/htdocs/code/app/Config/Routes.php)
Agregar rutas:
```php
// ============================================
// AUTENTICACIÓN PÚBLICA
// ============================================

// Autenticación Tradicional
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::login');
$routes->get('register', 'AuthController::register');
$routes->post('register', 'AuthController::register');
$routes->get('logout', 'AuthController::logout');

// Cambio de idioma
$routes->get('language/(:segment)', 'LanguageController::switch/$1');

// Verificación 2FA
$routes->get('verify-2fa', 'AuthController::verify2FA');
$routes->post('verify-2fa', 'AuthController::verify2FA');

// Magic Link (Passwordless)
$routes->get('magic-link/request', 'MagicLinkController::request');
$routes->post('magic-link/request', 'MagicLinkController::request');
$routes->get('magic-link/verify/(:any)', 'MagicLinkController::verify/$1');
$routes->post('magic-link/resend', 'MagicLinkController::resend');

// OAuth (Microsoft Entra ID, Google, etc.)
$routes->get('oauth/(:segment)/redirect', 'OAuthController::redirect/$1');
$routes->get('oauth/(:segment)/callback', 'OAuthController::callback/$1');

// LDAP Login (puede usar mismo formulario que login tradicional)
$routes->post('ldap/login', 'LdapAuthController::login');

// ============================================
// API AUTHENTICATION (REST)
// ============================================
$routes->group('api', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->post('auth/login', 'ApiAuthController::login');
    $routes->post('auth/refresh', 'ApiAuthController::refresh');
    $routes->post('auth/logout', 'ApiAuthController::logout', ['filter' => 'api_auth']);
    $routes->get('auth/me', 'ApiAuthController::me', ['filter' => 'api_auth']);
});

// ============================================
// RUTAS PROTEGIDAS (Requieren Autenticación)
// ============================================

// Dashboard
$routes->get('/', 'DashboardController::index', ['filter' => 'auth']);
$routes->get('dashboard', 'DashboardController::index', ['filter' => 'auth']);

// Gestión de 2FA (Usuario)
$routes->group('two-factor', ['filter' => 'auth'], function($routes) {
    $routes->get('enable', 'TwoFactorController::enable');
    $routes->post('enable', 'TwoFactorController::enable');
    $routes->post('disable', 'TwoFactorController::disable');
    $routes->get('recovery-codes', 'TwoFactorController::recoveryCodes');
    $routes->post('recovery-codes/regenerate', 'TwoFactorController::regenerateRecoveryCodes');
});

// Gestión de Tokens de API (Usuario)
$routes->group('api-tokens', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'ApiTokenController::index');
    $routes->get('create', 'ApiTokenController::create');
    $routes->post('create', 'ApiTokenController::create');
    $routes->post('revoke/(:num)', 'ApiTokenController::revoke/$1');
});

// Vincular/Desvincular Proveedores OAuth (Usuario)
$routes->group('account', ['filter' => 'auth'], function($routes) {
    $routes->get('linked-accounts', 'OAuthController::linkedAccounts');
    $routes->get('link/(:segment)', 'OAuthController::link/$1');
    $routes->post('unlink/(:segment)', 'OAuthController::unlink/$1');
});

// Usuarios (requiere permisos)
$routes->group('users', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'UserController::index');
    $routes->get('create', 'UserController::create');
    $routes->post('create', 'UserController::create');
    $routes->get('edit/(:num)', 'UserController::edit/$1');
    $routes->post('edit/(:num)', 'UserController::edit/$1');
    $routes->get('delete/(:num)', 'UserController::delete/$1');
    $routes->get('assign-role/(:num)', 'UserController::assignRole/$1');
    $routes->post('assign-role/(:num)', 'UserController::assignRole/$1');
});

// Roles (requiere permisos)
$routes->group('roles', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'RoleController::index');
    $routes->get('create', 'RoleController::create');
    $routes->post('create', 'RoleController::create');
    $routes->get('edit/(:num)', 'RoleController::edit/$1');
    $routes->post('edit/(:num)', 'RoleController::edit/$1');
    $routes->get('delete/(:num)', 'RoleController::delete/$1');
    $routes->get('permissions/(:num)', 'RoleController::permissions/$1');
    $routes->post('permissions/(:num)', 'RoleController::permissions/$1');
});

// Permisos (requiere permisos)
$routes->group('permissions', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'PermissionController::index');
    $routes->get('create', 'PermissionController::create');
    $routes->post('create', 'PermissionController::create');
    $routes->get('edit/(:num)', 'PermissionController::edit/$1');
    $routes->post('edit/(:num)', 'PermissionController::edit/$1');
    $routes->get('delete/(:num)', 'PermissionController::delete/$1');
});

// Configuración General (requiere permisos)
$routes->group('settings', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'SettingsController::index');
    $routes->post('/', 'SettingsController::index');
    $routes->post('upload-image', 'SettingsController::uploadLoginImage');
});

// Métodos de Autenticación (Solo Super Admin)
$routes->group('auth-methods', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'AuthMethodController::index');
    $routes->post('toggle/(:segment)', 'AuthMethodController::toggle/$1');
    $routes->get('configure/(:segment)', 'AuthMethodController::configure/$1');
    $routes->post('configure/(:segment)', 'AuthMethodController::configure/$1');
    $routes->post('test/ldap', 'LdapAuthController::test');
});

// Auditoría (Requiere permiso audit.view - Rol Auditor)
$routes->group('audit', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'AuditController::index');
    $routes->get('view/(:num)', 'AuditController::view/$1');
    $routes->post('export', 'AuditController::export');
    $routes->get('statistics', 'AuditController::statistics');
});
```


##### [MODIFY] [Config/Filters.php](file:///c:/xampp/htdocs/code/app/Config/Filters.php)
Registrar filtros personalizados:
```php
public array $aliases = [
    // ... filtros existentes
    'auth' => \App\Filters\AuthFilter::class,
    'permission' => \App\Filters\PermissionFilter::class,
];
```

##### [MODIFY] [.env](file:///c:/xampp/htdocs/code/.env)
Agregar configuraciones de autenticación:
```env
# ============================================
# ENCRYPTION
# ============================================
encryption.key = [CLAVE_GENERADA_AUTOMÁTICAMENTE_32_BYTES]

# ============================================
# JWT CONFIGURATION
# ============================================
jwt.secret = [CLAVE_JWT_GENERADA_AUTOMÁTICAMENTE]
jwt.issuer = http://localhost/code
jwt.audience = http://localhost/code
jwt.expiration = 3600
jwt.refresh_expiration = 604800

# ============================================
# MAGIC LINK CONFIGURATION
# ============================================
magiclink.expiration = 900
magiclink.enabled = true

# ============================================
# SMTP CONFIGURATION (Para Magic Links y 2FA)
# ============================================
email.fromEmail = noreply@tudominio.com
email.fromName = Tu Aplicación
email.SMTPHost = smtp.gmail.com
email.SMTPUser = tu-email@gmail.com
email.SMTPPass = tu-password-app
email.SMTPPort = 587
email.SMTPCrypto = tls

# ============================================
# MICROSOFT ENTRA ID (AZURE AD) OAUTH
# ============================================
oauth.entra.enabled = false
oauth.entra.clientId = 
oauth.entra.clientSecret = 
oauth.entra.tenantId = 
oauth.entra.redirectUri = http://localhost/code/oauth/entra_id/callback

# ============================================
# GOOGLE OAUTH (Opcional)
# ============================================
oauth.google.enabled = false
oauth.google.clientId = 
oauth.google.clientSecret = 
oauth.google.redirectUri = http://localhost/code/oauth/google/callback

# ============================================
# LDAP / ACTIVE DIRECTORY
# ============================================
ldap.enabled = false
ldap.host = ldap://tu-servidor-ldap.com
ldap.port = 389
ldap.use_tls = true
ldap.base_dn = dc=ejemplo,dc=com
ldap.user_search_base = ou=usuarios,dc=ejemplo,dc=com
ldap.login_attribute = uid
ldap.bind_dn = cn=admin,dc=ejemplo,dc=com
ldap.bind_password = 

# ============================================
# TWO-FACTOR AUTHENTICATION
# ============================================
totp.enabled = true
totp.issuer = Tu Aplicación
totp.digits = 6
totp.period = 30

# ============================================
# SECURITY SETTINGS
# ============================================
security.max_login_attempts = 5
security.lockout_duration = 900
security.password_min_length = 8
security.require_strong_password = true
```

##### [NEW] [composer.json](file:///c:/xampp/htdocs/code/composer.json)
Agregar dependencias necesarias:
```json
{
    "require": {
        "php": "^8.1",
        "codeigniter4/framework": "^4.4",
        "firebase/php-jwt": "^6.10",
        "phpmailer/phpmailer": "^6.9",
        "sonata-project/google-authenticator": "^2.3",
        "thenetworg/oauth2-azure": "^2.2"
    }
}
```

Ejecutar después de modificar:
```bash
composer update
```

##### [NEW] [Config/Auth.php](file:///c:/xampp/htdocs/code/app/Config/Auth.php)
Archivo de configuración personalizado para autenticación:
```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Auth extends BaseConfig
{
    // Métodos de autenticación habilitados
    public array $enabledMethods = [
        'password' => true,
        'magic_link' => true,
        'oauth' => false,
        'ldap' => false,
        'api_token' => true,
    ];
    
    // Configuración de sesión
    public int $sessionExpiration = 7200; // 2 horas
    public int $rememberMeExpiration = 2592000; // 30 días
    
    // Seguridad
    public int $maxLoginAttempts = 5;
    public int $lockoutDuration = 900; // 15 minutos
    public bool $requireEmailVerification = false;
    public bool $require2FA = false; // Si es true, todos los usuarios deben usar 2FA
    
    // Contraseñas
    public int $passwordMinLength = 8;
    public bool $requireStrongPassword = true;
    public int $passwordResetExpiration = 3600; // 1 hora
    
    // Magic Links
    public int $magicLinkExpiration = 900; // 15 minutos
    
    // JWT
    public int $jwtExpiration = 3600; // 1 hora
    public int $jwtRefreshExpiration = 604800; // 7 días
    
    // Proveedores OAuth soportados
    public array $oauthProviders = [
        'entra_id' => [
            'name' => 'Microsoft',
            'icon' => 'bi-microsoft',
            'enabled' => false,
        ],
        'google' => [
            'name' => 'Google',
            'icon' => 'bi-google',
            'enabled' => false,
        ],
    ];
}
```


---

### Helpers

##### [NEW] [auth_helper.php](file:///c:/xampp/htdocs/code/app/Helpers/auth_helper.php)
Funciones auxiliares:
- `is_logged_in()` - verifica si hay sesión activa
- `current_user()` - obtiene datos del usuario actual
- `has_permission($permission)` - verifica permiso del usuario actual
- `has_role($role)` - verifica si usuario tiene rol específico

## Plan de Verificación

### Pruebas Automatizadas

Dado que este es un proyecto nuevo sin tests existentes, se verificará manualmente el funcionamiento del sistema.

### Verificación Manual

#### 1. Configuración Inicial
```bash
# Ejecutar migraciones
php spark migrate

# Ejecutar seeders
php spark db:seed RoleSeeder
php spark db:seed PermissionSeeder
php spark db:seed UserSeeder
```

**Resultado esperado**: Base de datos creada con tablas, roles, permisos y usuario admin inicial.

#### 2. Prueba de Registro
1. Navegar a `http://localhost/code/register`
2. Completar formulario con datos válidos
3. Verificar que se crea el usuario en la base de datos
4. Verificar redirección a login con mensaje de éxito

**Resultado esperado**: Usuario creado correctamente, puede iniciar sesión.

#### 3. Prueba de Login
1. Navegar a `http://localhost/code/login`
2. Ingresar credenciales del admin (`admin` / `admin123`)
3. Verificar redirección a dashboard
4. Verificar que la sesión se mantiene al navegar

**Resultado esperado**: Login exitoso, sesión activa, acceso al dashboard.

#### 4. Prueba de Gestión de Usuarios
1. Desde el dashboard, navegar a "Usuarios"
2. Crear un nuevo usuario
3. Editar el usuario creado
4. Asignar un rol al usuario
5. Eliminar el usuario

**Resultado esperado**: CRUD completo funcional, validaciones activas.

#### 5. Prueba de Roles y Permisos
1. Navegar a "Roles"
2. Crear un nuevo rol
3. Asignar permisos al rol
4. Crear un usuario con ese rol
5. Iniciar sesión con el nuevo usuario
6. Verificar que solo puede acceder a las secciones permitidas

**Resultado esperado**: Sistema de permisos funciona correctamente, restricciones aplicadas.

#### 6. Prueba de Configuración Personalizable
1. Navegar a "Configuración"
2. Cambiar el nombre del proyecto
3. Cambiar la descripción
4. Subir una nueva imagen de login
5. Cerrar sesión
6. Verificar que la página de login muestra los cambios

**Resultado esperado**: Configuración guardada y aplicada correctamente en la vista de login.

#### 7. Prueba de Diseño Responsivo
1. Abrir la aplicación en navegador desktop
2. Redimensionar ventana a tamaño móvil
3. Verificar que el menú hamburguesa funciona
4. Verificar que las tablas son scrollables
5. Verificar que los formularios se adaptan correctamente

**Resultado esperado**: Diseño responsivo funcional en todos los tamaños de pantalla.

#### 8. Prueba de Validaciones
1. Intentar registrarse con email duplicado
2. Intentar login con credenciales incorrectas
3. Intentar crear usuario sin completar campos requeridos
4. Intentar subir imagen con formato no permitido

**Resultado esperado**: Validaciones funcionan, mensajes de error claros y específicos.

### Verificación de Estilo

1. **Paleta de Colores**: Verificar que se usa `#e10e30` para elementos principales
2. **Tipografía**: Verificar que se carga la fuente Inter
3. **Componentes**: Verificar border-radius de 16px en cards, 8px en botones
4. **Fondo**: Verificar que el body usa `#f3f4f6` y las cards usan `#ffffff`
5. **Hover Effects**: Verificar transiciones suaves en botones y enlaces

**Resultado esperado**: Diseño cumple con las especificaciones "Executive Clean".
