# TDD — Supplier Portal

## 1. Objetivo

Definir la estrategia de pruebas para construir el Supplier Portal con enfoque Test Driven Development.

El sistema debe probar primero:

- Reglas de dominio.

- Aislamiento multi-supplier.

- Autenticación y autorización.

- 2FA.

- Límite configurable de usuarios.

- Carga Excel.

- Vigencias de precios.

- Promociones.

- Reservas.

- Reportes.

- Exportación Excel.

- Manejo de errores.

- SQLite fallback.

- Sincronización a PostgreSQL.

- Circuit breaker.

- Prevención N+1.

---

## 2. Stack de testing

```txt
Pest
PHPUnit
Laravel Test Helpers
RefreshDatabase
Database Transactions
Laravel Fake Queue
Laravel Fake Mail
Laravel Fake Notification
Laravel Fake Storage
Laravel Excel testing
PHPStan / Larastan
Laravel Pint
Rector
Architecture Tests
```

---

## 3. Regla TDD general

Antes de implementar cada caso de uso, crear:

```txt
1. Test de dominio
2. Test de aplicación / use case
3. Test de autorización
4. Test de integración con DB
5. Test de UI/Filament cuando aplique
6. Test de error/falla
```

---

## 4. Estructura de pruebas

```txt
tests/
├── Unit/
│   ├── Domain/
│   └── Rules/
│
├── Feature/
│   ├── Auth/
│   ├── Supplier/
│   ├── Booking/
│   ├── Pricing/
│   ├── Promotion/
│   ├── Reporting/
│   ├── ErrorHandling/
│   └── Security/
│
├── Integration/
│   ├── Excel/
│   ├── PostgreSQL/
│   ├── SQLiteFallback/
│   ├── Queue/
│   └── ExternalServices/
│
└── Architecture/
```

---

## 5. Auth Tests

### test_supplier_user_can_login

Debe validar:

```txt
Usuario supplier activo puede iniciar sesión.
Es redirigido a /supplier.
Se crea sesión.
```

### test_disabled_user_cannot_login

Debe validar:

```txt
Usuario disabled no entra.
No se crea sesión.
Mensaje amigable.
```

### test_supplier_user_without_supplier_id_cannot_login

Debe validar:

```txt
Usuario con rol supplier sin supplier_id no entra.
Se registra incidente o warning.
```

### test_admin_goes_to_admin_panel

Debe validar:

```txt
admin entra a /admin.
supplier entra a /supplier.
```

---

## 6. 2FA Tests

### test_admin_requires_2fa

```txt
ADMIN_2FA_REQUIRED=true
admin inicia sesión
sistema solicita 2FA
```

### test_supplier_admin_requires_2fa

```txt
SUPPLIER_ADMIN_2FA_REQUIRED=true
supplier_admin inicia sesión
sistema solicita 2FA
```

### test_supplier_user_2fa_can_be_optional

```txt
SUPPLIER_USER_2FA_REQUIRED=false
supplier_user entra sin 2FA
```

### test_user_cannot_be_active_until_2fa_is_confirmed

```txt
usuario creado
estado pending_2fa_setup
no puede entrar al panel
confirma 2FA
estado active
```

---

## 7. Supplier User Limit Tests

### test_supplier_can_create_user_under_limit

```txt
SUPPLIER_MAX_USERS=3
supplier tiene 2 usuarios
crear tercero
resultado exitoso
```

### test_supplier_cannot_exceed_user_limit

```txt
SUPPLIER_MAX_USERS=3
supplier tiene 3 usuarios activos
crear cuarto usuario
rechazado
```

### test_supplier_override_max_users

```txt
SUPPLIER_MAX_USERS=3
supplier.max_users=5
permite hasta 5 usuarios
```

### test_pending_activation_counts_against_limit

```txt
usuarios active + pending_activation + pending_2fa_setup cuentan para límite
```

---

## 8. Multi-Supplier Security Tests

### test_supplier_sees_only_own_bookings

```txt
supplier A tiene reservas
supplier B tiene reservas
usuario A entra
solo ve reservas A
```

### test_supplier_cannot_access_other_supplier_booking

```txt
usuario A intenta abrir booking B
respuesta 403
evento auditado
```

### test_supplier_id_from_request_is_ignored

