# LeadFlow Legal
## componente de gestión y conversión de leads integrada con Docketwise

**Versión:** 1.0  
**Fecha:** 10 de septiembre de 2026

## 1. Objetivo
Desarrollar un componente para captar, calificar, gestionar y convertir prospectos de firmas jurídicas, utilizando Docketwise como sistema jurídico principal después de la conversión.

```text
Publicidad / Web / Redes / Referidos
→ Lead → Precalificación → Lead Scoring → Seguimiento
→ Consulta → Propuesta/Contrato → Ganado → Docketwise
→ Contact + Matter
```

## 2. Responsabilidad de LeadFlow Legal
- Captación de leads.
- Formularios dinámicos.
- Pipeline comercial.
- Lead scoring.
- Actividades y tareas.
- Agenda de consultas.
- Seguimientos.
- Automatizaciones.
- Comunicaciones.
- IA para apoyo comercial.
- Fuentes y campañas.
- Analítica de conversión.
- Conversión hacia Docketwise.

## 3. Responsabilidad de Docketwise
- Contactos/clientes.
- Matters o asuntos.
- Gestión jurídica.
- Documentos del expediente.
- Notas.
- Facturación y demás funciones disponibles mediante API.

LeadFlow Legal complementa Docketwise; no pretende reemplazarlo.

## 4. Arquitectura
```text
FUENTES
Web | Meta | Google | WhatsApp | Referidos
                    ↓
             LEADFLOW LEGAL
                    ↓
Leads | Pipeline | Formularios | Scoring
Actividades | Consultas | IA | Automatizaciones
Marketing | Analytics | Comunicaciones
                    ↓
            CAPA DE INTEGRACIÓN
                    ↓
                DOCKETWISE
Contact | Matter | Notes | Documents | Invoices
```

## 5. SaaS multitenant
Todos los registros funcionales deben manejar `tenant_id`.

```text
Plataforma
 ├── empresa A
 │    ├── Administradores
 │    ├── Abogados
 │    ├── Asesores
 │    ├── Leads
 │    └── Cuenta Docketwise
 ├── empresa B
 └── empresa C
```

Cada empresa tendrá usuarios, pipeline, servicios, formularios, automatizaciones, métricas e integración Docketwise independientes.

## 6. Roles
### Superadministrador
Gestión de tenants, planes, configuraciones globales y auditoría.

### Administrador de firma
Usuarios, roles, servicios, pipeline, formularios, integraciones, automatizaciones y reportes.

### Supervisor comercial
Todos los leads, asignaciones, pipeline, métricas, seguimientos y tiempos de respuesta.

### Asesor
Leads asignados, actividades, notas, consultas y seguimientos.

### Abogado
Revisión de precalificación, valoración, solicitudes de información y conversión.

### Auditor
Solo lectura.

## 7. Menú principal
```text
Inicio
Leads
 ├── Todos
 ├── Mis leads
 ├── Nuevos
 ├── Sin asignar
 └── Importar
Pipeline
Consultas
Actividades
Formularios
Automatizaciones
Comunicaciones
Marketing
 ├── Fuentes
 └── Campañas
Reportes
Docketwise
 ├── Conexión
 ├── Sincronización
 ├── Conversiones
 └── Errores
Configuración
```

## 8. Entidad Lead
```text
id
tenant_id
first_name
middle_name
last_name
email
phone
whatsapp
country
city
preferred_language
service_id
source_id
campaign_id
assigned_user_id
pipeline_stage_id
priority
score
status
potential_value
created_at
updated_at
last_contact_at
next_activity_at
utm_source
utm_medium
utm_campaign
utm_content
utm_term
landing_page
referrer
```

La información jurídica variable se maneja mediante formularios dinámicos.

## 9. Pipeline
```text
NUEVO
↓
CONTACTADO
↓
PRECALIFICACIÓN
↓
CALIFICADO
↓
CONSULTA PROGRAMADA
↓
CONSULTA REALIZADA
↓
PROPUESTA
↓
SEGUIMIENTO
↓
GANADO
↓
CONVERTIDO A DOCKETWISE
```

Estados alternativos: No calificado, Sin respuesta, No-show, Perdido y Duplicado.

Cada tenant puede configurar etapas, orden, probabilidad y automatizaciones.

## 10. Kanban
Debe soportar drag & drop y filtros por asesor, abogado, servicio, fuente, campaña, prioridad, score, fecha, leads sin actividad y actividades vencidas.

Cada tarjeta muestra nombre, servicio, responsable, score, tiempo en etapa, próxima actividad y alertas.

## 11. Ficha 360 del Lead
```text
María López
Visa familiar
Score: 87 | Prioridad: Alta
Estado: Consulta realizada
Responsable: Andrea

[WhatsApp] [Email] [Actividad] [Consulta] [Convertir]
```

