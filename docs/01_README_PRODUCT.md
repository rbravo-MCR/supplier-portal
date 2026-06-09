# README_PRODUCT.md

# Supplier Portal

## Visión del Producto

Supplier Portal es una plataforma empresarial que permite a proveedores de renta de vehículos que no cuentan con API ni SOAP administrar su operación comercial desde una interfaz web segura.

El sistema permite a cada supplier gestionar de forma autónoma:

```txt
Tarifas
Promociones
Disponibilidad
Inventario
Reservas
Reportes
Usuarios
```

sin depender de integraciones técnicas complejas.

---

# Problema de Negocio

Actualmente existen suppliers que:

```txt
No tienen API
No tienen SOAP
No tienen conectividad automática
Trabajan mediante Excel
Gestionan precios manualmente
Gestionan disponibilidad manualmente
```

Esto provoca:

```txt
Retrasos operativos
Errores de captura
Información desactualizada
Procesos manuales
Baja trazabilidad
```

Supplier Portal resuelve este problema proporcionando una plataforma única para capturar y administrar información comercial.

---

# Objetivos

## Objetivo Principal

Permitir que los suppliers administren su operación comercial mediante una plataforma web segura y escalable.

---

## Objetivos Secundarios

```txt
Reducir errores manuales
Centralizar información
Controlar accesos
Automatizar cargas de Excel
Generar reportes
Mantener trazabilidad
Preparar futuras integraciones
```

---

# Alcance Inicial

## Incluido

### Identity

```txt
Login
Roles
Permisos
2FA
Recuperación de contraseña
```

### Suppliers

```txt
Administración de suppliers
Configuración de límites
Configuración operativa
```

### Usuarios

```txt
Alta
Edición
Bloqueo
Activación
Asignación de roles
```

### Pricing

```txt
Tarifas manuales
Carga por Excel
Versionamiento
Vigencias
Historial
```

### Promotions

```txt
Descuentos
Ofertas
Promociones temporales
```

### Availability

```txt
Disponibilidad
Restricciones
Control de inventario
```

### Bookings

```txt
Consulta
Confirmación
Rechazo
Historial
```

### Reporting

```txt
Consultas
Exportación Excel
Reportes operativos
```

### Audit

```txt
Bitácora
Trazabilidad
Historial de cambios
```

---

# Fuera de Alcance

No forma parte de esta fase:

```txt
Pasarela de pagos
Facturación
Cancelaciones
Motor de precios dinámico
Aplicación móvil
Chat
CRM
```

---

# Usuarios del Sistema

## Super Admin

Responsable de administrar toda la plataforma.

Permisos:

```txt
Todos
```

---

## Admin

Responsable operativo.

Permisos:

```txt
Administración general
Monitoreo
Configuración
```

---

## Auditor

Permisos:

```txt
Solo lectura
Reportes
Bitácora
```

---

## Supplier Admin

Responsable de un supplier.

Permisos:

```txt
Usuarios del supplier
Tarifas
Promociones
Disponibilidad
Reservas
Reportes
```

---

## Supplier User

Permisos limitados.

```txt
Operación diaria
```

---

# Multi Supplier

Principio fundamental:

```txt
Cada supplier solo puede visualizar y operar información propia.
```

Reglas:

```txt
Aislamiento total
Policies obligatorias
SupplierContext obligatorio
```

---

# Requerimientos No Funcionales

## Seguridad

```txt
2FA
CSRF
Rate limiting
Auditoría
Sesiones seguras
```

## Disponibilidad

```txt
PostgreSQL principal
SQLite Store & Forward
Recuperación automática
```

## Observabilidad

```txt
Pulse
Horizon
Logs estructurados
Alertas críticas
Health checks
```

## Escalabilidad

```txt
Redis
Queues
Outbox Pattern
Circuit Breaker
Octane en fase posterior
```

---

# Excel Import

La carga masiva de precios debe seguir:

```txt
Excel
↓
Validación
↓
Staging
↓
Aprobación
↓
Publicación
```

Nunca:

```txt
Excel
↓
Rates
```

directamente.

---

# Store & Forward

Cuando PostgreSQL esté disponible:

```txt
Guardar normalmente
```

Cuando PostgreSQL no esté disponible:

```txt
Guardar temporalmente en SQLite
```

Cuando PostgreSQL vuelva:

```txt
Sincronizar
Eliminar SQLite
```

---

# Reglas Críticas

```txt
No mostrar errores técnicos.
No mostrar stack traces.
No exponer información entre suppliers.
No usar lógica de negocio en Filament.
No permitir N+1.
No usar SQLite como base principal.
No escribir Excel directo a tablas finales.
```

---

# Criterio de Éxito

El sistema será considerado exitoso cuando:

```txt
Los suppliers puedan operar sin soporte técnico.
La información esté centralizada.
Las tarifas puedan cargarse por Excel.
Las reservas puedan gestionarse desde el portal.
Los reportes puedan exportarse.
La auditoría permita trazabilidad completa.
La plataforma sea resiliente ante fallos de PostgreSQL.
```