```txt
usuario A envía supplier_id de B
sistema usa supplier_id de sesión
no modifica datos de B
```

### test_admin_can_filter_by_supplier

```txt
admin consulta bookings
puede filtrar por supplier
```

---

## 9. Pricing Domain Tests

### test_price_must_be_greater_than_zero

```txt
base_price <= 0
regla falla
```

### test_validity_dates_are_required

```txt
valid_from null
valid_to null
regla falla
```

### test_valid_to_must_be_after_or_equal_valid_from

```txt
valid_to menor que valid_from
regla falla
```

### test_rate_validity_must_not_overlap

```txt
existe tarifa activa del 01 al 15
nueva tarifa del 10 al 20
regla falla
```

### test_rate_history_is_not_overwritten

```txt
publicar nueva tarifa
mantiene versión anterior
crea nueva versión
```

---

## 10. Excel Import Tests

### test_supplier_can_upload_valid_rate_excel

```txt
supplier sube Excel válido
crea rate_import
crea rate_import_rows
estado validated
```

### test_invalid_excel_rows_are_stored

```txt
Excel tiene filas inválidas
se guardan raw_data
se guardan errors
no publica rates
```

### test_excel_cannot_publish_with_critical_errors

```txt
rate_import tiene errores críticos
publicar
rechazado
```

### test_valid_excel_publish_creates_versioned_rates

```txt
rate_import validado
publicar
crea rates versionados
estado published
```

### test_excel_supplier_code_cannot_override_context

```txt
Excel trae supplier_code de otro supplier
sistema usa supplier autenticado
rechaza o ignora supplier_code externo
```

---

## 11. Booking Tests

### test_supplier_can_view_booking_queue

```txt
usuario supplier entra a /supplier/bookings
ve reservas pendientes propias
```

### test_supplier_can_confirm_booking

```txt
booking pending
usuario confirma
estado confirmed
evento BookingConfirmed emitido
audit log creado
```

### test_supplier_can_reject_booking_with_reason

```txt
booking pending
usuario rechaza con motivo
estado rejected
evento BookingRejected emitido
```

### test_reject_booking_requires_reason

```txt
rechazo sin motivo
validación falla
```

---

## 12. Promotion Tests

### test_promotion_requires_validity_dates

```txt
promoción sin valid_from/valid_to
rechazada
```

### test_promotion_value_cannot_be_negative

```txt
value negativo
rechazada
```

### test_inactive_supplier_cannot_activate_promotion

```txt
supplier inactive
crear promoción active
rechazado
```

### test_expired_promotion_is_marked_expired

```txt
valid_to en pasado
job corre
estado expired
```

---

## 13. Reporting Tests

### test_supplier_report_only_contains_own_data

```txt
supplier A y B tienen reservas
usuario A exporta reporte
Excel solo contiene datos A
```

### test_report_export_is_queued

```txt
usuario solicita exportación
se crea report_export pending
se despacha GenerateReportExportJob
```

### test_large_report_is_not_generated_in_request

```txt
reporte grande
request responde rápido
job procesa en background
```

### test_report_download_is_audited

```txt
usuario descarga Excel
audit log registra usuario, filtros y fecha
```

---

## 14. Error Handling Tests

### test_unhandled_exception_returns_safe_message

```txt
ocurre Throwable
usuario recibe mensaje amigable
no ve stack trace
se genera incident_id
```

### test_500_error_sends_critical_email

```txt
ocurre error crítico
Mail::fake detecta envío
email contiene incident_id
```

### test_database_error_is_saved_to_sqlite

```txt
PostgreSQL no disponible
ocurre error crítico
incidente se guarda en SQLite
usuario recibe mensaje seguro
```

### test_email_failure_does_not_break_response

```txt
email service falla
sistema no muestra error crudo
incidente queda registrado
```

---

## 15. SQLite Fallback Tests

### test_incident_is_stored_locally_when_postgres_is_down

```txt
PostgreSQL down
guardar incidente
SQLite contiene registro
```

### test_incidents_sync_when_postgres_returns

```txt
SQLite tiene pendientes
PostgreSQL vuelve
incidents:sync corre
system_incidents contiene registros
SQLite marca synced_at
```

### test_duplicate_incidents_are_not_synced_twice