Pestañas: Resumen, Timeline, Información, Precalificación, Actividades, Consultas, Comunicaciones, Documentos y Docketwise.

## 12. Timeline
Registrar automáticamente creación, cambios de etapa/responsable, llamadas, emails, WhatsApp, notas, formularios, documentos, consultas, automatizaciones, conversiones, sincronizaciones y errores.

## 13. Formularios dinámicos
Campos: texto, texto largo, email, teléfono, número, fecha, selección, selección múltiple, Sí/No, país, dirección y archivo.

Debe soportar lógica condicional:
```text
¿Se encuentra en Estados Unidos?
 ├── Sí → ¿Con qué estatus ingresó?
 └── No → ¿En qué país se encuentra?
```

Los formularios pueden asociarse con servicios, campañas, landing pages y etapas.

## 14. Servicios jurídicos
Catálogo configurable. Ejemplos: visa familiar, visa de trabajo, asilo, ciudadanía, residencia, ajuste de estatus, defensa de deportación y consulta general.

Cada servicio puede definir formulario, pipeline, preguntas, reglas de scoring, responsable, automatizaciones y tipo de Matter en Docketwise.

## 15. Lead Scoring
Primera versión basada en reglas configurables.

```text
0-39    Baja
40-69   Media
70-100  Alta
```

| Criterio | Puntos |
|---|---:|
| Servicio prioritario | +20 |
| Formulario completo | +10 |
| Documentos disponibles | +10 |
| Solicita consulta | +15 |
| Respondió comunicación | +10 |
| Consulta programada | +15 |
| Referido | +10 |
| Sin respuesta > 7 días | -20 |

## 16. Inteligencia Artificial
La IA apoyará al equipo sin sustituir la valoración profesional.

Funciones:
- Resumir el caso del prospecto.
- Extraer información relevante.
- Detectar información faltante.
- Sugerir servicio y prioridad.
- Resumir conversaciones.
- Proponer próxima acción.
- Crear borradores de seguimiento.
- Generar resumen para el abogado.

## 17. Actividades
Tipos: llamada, email, WhatsApp, revisar información, solicitar documento, programar consulta, seguimiento y otro.

```text
id
tenant_id
lead_id
assigned_user_id
activity_type
title
description
due_at
completed_at
status
priority
created_by
```

## 18. Agenda de consultas
Crear, reprogramar, cancelar, confirmar, registrar asistencia, no-show y resultado.

Resultados: contratación probable, seguimiento, pendiente documentación, no elegible, no interesado y escalar a abogado.

## 19. Automatizaciones
Modelo:
```text
TRIGGER → CONDICIONES → ACCIONES
```

Ejemplo:
```text
Trigger: Lead creado
Condición: Fuente = Facebook
Acciones:
- Asignar asesor
- Calcular score
- Crear actividad
- Enviar bienvenida
```

Triggers iniciales: lead creado, cambio de etapa, formulario completado, score alcanzado, actividad vencida, consulta programada/realizada, no-show y lead ganado.

## 20. Integración Docketwise
Cada tenant conecta su propia cuenta mediante OAuth. Los tokens deben almacenarse cifrados.

### Conversión
```text
Lead ganado
↓
Validar datos
↓
Buscar posible Contact existente
↓
Crear o relacionar Contact
↓
Crear Matter
↓
Transferir notas relevantes
↓
Transferir documentos autorizados
↓
Guardar IDs externos
↓
Marcar como convertido
```

## 21. Mapeo externo
```text
integration_mappings
id
tenant_id
provider
entity_type
local_id
external_id
created_at
updated_at
```

Ejemplo:
```text
lead      9872 → docketwise contact 28451
matter    4521 → docketwise matter 77291
document  1002 → docketwise document 91811
```

## 22. Botón Convertir a cliente
```text
[ CONVERTIR A CLIENTE ]
```

El modal permitirá elegir tipo de Matter y abogado, y decidir si transfiere información, notas, documentos y resumen de precalificación.

La operación debe ser idempotente para impedir duplicados por reintentos.

## 23. Prevención de duplicados
Comparar email normalizado, teléfono, WhatsApp, nombre + teléfono y otros identificadores permitidos.

Antes de crear en Docketwise también debe intentarse identificar contactos existentes cuando la API lo permita.

## 24. Documentos preliminares
```text
id
tenant_id
lead_id
document_type_id
original_name
storage_key
mime_type
size
hash
uploaded_by
created_at
```

Solo los documentos autorizados se transfieren a Docketwise.

## 25. Comunicaciones
### MVP
- Registro de llamadas.
- Email.
- Notas.

### Fase 2
- WhatsApp Business Platform.
- Microsoft 365 / Google.
- SMS si aplica.
- Telefonía.

Toda comunicación debe quedar asociada al lead y al timeline.

