# 📋 PLAN DE INICIO DE DESARROLLO

**Fecha:** 1 de Febrero de 2026  
**Proyecto:** Sistema de Autenticación y Gestión de Usuarios - CodeIgniter 4  
**Estado:** ✅ LISTO PARA INICIO

---

## 🎯 OBJETIVOS DEL PLAN

1. ✅ Validar que el sistema está completamente implementado
2. ✅ Identificar cualquier issue o bug
3. ✅ Establecer flujo de trabajo para desarrollo futuro
4. ✅ Documentar pasos iniciales

---

## 📋 FASE 1: VALIDACIÓN INICIAL (Día 1)

### 1.1 Verificación del Entorno

```bash
# Verificar PHP
php -v                    # Debe ser 8.1+

# Verificar MySQL
mysql -u root -p          # Conectar a MySQL

# Verificar Composer
composer --version        # Debe estar instalado

# Ir al directorio del proyecto
cd c:\xampp\htdocs\rupin

# Instalar dependencias
composer install          # Instalar/actualizar paquetes
```

### 1.2 Configuración de Base de Datos

```bash
# Crear base de datos
# En MySQL console:
CREATE DATABASE rupin_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# O en PhpMyAdmin:
# 1. Crear nueva base de datos: rupin_db
# 2. Charset: utf8mb4
# 3. Collation: utf8mb4_unicode_ci
```

### 1.3 Configuración del Archivo .env

```env
# Copiar de .env.example a .env
cp .env.example .env

# Editar los siguientes campos:

# DATABASE
CI_ENVIRONMENT = development
database.default.hostname = localhost
database.default.database = rupin_db
database.default.username = root
database.default.password = 
database.default.DBDriver = MySQLi

# APP URL
app.baseURL = http://localhost/rupin

# ENCRYPTION
encryption.key = [GENERAR CON: php spark key:generate]

# JWT
jwt.secret = [GENERAR CON: openssl rand -base64 32]
jwt.issuer = http://localhost/rupin
jwt.audience = http://localhost/rupin
```

**Generar encryption.key:**
```bash
php spark key:generate
# Copia el valor generado a .env
```

**Generar JWT secret:**
```bash
# En PowerShell:
[Convert]::ToBase64String((1..32 | ForEach-Object { [byte](Get-Random -Min 0 -Max 256) }))

# O usar openssl si está disponible:
openssl rand -base64 32
```

### 1.4 Ejecutar Migraciones

```bash
# Ejecutar todas las migraciones
php spark migrate

# Salida esperada:
# Running Migrations...
# (lots of migrations)
# Done in 2.3456 seconds
```

### 1.5 Ejecutar Seeders

```bash
# Ejecutar seeders en orden
php spark db:seed RoleSeeder
php spark db:seed PermissionSeeder
php spark db:seed UserSeeder
php spark db:seed AppSettingSeeder

# Salida esperada:
# Seeding...
# Done in 0.1234 seconds
```

---

## 📋 FASE 2: PRUEBAS DE ACCESO (Día 1)

### 2.1 Iniciar Servidor

```bash
# Opción 1: Usar XAMPP (ya está ejecutándose)
# El proyecto debería estar en: http://localhost/rupin

# Opción 2: Usar el servidor integrado de CodeIgniter
php spark serve
# Acceder a: http://localhost:8080
```

### 2.2 Verificaciones Iniciales

**✅ Redirección a Login**
- Acceder a `http://localhost/rupin`
- Debe redirigir a `http://localhost/rupin/login`

**✅ Página de Login**
- La página debe cargar correctamente
- Debe mostrar formulario con campos: Username/Email y Password
- Debe haber link a "Registrarse"
- Debe tener selector de idioma

**✅ Login de Administrador**
```
Usuario: admin
Contraseña: admin123
```
- Después de login, debe redirigir a dashboard
- Dashboard debe mostrar bienvenida
- Sidebar debe estar visible

**✅ Logout**
- Hacer click en logout (esquina superior derecha)
- Debe redirigir a login
- No debe poder acceder a dashboard sin login

---

## 📋 FASE 3: VALIDACIÓN DE FUNCIONALIDADES (Días 1-2)

### 3.1 Autenticación

```
TESTING CHECKLIST:

☐ Login con usuario correcto
  → Debe entrar al dashboard
  
☐ Login con usuario incorrecto
  → Debe mostrar mensaje de error
  
☐ Intentos fallidos 5 veces
  → Debe bloquear temporalmente (15 minutos)
  
☐ Logout
  → Debe redirigir a login
  → Session debe terminar
  
☐ Acceder a ruta protegida sin login
  → Debe redirigir a login
```

