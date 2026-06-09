# OBSERVABILITY_PLAN.md

# Plan de Observabilidad

## Objetivo

Detectar problemas antes que los usuarios.

---

# Herramientas

```txt
Laravel Horizon
Laravel Pulse
Logs estructurados
Email Alerts
Health Checks
```

---

# Logs obligatorios

Todo log debe incluir:

```txt
incident_id
correlation_id
request_id
supplier_id
user_id
module
action
```

---

# Métricas

## Aplicación

```txt
Tiempo promedio respuesta
Errores por minuto
Errores por módulo
Usuarios activos
```

---

## PostgreSQL

```txt
Conexiones activas
Queries lentas
Locks
Uso CPU
Circuit breaker state
Circuit breaker failures
Database probe result
```

---

## Redis

```txt
Uso memoria
Jobs pendientes
Jobs fallidos
```

---

## Store & Forward

```txt
Operaciones pendientes
Operaciones sincronizadas
Errores de sincronización
Incidentes locales pendientes
```

---

# Alertas

Enviar email cuando:

```txt
PostgreSQL down
Redis down
Circuit Breaker Open
Job crítico falla
Store & Forward supera 100 registros
```

---

# Health Checks

Endpoints:

```txt
/health
/health/db
/health/redis
/health/queue
/health/storage
/health/outbox
/health/failed-jobs
```

Estados:

```txt
healthy
degraded
down
```

Comandos operativos:

```txt
php artisan system:health-check
php artisan system:database-circuit-probe
php artisan system:database-circuit-reset
```
