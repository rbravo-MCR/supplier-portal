# 11_CODING_STANDARDS.md

# Supplier Portal — Coding Standards

## 1. Objetivo

Definir reglas obligatorias para que el código sea mantenible, modular, testeable y consistente.

---

## 2. Principios obligatorios

```txt
Clean Code
SOLID
DRY
KISS
Single Responsibility Principle
Clean Architecture
Modular Monolith
TDD
```

---

## 3. Reglas generales

Prohibido:

```txt
God Classes
Fat Controllers
Fat Filament Resources
Fat Livewire Components
Business Logic en UI
Queries complejas en Presentation
Model::all() en producción
```

Obligatorio:

```txt
UseCases
DTOs
Policies
Repositories
Domain Rules
Tests
Logs estructurados
```

---

## 4. Naming

### Use Cases

```txt
CreateSupplierUser
UploadRateExcel
PublishValidatedRates
ConfirmBooking
RejectBooking
GenerateReportExport
```

### DTOs

```txt
CreateSupplierUserData
UploadRateExcelData
PublishRatesData
ConfirmBookingData
```

### Rules

```txt
SupplierUserLimitRule
RateValidityMustNotOverlap
PriceMustBeGreaterThanZero
UserMustBelongToSupplier
```

### Events

```txt
BookingConfirmed
RatesPublished
PromotionActivated
IncidentCreated
```

---

## 5. Filament

Filament solo debe contener:

```txt
Forms
Tables
Actions visuales
Notifications
```

Filament no debe contener:

```txt
Reglas de negocio
Validaciones complejas
Integraciones externas
Procesamiento Excel
Exportaciones grandes
```

Flujo obligatorio:

```txt
Filament Action
↓
UseCase
↓
Domain Rule
↓
Repository
```

---

## 6. Eloquent

Permitido solo en Infrastructure.

Domain no debe depender de Eloquent.

Prohibido en Domain:

```txt
Illuminate\Database\Eloquent\Model
DB::table()
Query Builder
```

---

## 7. DTOs

Toda entrada a UseCase debe usar DTO.

Ejemplo:

```txt
UploadRateExcelData
ConfirmBookingData
CreatePromotionData
```

No pasar arrays crudos entre capas.

---

## 8. Excepciones

Usar excepciones semánticas:

```txt
BusinessRuleViolation
SupplierAccessDenied
DatabaseUnavailable
ExternalServiceUnavailable
RateOverlapDetected
ImportValidationFailed
```

No lanzar `Exception` genérica desde dominio.

---

## 9. Logs

Todo log crítico debe incluir:

```txt
incident_id
correlation_id
supplier_id
user_id
module
action
```

---

## 10. Queries

Obligatorio:

```txt
paginate()
with()
withCount()
chunkById()
cursor()
```

Prohibido:

```txt
Model::all() en pantallas
Lazy loading accidental
Loops con relaciones no cargadas
```

---

## 11. Seguridad

Nunca confiar en:

```txt
supplier_id del request
role del request
status del request
```

Siempre resolver desde:

```txt
auth()
SupplierContext
Policies
```

---

## 12. Testing

Todo módulo debe incluir:

```txt
Unit Tests
Feature Tests
Policy Tests
Failure Tests
Multi-Supplier Tests
```

---

## 13. Formato

Usar:

```txt
Laravel Pint
PHPStan / Larastan
Rector
```

Código no formateado no se acepta.

---

## 14. Comentarios

Comentar solo:

```txt
Reglas de negocio complejas
Decisiones no obvias
Workarounds temporales
```

No comentar código obvio.

---

## 15. Regla final

Si una clase hace más de una cosa, debe separarse.