### 3.2 Gestión de Usuarios

```
TESTING CHECKLIST:

☐ Acceder a /users
  → Debe listar usuarios
  
☐ Crear usuario
  → Llenar formulario
  → Email único (validar duplicado)
  → Contraseña fuerte (8+ caracteres)
  → Debe crear correctamente
  
☐ Editar usuario
  → Cambiar datos
  → Debe guardar cambios
  
☐ Eliminar usuario
  → Debe eliminarse (soft delete)
  → Debe seguir en BD pero con deleted_at
  
☐ Asignar rol a usuario
  → Asignar rol a usuario
  → Verificar que tiene permiso del rol
```

### 3.3 Roles y Permisos

```
TESTING CHECKLIST:

☐ Crear rol
  → Llenar formulario
  → Guardar correctamente
  
☐ Asignar permisos a rol
  → Seleccionar permisos
  → Guardar correctamente
  
☐ Usuario con rol tiene permisos
  → Login con usuario
  → Verificar que ve opciones permitidas
  → Verificar que no ve opciones prohibidas
  
☐ Super Admin tiene todos los permisos
  → Debe ver todas las secciones
```

### 3.4 Auditoría

```
TESTING CHECKLIST:

☐ Eventos registrados
  → Crear usuario → registra "created"
  → Editar usuario → registra "updated"
  → Eliminar usuario → registra "deleted"
  → Login → registra "login"
  → Logout → registra "logout"
  
☐ Filtros funcionan
  → Filtrar por usuario
  → Filtrar por evento
  → Filtrar por fecha
  
☐ Exportación a CSV
  → Exportar logs
  → Archivo debe descargar
```

### 3.5 Idioma

```
TESTING CHECKLIST:

☐ Cambiar a Inglés
  → Página debe mostrar en inglés
  → Preferencia debe guardarse
  
☐ Cambiar a Español
  → Página debe mostrar en español
  → Preferencia debe guardarse
  
☐ Logout y login nuevamente
  → Debe mantener el idioma elegido
```

### 3.6 Configuración

```
TESTING CHECKLIST:

☐ Acceder a /settings
  → Debe mostrar formulario
  
☐ Cambiar nombre de app
  → Guardar nuevo nombre
  → Logout y verificar en página de login
  
☐ Subir imagen de login
  → Seleccionar imagen PNG/JPG
  → Subir correctamente
  → Aparecer en login
  
☐ Subir favicon
  → Seleccionar favicon.ico
  → Subir correctamente
```

---

## 📋 FASE 4: VALIDACIÓN DE SEGURIDAD (Día 2)

### 4.1 Validaciones

```
TESTING CHECKLIST:

☐ Email duplicado
  → Intentar crear usuario con email existente
  → Debe mostrar error
  
☐ Contraseña débil
  → Intentar registrarse con contraseña < 8 caracteres
  → Debe mostrar error
  
☐ Username duplicado
  → Intentar crear usuario con username existente
  → Debe mostrar error
  
☐ XSS Prevention
  → Intentar insertar <script> en campos
  → Debe escaparse correctamente
```

### 4.2 Control de Acceso

```
TESTING CHECKLIST:

☐ Usuario sin permiso no puede ver sección
  → Crear usuario con rol "Usuario"
  → Debe ver dashboard
  → No debe ver /users, /roles, /permissions
  
☐ Usuario sin permiso no puede acceder directo
  → Intentar /users directamente
  → Debe mostrar error 403
  
☐ No puede eliminarse a sí mismo
  → Intentar eliminar su propio usuario
  → Debe mostrar error
```

### 4.3 Brute Force Protection

```
TESTING CHECKLIST:

☐ Bloqueo después de 5 intentos
  → Intentar login 5 veces con contraseña incorrecta
  → En el 6º intento, debe mostrar bloqueo
  → Esperar 15 minutos o acceder a BD para cambiar locked_until
  
☐ Mensaje claro de bloqueo
  → Debe indicar cuándo se desbloqueará
```

---

## 📋 FASE 5: DOCUMENTACIÓN INICIAL (Día 2)

### 5.1 Documentación para Equipo

Crear en `proyectos/` un archivo `INICIO_RAPIDO.md`:

```markdown
# Inicio Rápido

## Primeros Pasos

1. Clonar/descargar proyecto
2. Ejecutar `composer install`
3. Copiar `.env.example` a `.env`
4. Configurar BD en `.env`
5. Ejecutar `php spark migrate`
6. Ejecutar seeders
7. Acceder a `http://localhost/rupin`

## Credenciales por Defecto

