# SDD — Supplier Portal

## 1. Nombre del sistema

**Supplier Portal**

Portal profesional para suppliers sin API/SOAP, orientado a operación de reservas, carga de tarifas, promociones, disponibilidad, inventario, reportes y configuración comercial.

---

## 2. Objetivo

Construir una aplicación modular en Laravel 13.x + Filament + PostgreSQL que permita a cada supplier gestionar únicamente su propia información.

El sistema debe ser:

- Modular como LEGO.

- Seguro multi-supplier.

- Resiliente ante fallas.

- Sin exposición de errores 500 al usuario.

- Preparado para crecimiento.

- Preparado para integrarse con `supplier-service` y `quote-engine`.

- Mantenible bajo Clean Architecture, SOLID, DRY, KISS y Clean Code.

---

## 3. Alcance funcional

### 3.1 Módulos principales

1. Autenticación y autorización.

2. Gestión de suppliers.

3. Gestión de usuarios por supplier.

4. Límite configurable de usuarios por supplier.

5. Reservas.

6. Confirmación/rechazo de reservas.

7. Carga de precios por Excel.

8. Gestión manual de tarifas.

9. Vigencias de precios.

10. Promociones.

11. Disponibilidad.

12. Inventario/flota.

13. Reportes y consultas.

14. Exportación a Excel.

15. Auditoría.

16. Observabilidad.

17. Manejo profesional de errores.

18. Fallback local SQLite.

19. Sincronización SQLite → PostgreSQL.

20. Alertas por email ante errores críticos.

---

## 4. Stack técnico

```txt
Laravel 13.x
Filament
PostgreSQL
SQLite fallback local
Redis
Laravel Queues
Laravel Horizon
Laravel Pulse
Laravel Octane, en fase posterior
Laravel Excel
Spatie Permission
Spatie Activity Log
Laravel Fortify / 2FA
Pest
PHPStan / Larastan
Laravel Pint
Rector
```

---

## 5. Principios arquitectónicos

### 5.1 Reglas base

```txt
Filament no contiene lógica de negocio.
Filament solo presenta UI.
Toda acción pasa por Use Cases.
Toda regla de negocio vive en Domain.
Toda persistencia vive en Infrastructure.
Todo acceso multi-supplier se valida por SupplierContext + Policies.
```

### 5.2 Estilo arquitectónico

```txt
Modular Monolith
Clean Architecture
Vertical Slices
Domain-driven modules
Event-driven internal flow
Outbox Pattern
Fail-safe error handling
```

---

## 6. Estructura del proyecto

```txt
app/
├── Modules/
│   ├── Identity/
│   ├── Supplier/
│   ├── Booking/
│   ├── Pricing/
│   ├── Promotion/
│   ├── Fleet/
│   ├── Availability/
│   ├── Import/
│   ├── Reporting/
│   ├── Audit/
│   ├── Integration/
│   └── System/
│
├── Shared/
│   ├── DTOs/
│   ├── Enums/
│   ├── Exceptions/
│   ├── ValueObjects/
│   ├── Support/
│   └── Contracts/
│
└── Providers/
```

Cada módulo debe seguir esta estructura:

```txt
Module/
├── Domain/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Rules/
│   ├── Exceptions/
│   └── Events/
│
├── Application/
│   ├── UseCases/
│   ├── DTOs/
│   └── Services/
│
├── Infrastructure/
│   ├── Persistence/
│   ├── Eloquent/
│   ├── Repositories/
│   └── External/
│
├── Presentation/
│   └── Filament/
│
├── Policies/
├── Jobs/
└── Tests/
```

---

## 7. Multi-supplier

### 7.1 Regla principal

Un supplier solo puede ver, modificar, exportar y operar datos propios.

Todas las tablas operativas deben tener `supplier_id`.

```txt
users
bookings
rates
rate_imports
rate_import_rows
promotions
availability_rules
vehicles
report_exports
audit_logs
```

### 7.2 SupplierContext

Crear servicio:

```txt
Shared/Support/SupplierContext
```

Responsabilidades:

```txt
Obtener supplier actual.
Validar que el usuario tenga supplier asignado.
Evitar acceso cruzado.
Exponer supplier_id de forma centralizada.
```

