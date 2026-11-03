# Plan — Componente PQRSF (solicitudes desde QR publicados)

> Documento para **validar y aprobar** antes de construir. Sigue el contrato de componentes
> (`proyectos/CONTRATO_COMPONENTES.md`).

**Identidad:** `components/Pqrsf/` · code `pqr` · tablas `cmp_pqr_` · rutas `/pqrsf` · permisos `pqr.*`
Drop-in: no toca `app/` salvo **una** migración del núcleo para el plan (§11).

---

## 1. El flujo, de punta a punta

1. La empresa crea **puntos de publicación** (Recepción, Bodega, Sede Norte, Sala de espera…). Cada punto
   tiene **su propia URL con token aleatorio** —no el id, para que no se puedan recorrer— con su **QR** y un
   **cartel imprimible en PDF** para pegar en el área.
2. El ciudadano escanea y elige **identificarse o enviar de forma anónima**. Llena el formulario (tipo PQRSF,
   asunto, mensaje, evidencia), **acepta los términos y las autorizaciones** y resuelve el **captcha**. Recibe
   un **radicado** `PQR-000001` y un **enlace de consulta** de su caso.
3. Si se identificó **y autorizó recibir comunicaciones**, le llega **de inmediato** un **acuse de recibo
   automático** con el radicado, el enlace y la fecha máxima de respuesta (plantilla + resumen de la IA). Si
   es anónima o no autorizó, **el enlace del ticket es la única vía** y la pantalla se lo advierte.
4. El caso entra a la **bandeja**: se **asigna un responsable**, se conversa en un **hilo colaborativo**
   (notas internas, cambios de estado, adjuntos) y avanza por estados con **fecha de vencimiento** según el
   tipo.
5. La **IA deja un borrador** de la respuesta de fondo; el gestor lo edita o lo regenera con instrucciones.
6. Al **aprobar**, el gestor **elige por dónde avisar** (correo · WhatsApp · SMS, entre los disponibles) y el
   sistema envía con los canales de la empresa. **El resultado de cada envío queda en el historial.**

### Decisiones tomadas (validadas)

- **La IA no responde sola.** Acuse automático inmediato + borrador de la respuesta de fondo que **solo sale
  cuando un humano lo aprueba**. Es lo defendible: nadie puede alegar que «la máquina respondió sola».
- **El gestor elige los canales** al aprobar, entre los que dejó el ciudadano y los que la empresa tenga
  configurados en «Mi empresa → Canales».
- **Adjuntos desde el día uno**, del ciudadano y del gestor. En PQRSF la evidencia suele ser el caso.

---

## 1.b Anonimato, consentimientos y captcha

**Solicitud anónima.** El formulario abre con la elección **«Me identifico» / «Enviar anónima»**. En anónima
**no se piden ni se guardan** nombre, documento, correo ni teléfono: el **enlace del ticket es la única forma de
seguir el caso**, y por eso la pantalla de gracias lo muestra grande, copiable y con un aviso claro de que lo
guarde. La empresa puede **desactivar el anonimato** en la configuración (hay operaciones que exigen
identificación); si está desactivado, la opción no aparece.

**Tres autorizaciones separadas**, porque jurídicamente lo son:

| Casilla | Cuándo | Qué implica |
|---|---|---|
| **Términos y condiciones** | siempre, obligatoria | Sin marcarla no se radica |
| **Tratamiento de datos personales** | solo si se identifica, obligatoria | Con **enlace a la política de privacidad** de la empresa |
| **Recibir comunicaciones** de esta solicitud | solo si se identifica, **opcional** | **Si no la marca, el sistema no le escribe**: al aprobar, los canales salen deshabilitados con el motivo, y la respuesta queda publicada en su ticket |

Esa última casilla no es decorativa: **manda sobre el envío**. El gestor no puede escribirle a quien no
autorizó, y la pantalla se lo explica en vez de fallar en silencio.

