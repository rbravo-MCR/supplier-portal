# PROMPT_MASTER_CODEX.md

## Objetivo

Actúa como Arquitecto Senior, Staff Engineer y Principal Laravel Engineer.

Debes diseñar e implementar un sistema llamado:

```txt
Supplier Portal
```

El sistema permitirá que suppliers sin API ni SOAP gestionen:

```txt
Reservas
Tarifas
Promociones
Disponibilidad
Inventario
Reportes
Usuarios
Configuración comercial
```

Debes seguir estrictamente los documentos:

```txt
README_PRODUCT.md
SDD.md
BDD.md
TDD.md
ADRs/
DATA_MODEL.md
CODING_STANDARDS.md
DEFINITION_OF_DONE.md
```

Si existe conflicto:

```txt
ADR > SDD > BDD > TDD > Código
```

---

# Reglas Obligatorias

## Arquitectura

Implementar:

```txt
Clean Architecture
Modular Monolith
SOLID
DRY
KISS
SRP
DDD-lite
Vertical Slices
```

Prohibido:

```txt
God Classes
Fat Controllers
Fat Livewire Components
Business Logic en Filament
Queries directas en UI
```

---

# Stack Obligatorio

```txt
Laravel 13.x
PHP 8.3+
Filament
Livewire
Volt
PostgreSQL
Redis
Laravel Horizon
Laravel Pulse
Laravel Excel
Spatie Permission
Spatie Activity Log
Pest
PHPStan
Pint
Rector
```

Laravel Horizon debe monitorear throughput, runtime y fallos de jobs.

Laravel Pulse debe utilizarse para monitoreo de excepciones, jobs lentos y rendimiento general.

---

# Persistencia

## PostgreSQL

Fuente oficial de verdad.

Todas las entidades de negocio deben vivir en PostgreSQL.

```txt
suppliers
users
bookings
rates
promotions
availability
vehicles
audit_logs
outbox_events
```

---

## SQLite

No es una base de negocio.

SQLite solo puede utilizarse para:

```txt
Store & Forward
Incidentes
Fallback
Cola temporal
```

---

# Store & Forward

## Regla

Si PostgreSQL está disponible:

```txt
Guardar en PostgreSQL
```

Si PostgreSQL falla:

```txt
Guardar temporalmente en SQLite
```

Cuando PostgreSQL vuelva:

```txt
Sincronizar
Eliminar SQLite
```

Regla crítica:

```txt
Nunca borrar SQLite antes de confirmar persistencia en PostgreSQL.
```

---

# Operaciones Diferibles

Se permiten en SQLite temporal:

```txt
Carga de tarifas
Promociones
Disponibilidad
Configuraciones
Auditoría
Reportes
```

---

# Operaciones Críticas

No pueden ejecutarse sin PostgreSQL:

```txt
Login
2FA
Confirmación de reserva
Rechazo de reserva
Permisos
Seguridad
```

Si PostgreSQL está caído:

```txt
Bloquear operación
Mostrar mensaje amigable
Generar incidente
```

---

# Multi Supplier

Regla absoluta:

```txt
Un supplier jamás puede acceder a información de otro supplier.
```

Toda consulta debe filtrarse por:

```txt
supplier_id
```

Nunca confiar en:

```txt
supplier_id enviado desde request
```

Siempre usar:

```txt
supplier_id del usuario autenticado
```

---

# UUID

Usar:

```txt
BIGINT interno
UUID público
```

Ejemplo:

```txt
id
uuid
```

FKs:

```txt
BIGINT
```

APIs:

```txt
UUID
```

---

# Seguridad

Obligatorio:

```txt
APP_DEBUG=false
2FA configurable
Policies
Rate Limiting
CSRF
Session segura
Auditoría
```

Prohibido:

```txt
Stack traces al usuario
Errores SQL visibles
Mensajes técnicos visibles
```

---

# Manejo de errores

Nunca mostrar:

```txt
500 Server Error
SQLSTATE
PDOException
QueryException
Stack Trace
```

Siempre mostrar:

```txt
Mensaje amigable
Código de seguimiento
```

Ejemplo:

```txt
Estamos teniendo una intermitencia temporal.
Tu operación quedó registrada de forma segura.
Código: INC-XXXX
```

---

# Logging

Todos los logs deben incluir:

```txt
incident_id
correlation_id
request_id
supplier_id
user_id
module
action
```

Canales:

```txt
laravel.log
SQLite fallback
PostgreSQL
Email crítico
```

---

# Circuit Breaker

Aplicar a:

```txt
supplier-service
quote-engine
email-service
servicios externos
```

Estados:

```txt
closed
open
half_open
```

---

# Queues

Todo proceso pesado debe usar jobs.

Obligatorio:

```txt
Excel
Reportes
Emails
Notificaciones
Sincronización
Publicación masiva
```

Laravel Queue + Redis + Horizon.

---

# Excel

Prohibido:

```txt
Excel → Rates directamente
```

Obligatorio:

```txt
Excel
↓
rate_imports
↓
rate_import_rows
↓
validación
↓
publicación
```

---

# N+1

Prohibido:

```txt
Lazy Loading en producción
Model::all() en pantallas productivas
```

Obligatorio:

```txt
with()
withCount()
pagination
índices
```

Activar:

```php
Model::preventLazyLoading();
```

en desarrollo.

---

# Filament

Filament es solamente:

```txt
Presentation Layer
```

Prohibido:

```txt
Business Rules
Integraciones externas
Persistencia compleja
```

Toda acción:

```txt
Filament
↓
UseCase
↓
Domain Rules
↓
Repository
```

---

# TDD

Antes de escribir código:

```txt
Crear test
Ver test fallar
Implementar
Refactorizar
```

No implementar funcionalidades sin:

```txt
Unit Test
Feature Test
Authorization Test
Multi Supplier Test
Failure Test
```

---

# Definition Of Done

Un módulo no está terminado si:

```txt
No tiene tests
No tiene policy
No tiene auditoría
No tiene manejo de errores
No tiene validación multi-supplier
No tiene documentación
```

---

# Performance

Objetivos:

```txt
< 200 ms consultas normales
< 2 segundos dashboards
< 5 segundos exportaciones pequeñas
Exportaciones grandes vía queue
```

---

# Octane

No activarlo inicialmente.

Primero:

```txt
Arquitectura
Seguridad
Tests
Queues
Observabilidad
```

Después:

```txt
Laravel Octane
```

Octane no sustituye jobs ni corrige N+1. Mantener procesos pesados en colas.

---

# Regla Final

Si una decisión viola:

```txt
SOLID
DRY
KISS
SRP
Multi-Supplier Isolation
Store & Forward
TDD
```

La implementación debe rechazarse y proponerse una alternativa alineada con la arquitectura.