Regla:

```txt
Nunca confiar en supplier_id recibido desde request.
Siempre usar supplier_id del usuario autenticado.
```

---

## 8. Autenticación

### 8.1 Flujo

```txt
Usuario ingresa email/password
↓
Laravel valida credenciales
↓
Verifica usuario activo
↓
Verifica rol
↓
Verifica supplier activo si aplica
↓
Verifica 2FA si aplica
↓
Redirige a panel correspondiente
```

### 8.2 Paneles

```txt
/admin
/supplier
```

### 8.3 Roles

```txt
super_admin
admin
auditor
supplier_admin
supplier_user
```

### 8.4 Reglas

```txt
super_admin/admin/auditor → supplier_id nullable
supplier_admin/supplier_user → supplier_id obligatorio
```

---

## 9. Límite configurable de usuarios por supplier

### 9.1 Variable global

```env
SUPPLIER_MAX_USERS=3
```

### 9.2 Override por supplier

Tabla `suppliers`:

```txt
max_users nullable
```

### 9.3 Regla

```txt
max_users efectivo =
supplier.max_users si existe
si no, SUPPLIER_MAX_USERS
```

### 9.4 Estados que cuentan para el límite

```txt
active
pending_activation
pending_2fa_setup
```

No contar:

```txt
disabled
blocked
deleted
```

---

## 10. 2FA

### 10.1 Estados de usuario

```txt
pending_activation
pending_2fa_setup
active
blocked
disabled
```

### 10.2 Configuración

```env
ADMIN_2FA_REQUIRED=true
SUPPLIER_ADMIN_2FA_REQUIRED=true
SUPPLIER_USER_2FA_REQUIRED=false
```

### 10.3 Flujo de creación

```txt
Admin crea usuario
↓
Sistema valida límite de usuarios
↓
Usuario queda pending_activation
↓
Sistema envía email de activación
↓
Usuario define contraseña
↓
Usuario configura 2FA si aplica
↓
Usuario queda active
```

---

## 11. Identificadores

Usar estrategia híbrida:

```txt
id BIGINT interno
uuid UUID público
```

Regla:

```txt
FK internas usan BIGINT.
APIs, URLs, eventos y auditoría usan UUID.
```

Ejemplo:

```txt
suppliers.id
suppliers.uuid
```

---

## 12. Módulo Pricing

### 12.1 Objetivo

Permitir carga, validación, publicación e historial de tarifas por supplier.

### 12.2 Entidades

```txt
RatePlan
Rate
RateValidity
RateImport
RateImportRow
```

### 12.3 Reglas

```txt
Precio mayor a 0.
Moneda válida.
Fecha inicial obligatoria.
Fecha final obligatoria.
Fecha final >= fecha inicial.
No permitir vigencias cruzadas.
No permitir supplier_id externo.
No sobrescribir historial.
Publicar nueva versión.
```

---

## 13. Carga de precios por Excel

### 13.1 Flujo

```txt
Supplier descarga plantilla
↓
Supplier sube Excel
↓
Sistema guarda archivo original
↓
Sistema crea rate_import
↓
Sistema procesa en queue
↓
Sistema guarda filas en rate_import_rows
↓
Sistema valida fila por fila
↓
Sistema muestra resumen
↓
Supplier corrige o confirma
↓
Sistema publica tarifas válidas
```

### 13.2 Columnas recomendadas

```txt
office_code
vehicle_class
acriss_code
rate_plan_code
currency
base_price
valid_from
valid_to
min_days
max_days
status
```

### 13.3 Tablas

#### rate_imports

```txt
id
uuid
supplier_id
original_filename
stored_path
status
total_rows
valid_rows
invalid_rows
uploaded_by
created_at
updated_at
```

#### rate_import_rows

```txt
id
rate_import_id
row_number
raw_data jsonb
normalized_data jsonb
errors jsonb
status
created_at
updated_at
```

#### rates

```txt
id
uuid
supplier_id
office_code
vehicle_class
acriss_code
rate_plan_code
currency
base_price
valid_from
valid_to
min_days
max_days
status
version
created_by
created_at
updated_at
```

### 13.4 Estados

```txt
uploaded
processing
validated
failed
approved
published
```