| Usuario | Contraseña | Rol |
|---------|-----------|-----|
| admin | admin123 | Super Admin |

## Estructura del Proyecto

- `app/Controllers/` - Controladores
- `app/Models/` - Modelos
- `app/Views/` - Vistas
- `app/Database/Migrations/` - Migraciones
- `app/Database/Seeds/` - Seeders
- `public/css/custom.css` - Estilos

## Cambiar Contraseña Admin

1. Login con admin/admin123
2. Ir a Perfil
3. Cambiar contraseña
```

### 5.2 Crear Issues/Bugs Encontrados

Si se encuentran bugs durante testing:

1. Documentar en `proyectos/BUGS_ENCONTRADOS.md`
2. Incluir: Descripción, Pasos para reproducir, Comportamiento esperado
3. Priorizar (Critical, High, Medium, Low)

**Ejemplo:**
```markdown
# Bug: Login falla con espacios en contraseña

## Descripción
Al intentar login con contraseña que tiene espacios, falla.

## Pasos para Reproducir
1. Registrarse con contraseña: "mi contraseña 123"
2. Logout
3. Intentar login
4. Falla

## Comportamiento Esperado
Debe permitir espacios en contraseña

## Prioridad
Medium
```

---

## 📋 FASE 6: CONFIGURACIÓN PARA PRODUCCIÓN (Semana 1)

### 6.1 Variables de Entorno

```env
# Cambiar a production
CI_ENVIRONMENT = production

# URL correcta
app.baseURL = https://tudominio.com

# BD remota
database.default.hostname = servidor-bd.com
database.default.database = rupin_prod
database.default.username = usuario_bd
database.default.password = contraseña_segura

# SMTP para emails
email.fromEmail = noreply@tudominio.com
email.SMTPHost = smtp.gmail.com
email.SMTPUser = tu-email@gmail.com
email.SMTPPass = contraseña-app
```

### 6.2 Seguridad

```
☐ Cambiar contraseña admin
☐ Configurar HTTPS
☐ Desactivar debug (CI_ENVIRONMENT = production)
☐ Configurar headers de seguridad
☐ Backup automático de BD
☐ Logs rotados
☐ Email configurado
```

### 6.3 Optimización

```
☐ Habilitar caching
☐ Minificar assets
☐ Optimizar imágenes
☐ Comprimir respuestas
```

---

## 🚀 HITO IMPORTANTE

**Cuando se complete la FASE 5 (Documentación Inicial), el proyecto estará LISTO PARA:**

1. ✅ Desarrollo de funcionalidades nuevas
2. ✅ Integración con otros sistemas
3. ✅ Testing en ambiente staging
4. ✅ Deploy a producción

---

## 📊 TIMELINE RECOMENDADO

| Fase | Duración | Estado |
|------|----------|--------|
| **Fase 1: Validación Inicial** | 1-2 horas | 📋 Por hacer |
| **Fase 2: Pruebas de Acceso** | 30 minutos | 📋 Por hacer |
| **Fase 3: Validación Funcional** | 4-6 horas | 📋 Por hacer |
| **Fase 4: Validación Seguridad** | 3-4 horas | 📋 Por hacer |
| **Fase 5: Documentación** | 2 horas | 📋 Por hacer |
| **Fase 6: Producción** | 1 semana | 📋 Por hacer |
| **TOTAL** | **~2 semanas** | |

---

## ✅ CHECKLIST FINAL

```
ANTES DE INICIAR DESARROLLO:

☐ BD creada y migrada
☐ Seeders ejecutados
☐ .env configurado correctamente
☐ Server iniciado sin errores
☐ Login funciona con admin/admin123
☐ Dashboard accesible
☐ Logout funciona
☐ Auditoría registra eventos
☐ Cambio de idioma funciona
☐ CRUD de usuarios funciona
☐ Sistema de permisos funciona
☐ Documentación inicial creada
☐ Issues/Bugs documentados

SISTEMA LISTO PARA DESARROLLO ✅
```

---

## 📞 SOPORTE

Si surgen problemas durante el inicio:

### Logs para revisar
- `writable/logs/` - Logs de CodeIgniter
- `writable/debugbar/` - Debugbar de desarrollo

### Comandos útiles
```bash
# Ver migraciones ejecutadas
php spark migrate:status

# Revertir última migración
php spark migrate:rollback

# Ejecutar una migración específica
php spark migrate:refresh

# Limpiar caché
php spark cache:clear
```

---

**Documento Generado:** 1 de Febrero de 2026  
**Versión:** 1.0  
**Estado:** ✅ PLAN LISTO

