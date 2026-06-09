# BDD — Supplier Portal

## Feature: Autenticación de usuarios

### Scenario: Login exitoso de supplier

Given un usuario supplier activo  
And su supplier está activo  
And sus credenciales son correctas  
When intenta iniciar sesión  
Then el sistema debe autenticarlo  
And redirigirlo al panel `/supplier`  
And crear una sesión segura

### Scenario: Login de usuario inactivo

Given un usuario con estado `disabled`  
When intenta iniciar sesión  
Then el sistema debe rechazar el acceso  
And mostrar un mensaje claro  
And no revelar detalles técnicos

### Scenario: Usuario requiere 2FA

Given un usuario con 2FA obligatorio  
And sus credenciales son correctas  
When intenta iniciar sesión  
Then el sistema debe solicitar código 2FA  
And no permitir acceso hasta validar el código

---

## Feature: Aislamiento multi-supplier

### Scenario: Supplier solo ve sus datos

Given el supplier A tiene reservas  
And el supplier B tiene reservas  
When un usuario del supplier A entra al portal  
Then solo debe ver reservas del supplier A

### Scenario: Supplier intenta acceder a datos de otro supplier

Given un usuario del supplier A  
When intenta acceder a un recurso del supplier B  
Then el sistema debe bloquear el acceso  
And responder con 403 amigable  
And registrar el intento en auditoría

---

## Feature: Límite de usuarios por supplier

### Scenario: Crear usuario dentro del límite

Given un supplier con límite máximo de 3 usuarios  
And actualmente tiene 2 usuarios activos  
When el admin crea un nuevo usuario  
Then el sistema debe permitir la creación

### Scenario: Rechazar usuario al exceder límite

Given un supplier con límite máximo de 3 usuarios  
And ya tiene 3 usuarios activos o pendientes  
When el admin intenta crear otro usuario  
Then el sistema debe rechazar la creación  
And mostrar mensaje claro

### Scenario: Override por supplier

Given `SUPPLIER_MAX_USERS=3`  
And el supplier tiene `max_users=5`  
When se crean usuarios para ese supplier  
Then el sistema debe permitir hasta 5 usuarios

---

## Feature: Carga de precios por Excel

### Scenario: Supplier sube Excel válido

Given un supplier autenticado  
And el archivo Excel contiene precios válidos  
When sube el archivo  
Then el sistema debe crear un `rate_import`  
And guardar el archivo original  
And procesarlo en queue  
And marcarlo como `validated`

### Scenario: Excel con errores

Given un supplier autenticado  
And el Excel contiene filas inválidas  
When sube el archivo  
Then el sistema debe guardar todas las filas en staging  
And marcar filas inválidas  
And mostrar errores por fila  
And no publicar precios

### Scenario: Publicar tarifas validadas

Given un rate import validado sin errores críticos  
When el supplier aprueba la publicación  
Then el sistema debe crear nuevas tarifas versionadas  
And mantener historial  
And auditar la publicación

### Scenario: Vigencias cruzadas

Given ya existe una tarifa activa para el mismo supplier, oficina, ACRISS y rate plan  
When el Excel contiene otra tarifa con vigencia cruzada  
Then el sistema debe rechazar esa fila  
And registrar el error

---

## Feature: Reservas

### Scenario: Ver cola de reservas

Given un supplier autenticado  
When abre la cola de reservas  
Then debe ver solo sus reservas pendientes

### Scenario: Confirmar reserva

Given una reserva pendiente del supplier actual  
When el usuario confirma la reserva  
Then el sistema debe cambiar estado a `confirmed`  
And registrar acción  
And emitir evento `BookingConfirmed`

### Scenario: Rechazar reserva

Given una reserva pendiente del supplier actual  
When el usuario rechaza la reserva  
Then el sistema debe cambiar estado a `rejected`  
And requerir motivo  
And registrar acción  
And emitir evento `BookingRejected`

---

## Feature: Promociones

### Scenario: Crear promoción válida

Given un supplier autenticado  
When crea una promoción con vigencia válida  
Then el sistema debe guardarla  
And asociarla al supplier actual

### Scenario: Promoción sin vigencia

Given un supplier autenticado  
When intenta crear promoción sin fechas  
Then el sistema debe rechazarla

### Scenario: Promoción vencida

Given una promoción con fecha final pasada  
When el sistema ejecuta revisión programada  
Then debe marcarla como `expired`

---

## Feature: Reportes y exportación Excel

### Scenario: Consultar reporte

Given un supplier autenticado  
When consulta reporte de reservas  
Then el sistema debe mostrar solo datos del supplier actual  
And paginar resultados