---

## 14. Módulo Promotions

### 14.1 Objetivo

Permitir al supplier configurar promociones, descuentos y ofertas.

### 14.2 Tipos

```txt
percentage
fixed_amount
special_price
free_days
seasonal
bundle
```

### 14.3 Reglas

```txt
Toda promoción debe tener vigencia.
No puede tener valor negativo.
No puede estar activa si el supplier está inactivo.
No puede aplicar fuera de su fecha.
Debe auditarse todo cambio.
```

---

## 15. Módulo Booking

### 15.1 Objetivo

Permitir al supplier operar reservas manualmente.

### 15.2 Funciones

```txt
Ver cola de reservas.
Ver detalle.
Confirmar reserva.
Rechazar reserva.
Ver historial de acciones.
Medir SLA.
```

### 15.3 Estados

```txt
pending
confirmed
rejected
cancelled
completed
expired
```

---

## 16. Módulo Reporting

### 16.1 Objetivo

Permitir consultas, reportes y exportaciones a Excel.

### 16.2 Reportes iniciales

```txt
Reservas por fecha.
Reservas confirmadas/rechazadas.
Tarifas activas.
Tarifas vencidas.
Tarifas por vencer.
Promociones activas.
Promociones vencidas.
Errores de importación.
Inventario por oficina.
Disponibilidad por fecha.
Actividad de usuarios.
```

### 16.3 Exportaciones

No generar Excel grande dentro del request.

Flujo:

```txt
Usuario solicita exportación
↓
Sistema crea report_export
↓
Job procesa en background
↓
Archivo se guarda en storage
↓
Usuario recibe notificación
↓
Usuario descarga desde historial
```

### 16.4 Tabla report_exports

```txt
id
uuid
supplier_id nullable
user_id
report_type
filters jsonb
format
status
file_path
rows_count
error_message
created_at
completed_at
expires_at
```

---

## 17. Prevención N+1

### 17.1 Reglas

```txt
Prohibir lazy loading en desarrollo.
Usar eager loading explícito.
Usar withCount para dashboards.
Usar paginación siempre.
No usar Model::all() en producción.
Revisar query count por pantalla.
```

### 17.2 Índices mínimos

```txt
(supplier_id, status)
(supplier_id, created_at)
(supplier_id, valid_from, valid_to)
(supplier_id, reservation_code)
```

---

## 18. Manejo profesional de errores

### 18.1 Objetivo

El usuario nunca debe ver un error 500 crudo.

### 18.2 Mensaje genérico

```txt
Estamos teniendo una intermitencia temporal.
Tu operación quedó registrada de forma segura y será reintentada automáticamente.
Puedes volver a intentar en unos minutos.
Código de seguimiento: {incident_id}
```

### 18.3 Clasificación

```txt
BusinessException → no crítico
ValidationException → no crítico
AuthorizationException → no crítico
DatabaseConnectionException → crítico
QueryException → crítico si afecta operación
ExternalServiceException → degradado
Unhandled Throwable → crítico
```

---

## 19. Fallback SQLite y Circuit Breaker PostgreSQL

### 19.1 Objetivo

Si PostgreSQL falla, proteger la operación web con un circuit breaker local.

Solo se guardan incidentes locales para requests mutables:

```txt
POST
PUT
PATCH
DELETE
```

Las consultas no se guardan en SQLite porque dependen de disponibilidad y no representan una operación de negocio pendiente de reintento.

### 19.2 Configuración

```env
APP_ERROR_SQLITE_PATH=storage/app/fallback/incidents.sqlite
APP_DB_CHECK_MAX_ATTEMPTS=3
APP_DB_CHECK_RETRY_SLEEP_MS=100
APP_DB_CIRCUIT_BREAKER_ENABLED=true
APP_DB_CIRCUIT_BREAKER_FAILURE_THRESHOLD=3
APP_DB_CIRCUIT_BREAKER_OPEN_SECONDS=30
APP_DB_CIRCUIT_BREAKER_STATE_PATH=storage/app/fallback/db-circuit-breaker.json
```

### 19.3 Tabla local_incidents

```txt
id
incident_id
correlation_id
supplier_id
user_id
module
action
severity
safe_message
technical_message
payload_json
status
attempts
created_at
synced_at
```