**Trazabilidad del consentimiento.** De cada solicitud se sella **fecha, IP y la versión del texto aceptado**
(`policy_version`): un consentimiento sin fecha ni versión no prueba nada el día que alguien reclame.

**Captcha propio, sin CDN.** La política del proyecto es cero recursos externos, así que nada de reCAPTCHA ni
hCaptcha. Se resuelve con tres barreras, todas server-side:
1. **Imagen con GD** (la misma extensión que ya usa el QR) con 5 caracteres; el valor va **hasheado en sesión**,
   con caducidad y botón de refrescar. Comparación sin distinguir mayúsculas.
2. **Campo trampa** (honeypot) invisible: si viene lleno, es un bot.
3. **Tiempo mínimo** entre abrir y enviar el formulario.

Alternativa accesible: junto a la imagen, una **pregunta aritmética en texto** para lectores de pantalla. El
captcha se puede desactivar por empresa (p. ej. en un punto interno de bodega).

---

## 2. Datos (5 tablas, portables §8)

| Tabla | Contenido |
|---|---|
| `cmp_pqr_points` | `company_id`, nombre, área, `token` (URL pública), `is_active`, `visits`, notas |
| `cmp_pqr_requests` | `company_id`, `point_id`, `number` (radicado), `type`, `subject`, `message`, `priority`, `status`, `assignee_id`, `due_at`, **`is_anonymous`**, datos del ciudadano (nombre, documento, correo, teléfono, ciudad — **vacíos si es anónima**), **`accept_terms`, `consent_data`, `consent_comms`, `consent_at`, `consent_ip`, `policy_version`**, `ticket_token`, `ai_summary`, `ai_draft`, `answer`, `answered_at`, `answered_by`, `closed_at` |
| `cmp_pqr_events` | **el hilo**: `request_id`, `user_id` (null = el ciudadano), `type` (`nota`\|`estado`\|`asignacion`\|`respuesta`\|`envio`\|`adjunto`), `body`, `meta`, `created_at` |
| `cmp_pqr_attachments` | polimórficos (`owner_type`/`owner_id`), archivos **privados** en `writable/uploads/pqrsf`, descarga por controlador con permiso y aislada por empresa |
| `cmp_pqr_settings` | 1 por empresa: prefijo del radicado, numeración, **días de respuesta por tipo**, **`allow_anonymous`**, **`privacy_url` / `privacy_text`**, **`terms_url` / `terms_text`**, **`policy_version`**, **`captcha_enabled`**, IA on/off, tono de la IA, plantilla del acuse |

**Tipos:** Petición · Queja · Reclamo · Sugerencia · Felicitación (VARCHAR + validación en el modelo, sin ENUM).
**Estados:** `recibida` → `en_gestion` → `respondida` → `cerrada`, más `anulada`. El vencimiento se calcula al
radicar con los días del tipo.

---

## 3. Pantallas

**Bandeja** — métricas (abiertas · vencidas · respondidas este mes · tiempo promedio) y **filtros en una sola
fila** (buscador · estado · tipo · punto · responsable), con `DataTable` del núcleo. Las vencidas se marcan.

**Detalle** — datos del ciudadano y de dónde vino (punto/área), el **hilo colaborativo**, asignar responsable,
cambiar estado, adjuntos, **redactar/regenerar con IA** y **«Aprobar y enviar»** con la selección de canales —
deshabilitados, con el motivo a la vista, si la solicitud es anónima o el ciudadano no autorizó
comunicaciones—. El detalle muestra el **sello del consentimiento** (fecha, IP y versión aceptada).

**Puntos QR** — CRUD, enlace copiable, **QR descargable** y **cartel imprimible** (dompdf: QR grande + logo +
«Escanea para dejar tu PQRSF»).

**Configuración** — prefijo y numeración, días por tipo, **anonimato on/off**, **política de privacidad y
términos** (enlace o texto propio, con su **versión**), **captcha on/off**, IA (on/off + tono) y plantilla del
acuse.

