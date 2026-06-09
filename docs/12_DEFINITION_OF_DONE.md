# 12_DEFINITION_OF_DONE.md

# Supplier Portal — Definition of Done

## 1. Objetivo

Definir cuándo una funcionalidad puede considerarse terminada.

Una tarea no está terminada porque “funciona”. Está terminada cuando cumple arquitectura, seguridad, pruebas, observabilidad y mantenimiento.

---

## 2. Requisitos generales

Una funcionalidad solo se considera terminada si:

```txt
Pasa tests
Respeta arquitectura
Tiene autorización
Tiene validaciones
Tiene manejo de errores
Tiene auditoría si aplica
No rompe multi-supplier
No genera N+1
No expone errores técnicos
```

---

## 3. Arquitectura

Debe cumplir:

```txt
Clean Architecture
Modular Monolith
SOLID
DRY
KISS
SRP
```

No se acepta:

```txt
Lógica de negocio en Filament
Lógica de negocio en Controller
Queries complejas en UI
Dependencias de Infrastructure en Domain
```

---

## 4. Seguridad

Debe cumplir:

```txt
Policy implementada
Permisos revisados
SupplierContext aplicado
Sin supplier_id confiado desde request
Sin stack trace visible
CSRF protegido
Rate limiting si aplica
```

---

## 5. Multi-supplier

Todo módulo con datos de supplier debe probar:

```txt
Supplier A no ve Supplier B
Supplier A no modifica Supplier B
Reportes no fugan datos
Exports no fugan datos
```

---

## 6. Testing mínimo

Cada módulo requiere:

```txt
Unit Test
Feature Test
Policy Test
Failure Test
Multi-Supplier Test
```

Si aplica:

```txt
Excel Import Test
Queue Test
Store & Forward Test
N+1 Test
Report Export Test
```

---

## 7. Performance

No se acepta si:

```txt
Tiene N+1
Usa Model::all() en pantalla productiva
No pagina listados
Genera Excel grande en request
No tiene índices principales
```

---

## 8. Persistencia

Debe cumplir:

```txt
PostgreSQL como fuente de verdad
SQLite solo fallback temporal
Store & Forward con operation_uuid
No borrar SQLite antes de confirmar PostgreSQL
```

---

## 9. Errores

Debe cumplir:

```txt
No raw 500
Mensaje amigable
incident_id generado
Log técnico registrado
Email crítico si aplica
```

---

## 10. Queues

Debe usar queue para:

```txt
Excel
Reportes
Emails
Sync
Outbox
Store & Forward
```

---

## 11. Observabilidad

Debe registrar:

```txt
logs relevantes
audit log si aplica
eventos críticos
métricas si aplica
```

---

## 12. Documentación

Debe actualizar:

```txt
SDD si cambia arquitectura
BDD si cambia comportamiento
TDD si cambia pruebas
ADR si cambia decisión técnica
DATA_MODEL si cambia tabla
EVENT_CATALOG si cambia evento
PERMISSION_MATRIX si cambia permisos
```

---

## 13. Checklist final

Antes de cerrar una tarea:

```txt
[ ] Tests pasan
[ ] PHPStan pasa
[ ] Pint pasa
[ ] No N+1
[ ] Policy creada
[ ] Supplier isolation probado
[ ] Errores controlados
[ ] Logs correctos
[ ] Auditoría agregada
[ ] Documentos actualizados
```

---

## 14. Regla final

Si no está probado, no está terminado.
