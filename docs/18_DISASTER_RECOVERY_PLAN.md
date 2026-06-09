# DISASTER_RECOVERY_PLAN.md

# Plan de Recuperación ante Desastres

## Objetivo

Recuperar operación ante incidentes críticos.

---

# Escenarios

## PostgreSQL Corrupto

### Procedimiento

```txt
1. Bloquear escrituras
2. Activar modo degradado / circuit breaker
3. Restaurar último backup
4. Verificar integridad
5. Ejecutar system:database-circuit-probe
6. Reanudar operación
```

---

## Redis Perdido

### Procedimiento

```txt
1. Restaurar Redis
2. Reiniciar Horizon
3. Validar colas
4. Reprocesar failed jobs
```

---

## Servidor Caído

### Procedimiento

```txt
1. Levantar servidor secundario
2. Restaurar configuración
3. Restaurar backups
4. Validar health checks
```

---

## SQLite Store & Forward Saturado

### Procedimiento

```txt
1. Revisar PostgreSQL
2. Revisar circuit breaker de base de datos
3. Ejecutar system:database-circuit-probe
4. Ejecutar sincronización cuando esté disponible
5. Validar idempotencia
6. Limpiar registros sincronizados
```

---

## PostgreSQL Circuit Breaker Open

### Procedimiento

```txt
1. No reintentar PostgreSQL desde requests web mientras el circuito esté abierto
2. Ejecutar probe automático cada 1 minuto
3. Cerrar circuito automáticamente si PostgreSQL responde
4. Usar system:database-circuit-reset solo tras validación manual
5. Confirmar system:health-check OK
```

### Fallback SQLite

```txt
Solo escrituras se registran en SQLite:
POST
PUT
PATCH
DELETE

Consultas no se guardan localmente.
```

---

# RTO

Tiempo objetivo recuperación:

```txt
30 minutos
```

---

# RPO

Pérdida máxima aceptable:

```txt
5 minutos
```

---

# Backups

## PostgreSQL

```txt
Backup diario completo
Backup incremental cada hora
Retención 30 días
```

---

## Archivos

```txt
Storage
Excel
Reportes
```

Retención:

```txt
90 días
```