### Scenario: Exportar reporte a Excel

Given un supplier autenticado  
When solicita exportación a Excel  
Then el sistema debe crear `report_export`  
And procesarlo en queue  
And notificar cuando esté listo

### Scenario: Exportación grande

Given un reporte con muchas filas  
When el usuario solicita exportación  
Then el sistema no debe procesarlo en el request  
And debe usar job en background

---

## Feature: Manejo profesional de errores

### Scenario: Error inesperado de aplicación

Given ocurre una excepción no controlada  
When el usuario realiza una operación  
Then el sistema debe mostrar mensaje amigable  
And generar `incident_id`  
And registrar log técnico  
And enviar alerta crítica por email

### Scenario: PostgreSQL no disponible

Given PostgreSQL está caído  
When ocurre un error crítico  
Then el sistema debe guardar incidente en SQLite local  
And mostrar mensaje maquillado al usuario  
And programar sincronización posterior

### Scenario: Sincronizar incidentes locales

Given existen incidentes pendientes en SQLite  
And PostgreSQL vuelve a estar disponible  
When corre `incidents:sync`  
Then el sistema debe guardar los incidentes en PostgreSQL  
And marcar los locales como sincronizados

---

## Feature: Circuit breaker

### Scenario: Servicio externo falla repetidamente

Given `quote-engine` falla 3 veces consecutivas  
When el sistema intenta sincronizar tarifas  
Then debe abrir el circuito  
And evitar nuevos intentos temporales  
And registrar incidente

### Scenario: Servicio externo se recupera

Given el circuito está en estado `half_open`  
When el servicio responde correctamente  
Then el sistema debe cerrar el circuito

---

## Feature: Persistencia temporal en SQLite

### Scenario: Guardar operación cuando PostgreSQL está caído

Given PostgreSQL no está disponible
And el usuario realiza una operación diferible
When el sistema intenta guardar la información
Then debe guardar la operación temporalmente en SQLite
And debe mostrar un mensaje amigable
And no debe mostrar error 500

### Scenario: Sincronizar operación pendiente

Given existen operaciones pendientes en SQLite
And PostgreSQL vuelve a estar disponible
When corre el proceso de sincronización
Then el sistema debe guardar las operaciones en PostgreSQL
And eliminar las operaciones sincronizadas de SQLite

### Scenario: No borrar SQLite si falla PostgreSQL

Given existe una operación pendiente en SQLite
And PostgreSQL responde con error durante la sincronización
When el sistema intenta sincronizar
Then no debe eliminar la operación de SQLite
And debe incrementar el contador de intentos

---

## Feature: Auditoría

### Scenario: Cambio de precio

Given un usuario modifica una tarifa  
When guarda el cambio  
Then el sistema debe registrar usuario, supplier, valor anterior y valor nuevo

### Scenario: Descarga de reporte

Given un usuario descarga un Excel  
When la descarga se completa  
Then el sistema debe auditar quién descargó, cuándo y con qué filtros

---

## Feature: Prevención N+1

### Scenario: Pantalla principal de reservas

Given existen reservas con supplier, vehículo y estado  
When se carga la pantalla  
Then el sistema debe usar eager loading  
And no debe disparar consultas N+1

### Scenario: Dashboard

Given existen métricas por supplier  
When se carga el dashboard  
Then el sistema debe usar agregados o `withCount`  
And no cargar relaciones completas innecesarias

---

## Feature: Seguridad

### Scenario: APP_DEBUG desactivado

Given el sistema está en producción  
When ocurre un error  
Then no debe mostrar stack trace

### Scenario: Supplier manipula supplier_id

Given un supplier autenticado  
When envía un `supplier_id` diferente en el request  
Then el sistema debe ignorarlo  
And usar el supplier_id de la sesión

---

## Acceptance Criteria Globales

- Ningún supplier puede ver datos de otro.

- Ningún Excel inválido publica tarifas.

- Ningún reporte exporta datos de otro supplier.

- Ningún error 500 crudo llega al usuario.

- Todo error crítico genera incidente.

- Si PostgreSQL falla, se usa SQLite fallback.

- Si vuelve PostgreSQL, se sincronizan incidentes.

- Los procesos pesados usan queues.

- Las exportaciones usan jobs.

- Las tarifas se versionan.

- Las promociones tienen vigencia.

- Las acciones críticas se auditan.

- Las pantallas principales no presentan N+1.

- Filament no contiene lógica de negocio.

- Todo caso funcional pasa por UseCase.