### 19.4 Flujo de request

```txt
Request web
↓
Circuit breaker abierto?
↓
Si abierto: responder 503 sin probar PostgreSQL
↓
Si cerrado: probar PostgreSQL con 3 intentos
↓
Si PostgreSQL responde: continuar request normal
↓
Si PostgreSQL falla: registrar fallo y abrir circuito al superar umbral
↓
Si la request es escritura: guardar incidente en SQLite
↓
Responder 503 con incident_id
```

### 19.5 Recuperación

Probe automático:

```txt
php artisan system:database-circuit-probe
```

Frecuencia:

```txt
cada 1 minuto
```

Reset manual:

```txt
php artisan system:database-circuit-reset
```

Sincronización de `local_incidents` hacia `system_incidents`:

```txt
Pendiente: php artisan incidents:sync
```

---

## 20. Alertas por email

### 20.1 Cuándo enviar

```txt
Error 500 no controlado.
Error de conexión a PostgreSQL.
Fallo crítico de queue.
Fallo de sincronización con quote-engine.
Circuit breaker abierto.
```

### 20.2 Regla

El email es best-effort.

```txt
Si se manda, correcto.
Si falla, se registra en SQLite.
Nunca debe bloquear la respuesta al usuario.
```

---

## 21. Circuit Breaker

### 21.1 Aplicar a

```txt
supplier-service
quote-engine
email-service
storage externo
```

### 21.2 Estados

```txt
closed
open
half_open
```

### 21.3 Regla base

```txt
3 fallos consecutivos → open
esperar 60 segundos
probar half_open
si responde → closed
si falla → open
```

### 21.4 Regla para PostgreSQL primario

```txt
3 intentos por validación
100 ms entre intentos
3 fallos de validación → open
probe automático cada 1 minuto
si PostgreSQL responde → closed
si falla → mantener open
```

---

## 22. Logging

### 22.1 Canales

```txt
laravel.log
PostgreSQL system_logs
SQLite fallback
Email crítico
Sentry opcional
```

### 22.2 Contexto mínimo

```txt
incident_id
correlation_id
request_id
supplier_id
user_id
module
action
severity
exception_class
safe_message
technical_message
```

---

## 23. Outbox Pattern

### 23.1 Objetivo

Evitar pérdida de eventos críticos.

### 23.2 Tabla outbox_events

```txt
id
uuid
aggregate_type
aggregate_id
event_type
payload jsonb
status
attempts
available_at
processed_at
created_at
updated_at
```

### 23.3 Eventos iniciales

```txt
BookingConfirmed
BookingRejected
RateImportUploaded
RateImportValidated
RatesPublished
PromotionActivated
AvailabilityChanged
ReportExportRequested
ReportExportCompleted
```

---

## 24. Queues

### 24.1 Jobs iniciales

```txt
ProcessRateImportJob
ValidateRateImportRowsJob
PublishValidatedRatesJob
SyncQuoteEngineJob
SendCriticalIncidentEmailJob
SyncIncidentsToPostgresJob
GenerateReportExportJob
ExpireReportExportsJob
```

### 24.2 Regla

Todo proceso pesado va a queue.

```txt
Excel
Reportes
Sincronización
Emails
Notificaciones
Publicación masiva
```

---

## 25. Octane

### 25.1 Decisión

Octane no se activa en fase inicial.

Primero construir:

```txt
Arquitectura modular
Auth
Multi-supplier
Pricing
Excel
Reporting
Error handling
Queues
Observabilidad
```

Después activar Octane.

### 25.2 Regla

El sistema debe ser stateless por request.

Prohibido:

```txt
Estado global mutable.
Singletons con datos de usuario.
Variables estáticas con supplier_id.
Cachear usuario actual en memoria persistente.
```

---

## 26. Observabilidad

### 26.1 Herramientas

```txt
Laravel Pulse
Laravel Horizon
Logs estructurados
Health checks
Sentry opcional
```

### 26.2 Health checks

```txt
/health
/health/db
/health/queue
/health/storage
/health/integrations
```

### 26.3 Estados

```txt
healthy
degraded
down
```

---

## 27. Seguridad