## 26. Marketing Attribution
Registrar source, medium, campaign, content, term, landing_page y referrer.

```text
Campaña → Leads → Calificados → Consultas → Ganados → Ingresos
```

## 27. Dashboard
Indicadores:
- Leads generados y calificados.
- Consultas y propuestas.
- Clientes ganados.
- Conversión.
- Tiempo de primera respuesta.
- Conversión por fuente/campaña/servicio/asesor/abogado.
- Leads perdidos y razones.
- Valor potencial y ganado.

Medir especialmente primera respuesta: `<5 min`, `5-30 min`, `30-60 min`, `1-4 h`, `>4 h`.

## 28. Modelo de datos inicial
```text
tenants
users
roles
permissions
leads
lead_sources
campaigns
services
pipelines
pipeline_stages
lead_stage_history
forms
form_fields
form_rules
form_submissions
form_answers
activities
notes
appointments
documents
document_types
communications
communication_templates
scoring_rules
lead_score_history
automations
automation_triggers
automation_conditions
automation_actions
automation_runs
integrations
integration_mappings
integration_logs
timeline_events
audit_logs
```

## 29. API interna sugerida
```text
/api/leads
/api/leads/{id}
/api/leads/{id}/activities
/api/leads/{id}/timeline
/api/leads/{id}/documents
/api/leads/{id}/appointments
/api/leads/{id}/score
/api/leads/{id}/convert
/api/pipelines
/api/forms
/api/services
/api/campaigns
/api/automations
/api/integrations/docketwise/connect
/api/integrations/docketwise/callback
/api/integrations/docketwise/sync
/api/integrations/docketwise/status
```

## 30. Stack propuesto
```text
Backend: PHP + CodeIgniter 4
Base de datos: MariaDB o PostgreSQL
Cache/colas: Redis
Frontend: Bootstrap + JavaScript, Vue o React
IA: OpenAI API y/o proveedor configurable
Integraciones: Docketwise, WhatsApp Business Platform,
Microsoft 365, Google Calendar, Meta Leads y Google Ads
```

Docketwise debe implementarse mediante un adaptador desacoplado.

## 31. Seguridad
- Separación estricta por tenant_id.
- RBAC.
- MFA para usuarios privilegiados.
- Cifrado de secretos y tokens.
- HTTPS.
- Auditoría.
- Validación y hash de archivos.
- CSRF y rate limiting.
- Sesiones seguras.
- Retención y backups.
- Logs de integración.
- Mínimo privilegio.
- Consentimientos y avisos aplicables.

## 32. MVP
1. SaaS multitenant.
2. Usuarios y roles.
3. Captura de leads.
4. Ficha 360.
5. Pipeline Kanban.
6. Actividades y seguimiento.
7. Formularios de precalificación.
8. Servicios jurídicos.
9. Lead scoring por reglas.
10. Agenda básica.
11. Timeline.
12. OAuth Docketwise.
13. Conversión `Lead → Contact + Matter`.
14. Transferencia controlada de notas/documentos.
15. Dashboard comercial básico.

## 33. Fase 2
WhatsApp, correo, calendarios, automatizaciones avanzadas, IA, portal del prospecto, Meta Lead Ads, Google Ads, plantillas y notificaciones.

## 34. Fase 3
Attribution avanzada, rentabilidad, call center, grabación/transcripción, scoring predictivo, IA de conversaciones, workflows avanzados, API pública, webhooks y marketplace de integraciones.

## 35. Principio UX
La aplicación debe responder rápidamente:
```text
¿Qué leads nuevos tengo?
¿A quién debo contactar ahora?
¿Qué actividad está vencida?
¿Quién tiene una consulta hoy?
¿Qué leads tienen mayor probabilidad?
¿Por qué se pierden prospectos?
¿Qué campaña produce clientes?
¿Qué lead está listo para Docketwise?
```

## 36. Diferenciador comercial
No posicionarlo simplemente como “CRM para abogados”.

> Plataforma de captación y conversión de prospectos para firmas jurídicas, integrada con Docketwise.

```text
INTERESADO → LEAD → CALIFICADO → CONSULTA → CONTRATACIÓN
→ CLIENTE + MATTER EN DOCKETWISE
```

## 37. Arquitectura de integraciones
Diseñar una interfaz común:
```text
LeadFlow Legal
      ↓
Integration Interface
      ↓
Docketwise Adapter
```

Esto permitirá incorporar posteriormente otros sistemas jurídicos sin rediseñar el núcleo del CRM.

## 38. Recomendación de desarrollo
Comenzar por el flujo completo y demostrable:

```text
Landing/Formulario
→ Lead
→ Pipeline
→ Precalificación
→ Actividad
→ Consulta
→ Ganado
→ Convertir
→ Contact + Matter en Docketwise
```

Una vez estable, añadir automatización, comunicaciones e IA sobre ese núcleo.