**Públicas y autosuficientes** (contrato §7.3: CSS propio en línea, iconos SVG en línea, cero CDN):
formulario del punto (con la elección identificarme/anónima, las tres casillas, los enlaces a política y
términos, y el captcha), gracias con el radicado —y el **enlace del ticket destacado** cuando es la única vía—
y **consulta del estado** por ese enlace.

---

## 4. Integraciones del núcleo (nada nuevo que instalar)

- **IA:** `App\Libraries\AiClient::generate()` (Gemini/OpenAI según Configuración → IA). Si no hay clave, el
  componente **sigue funcionando**: se salta el borrador y avisa en la pantalla.
- **Envíos:** `CompanyMailer::send()`, `CompanyWhatsApp::sendText()`, `CompanySms::send()` — cada uno devuelve
  `ok` + `via`, y eso es lo que se guarda en el evento de envío.
- **QR:** copia propia de `QrService` (§0.4: un componente no depende de otro).
- **PDF:** copia propia de `PdfService` (dompdf).

## 5. Plan (§11)

Migración del núcleo `AddPqrsfLimitsToPlans`: **`pqrsf_enabled`** + **`max_requests`** (`0` = ilimitado), con
sus campos en el formulario de planes, `PlanModel` y `PlanController::collect()`. `PlanGuard` propio del
componente hace valer el servicio y el cupo **en el formulario público**, con aviso claro.

---

## 6. Cómo se valida (esto es lo que voy a comprobar)

Autotest CLI temporal + pruebas HTTP reales, y se borra al terminar:

1. **Numeración** `PQR-000001` transaccional: consecutiva, sin huecos, sin repetir con dos envíos a la vez.
2. **Token del punto**: 32 hex, no enumerable; punto inactivo o empresa inactiva → 404.
3. **Aislamiento**: una empresa no ve ni gestiona solicitudes, puntos ni adjuntos de otra.
4. **La IA no envía sola**: con IA activa, tras radicar el estado es `recibida` y `answer` está vacío; solo el
   flujo de aprobación escribe `answer` y dispara envíos.
5. **Sin clave de IA** el radicado igual entra (degradación limpia).
6. **Envíos**: al aprobar se registra un evento por canal con su `via` y su resultado; si la empresa no tiene
   ese canal configurado, falla con mensaje y **no** marca la solicitud como respondida a medias.
7. **Vencimientos**: `due_at` se calcula con los días del tipo; el listado marca las vencidas.
8. **Consulta pública**: el ticket muestra estado y respuesta, y **no** filtra datos internos (notas, nombres
   de gestores).
8.b **Anónima**: no se guarda nombre, documento, correo ni teléfono aunque vengan en el POST; el ticket sigue
   funcionando; con el anonimato desactivado la opción no existe y un POST forzado se rechaza.
8.c **Consentimientos**: sin términos no se radica; identificándose, sin autorización de datos tampoco;
   **sin autorización de comunicaciones el envío queda bloqueado** (el gestor ve el motivo) y la respuesta solo
   se publica en el ticket. Se comprueba que quedan sellados fecha, IP y versión.
8.d **Captcha**: código errado o vencido → no se crea nada y el formulario conserva lo escrito; el honeypot
   lleno se rechaza; el envío instantáneo se rechaza; con el captcha desactivado en esa empresa, entra.
9. **Adjuntos**: tipo/tamaño validados, quedan fuera de `public/`, y la descarga exige permiso y empresa.
10. **Plan**: sin servicio o sin cupo, el formulario público lo dice y no crea nada.
11. **i18n** es/en simétricos y **cero CDN** en las vistas públicas.
12. Prueba de humo por HTTP: crear punto → abrir su URL → radicar con adjunto → ver acuse → asignar → borrador
    IA → aprobar y enviar → consultar el ticket.

---

## 7. Fuera de alcance (para no inflar la primera entrega)

Encuesta de satisfacción al cerrar · escalamiento automático por vencimiento · reportes exportables · firma
electrónica · anonimato total · portal con cuenta para el ciudadano · plantillas de respuesta reutilizables.
Todo cabe después sin romper lo que se entregue.