### 27.1 Reglas

```txt
APP_DEBUG=false en producción.
No exponer stack trace.
No confiar en supplier_id del request.
Policies obligatorias.
2FA configurable.
Sesiones seguras.
Auditoría completa.
Rate limiting en login.
Bloqueo temporal por intentos fallidos.
```

### 27.2 Configuración de sesión

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

---

## 28. Gobernanza

### 28.1 Herramientas

```txt
Pest
PHPStan / Larastan
Laravel Pint
Rector
Architecture tests
CI pipeline
Pre-commit hooks
ADR
```

### 28.2 Reglas de calidad

```txt
Nada de lógica de negocio en Filament Resources.
Nada de consultas sin supplier_id en panel supplier.
Nada de Model::all() en pantallas productivas.
Nada de errores técnicos al usuario.
Nada de jobs sin retries controlados.
Nada de exportaciones grandes en request.
Nada de imports directos a tablas finales.
```

---

## 29. Testing

### 29.1 Pruebas requeridas

```txt
Unit tests para reglas de dominio.
Feature tests para use cases.
Policy tests para aislamiento multi-supplier.
Integration tests para imports Excel.
Failure tests para PostgreSQL caído.
Failure tests para email caído.
Queue tests.
Report export tests.
N+1 tests.
Authorization tests.
2FA tests.
```

### 29.2 Casos críticos

```txt
Supplier A no puede ver datos de Supplier B.
Supplier no puede crear más usuarios que su límite.
Excel inválido no publica tarifas.
Vigencias cruzadas son rechazadas.
Error DB guarda incidente en SQLite.
Error 500 dispara email crítico.
Reporte grande se procesa por queue.
Usuario ve mensaje maquillado, no stack trace.
```

---

## 30. Criterios de aceptación globales

El sistema se considera aceptado cuando:

```txt
Login funciona con roles y 2FA.
Admin y supplier entran a paneles separados.
Supplier solo ve su información.
Límite de usuarios es configurable.
Carga Excel usa staging.
Validación de Excel muestra errores por fila.
Tarifas se publican versionadas.
Reportes exportan a Excel por queue.
Errores 500 no llegan crudos al usuario.
Errores críticos mandan email.
Falla de PostgreSQL guarda incidente en SQLite.
Al volver PostgreSQL, incidentes se sincronizan.
No hay N+1 detectado en pantallas principales.
Todos los módulos pasan tests.
```

---

## 31. Roadmap recomendado

### Fase 1

```txt
Base Laravel
Filament Admin/Supplier panels
PostgreSQL
Auth
Roles
Policies
SupplierContext
```

### Fase 2

```txt
Suppliers
Usuarios
Límite configurable
2FA
Auditoría básica
```

### Fase 3

```txt
Booking queue
Booking detail
Confirm/reject
SLA básico
```

### Fase 4

```txt
Pricing manual
Rate plans
Vigencias
Versionamiento
```

### Fase 5

```txt
Carga Excel
Staging
Validación fila por fila
Publicación controlada
```

### Fase 6

```txt
Promotions
Availability
Fleet
```

### Fase 7

```txt
Reporting
Consultas
Exportación Excel
Historial de exportaciones
```

### Fase 8

```txt
Error handling profesional
SQLite fallback
Email alerts
Incident sync
Health checks
```

### Fase 9

```txt
Outbox pattern
Circuit breaker
Integración supplier-service
Integración quote-engine
```

### Fase 10

```txt
Performance
Pulse
Horizon
Octane
Optimización avanzada
```

---

## 32. Instrucción para Codex

Implementar el sistema respetando estrictamente:

```txt
Clean Architecture
SOLID
DRY
KISS
Single Responsibility Principle
Multi-supplier isolation
No business logic in Filament
No direct Excel-to-production writes
No raw 500 to users
Fallback SQLite for critical incidents
Queued exports/imports
PostgreSQL as primary database
UUID public identifiers + BIGINT internal PK
```

Antes de escribir código de un módulo, crear:

```txt
Use Case
DTO
Policy
Repository Contract
Repository Implementation
Domain Rules
Tests
Filament Resource
```

Ningún módulo debe aprobarse sin pruebas de aislamiento por supplier.