```txt
mismo incident_id existe
sync corre dos veces
solo un registro en PostgreSQL
```

---

## 16. Circuit Breaker Tests

### test_circuit_opens_after_three_failures

```txt
quote-engine falla 3 veces
circuit status open
no intenta nueva llamada
```

### test_circuit_half_open_after_timeout

```txt
circuit open
pasa tiempo configurado
estado half_open
```

### test_circuit_closes_after_successful_probe

```txt
half_open
servicio responde
estado closed
```

---

## 17. Outbox Tests

### test_event_is_stored_in_outbox

```txt
BookingConfirmed
crea outbox_event
status pending
```

### test_outbox_event_is_processed

```txt
outbox_event pending
job corre
status processed
```

### test_failed_outbox_event_retries

```txt
fallo externo
attempts aumenta
status pending o failed según límite
```

---

## 18. N+1 Tests

### test_booking_queue_does_not_lazy_load_relationships

```txt
preventLazyLoading activo
pantalla bookings carga
no LazyLoadingViolationException
```

### test_dashboard_uses_aggregates

```txt
dashboard carga métricas
usa withCount/agregados
no carga relaciones completas
```

### test_booking_queue_does_not_trigger_lazy_loading()

```
test_rate_table_uses_eager_loading()

test_dashboard_uses_aggregated_counts()

test_report_export_uses_chunking()
```

### Un módulo NO está terminado si:

- Tiene N+1 detectado.
- Usa Model::all() en producción.
- No tiene índices adecuados.
- No tiene eager loading en listados.

---

## 19. Architecture Tests

### test_filament_resources_do_not_contain_business_logic

Validar que Filament no tenga:

```txt
cálculos de negocio
validaciones complejas
queries críticas duplicadas
integración externa directa
```

### test_use_cases_do_not_depend_on_filament

```txt
Application no depende de Presentation
```

### test_domain_does_not_depend_on_eloquent

```txt
Domain no importa Illuminate\Database\Eloquent
```

### test_infrastructure_can_depend_on_domain

```txt
Infrastructure puede implementar contratos de Domain/Application
```

---

## 20. CI Requirements

Pipeline mínimo:

```txt
composer validate
composer install
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan migrate --env=testing
architecture tests
```

---

## 21. Definition of Done por módulo

Un módulo se considera terminado solo si:

```txt
Tests unitarios pasan.
Tests feature pasan.
Tests de autorización pasan.
Tests multi-supplier pasan.
No hay N+1 visible.
No hay lógica de negocio en Filament.
Errores están controlados.
Logs/auditoría están presentes.
PHPStan pasa.
Pint pasa.
```

---

## 22. Orden recomendado TDD

```txt
1. SupplierContext tests
2. Auth tests
3. User limit tests
4. Policies tests
5. Booking tests
6. Pricing rules tests
7. Excel import tests
8. Promotion tests
9. Reporting tests
10. Error handling tests
11. SQLite fallback tests
12. Circuit breaker tests
13. Outbox tests
14. Architecture tests
```

---

## Store & Forward Tests

### test_operation_is_saved_to_sqlite_when_postgres_is_down

Debe validar:

- PostgreSQL no disponible.
- Operación diferible ejecutada.
- Registro creado en SQLite.
- Usuario recibe mensaje seguro.
- No se lanza error 500.

### test_pending_operation_syncs_to_postgres_when_available

Debe validar:

- Existe operación pendiente en SQLite.
- PostgreSQL está disponible.
- Se inserta en PostgreSQL.
- Se elimina de SQLite.

### test_sqlite_operation_is_not_deleted_if_postgres_insert_fails

Debe validar:

- Existe operación pendiente.
- Falla inserción en PostgreSQL.
- Registro permanece en SQLite.
- attempts aumenta.

### test_operation_uuid_prevents_duplicates

Debe validar:

- Misma operación intenta sincronizarse dos veces.
- PostgreSQL solo guarda una vez.
- Segunda sincronización se ignora de forma segura.

---

## 23. Instrucción para Codex

Implementar cada funcionalidad escribiendo primero el test.

No implementar código productivo si no existe antes:

```txt
Test de comportamiento.
Test de autorización.
Test de error.
Test multi-supplier.
```

Prohibido aprobar módulos sin pruebas de aislamiento por supplier.
