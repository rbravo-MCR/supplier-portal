# ADR_INDEX.md

## Supplier Portal — Architectural Decision Records

## ADR-001: PostgreSQL como base principal

**Decisión:** PostgreSQL será la base oficial del sistema.

**Motivo:**

- JSONB.

- Índices avanzados.

- Mejor concurrencia.

- Consultas analíticas.

- Mejor soporte para reportes.

**Prohibido:**

- MySQL.

- MariaDB.

- SQLite como base principal.

---

## ADR-002: SQLite como Store & Forward temporal

**Decisión:** SQLite solo se usará cuando PostgreSQL no esté disponible.

**Regla:**

```txt
PostgreSQL down → SQLite temporal → PostgreSQL up → sincronizar → borrar SQLite
```

Nunca borrar SQLite antes de confirmar escritura en PostgreSQL.

---

## ADR-003: Modular Monolith

**Decisión:** El sistema será monolito modular.

**Módulos:**

```txt
Identity
Supplier
Booking
Pricing
Promotion
Fleet
Availability
Import
Reporting
Audit
System
Integration
```

---

## ADR-004: Filament solo Presentation Layer

**Decisión:** Filament no contiene lógica de negocio.

**Flujo obligatorio:**

```txt
Filament Action → UseCase → Domain Rule → Repository → Persistence
```

---

## ADR-005: BIGINT interno + UUID público

**Decisión:**

```txt
id BIGINT para PK/FK internas
uuid para URLs, APIs, eventos y auditoría
```

---

## ADR-006: Queues para procesos pesados

**Decisión:** Todo proceso pesado va a queue.

Aplica a:

```txt
Excel
Reportes
Emails
Sincronización
Outbox
Store & Forward
```

---

## ADR-007: No raw 500 al usuario

**Decisión:** El usuario nunca verá errores técnicos.

Debe ver:

```txt
Mensaje amigable
Código de seguimiento
```

El equipo técnico verá:

```txt
logs
incident_id
stack trace interno
```

---

## ADR-008: Octane no se activa al inicio

**Decisión:** Primero arquitectura, tests y observabilidad. Octane se habilita después.

**Motivo:** Evitar bugs por estado persistente mal manejado.

---

## ADR-009: Prevención N+1

**Decisión:** Todo listado relacional debe usar eager loading, agregados o paginación.

**Obligatorio:**

```txt
with()
withCount()
paginate()
chunkById()
preventLazyLoading()
```

**Prohibido:**

```txt
Model::all() en pantallas productivas
Lazy loading accidental
Relaciones dentro de loops
```

---

## ADR-010: Multi-supplier isolation

**Decisión:** Todo dato operativo pertenece a un supplier.

**Regla:**

```txt
Nunca confiar en supplier_id del request.
Siempre usar supplier_id del usuario autenticado.
```

---

## ADR-011: Excel usa staging

**Decisión:** Excel nunca escribe directo a tablas finales.

Flujo:

```txt
Excel → rate_imports → rate_import_rows → validación → publicación
```

---

## ADR-012: Reportes por queue

**Decisión:** Exportaciones grandes no se generan en request.

Flujo:

```txt
Solicitar reporte → report_exports → Job → archivo → descarga
```

---

## ADR-013: 2FA configurable

**Decisión:** 2FA será obligatorio/configurable por rol.

```env
ADMIN_2FA_REQUIRED=true
SUPPLIER_ADMIN_2FA_REQUIRED=true
SUPPLIER_USER_2FA_REQUIRED=false
```

---

## ADR-014: Circuit Breaker para integraciones

**Decisión:** Integraciones externas deben protegerse con circuit breaker.

Aplica a:

```txt
supplier-service
quote-engine
email-service
storage externo
```

---

## ADR-015: TDD obligatorio

**Decisión:** Ningún módulo se implementa sin pruebas.

Mínimos:

```txt
Unit Test
Feature Test
Policy Test
Failure Test
Multi-supplier Test
```
