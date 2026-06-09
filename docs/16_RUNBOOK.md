# RUNBOOK.md

# Runbook Operativo

## PostgreSQL Down

### Síntomas

```txt
Timeout
Connection refused
PDOException
```

### Acciones

```txt
1. Verificar servicio PostgreSQL
2. Verificar red
3. Verificar credenciales
4. Confirmar circuit breaker de base de datos
5. Revisar SQLite fallback si hubo escrituras
6. Revisar incidentes
7. Validar recuperación con system:database-circuit-probe
```

### Comandos

```txt
php artisan system:health-check
php artisan system:database-circuit-probe
php artisan system:database-circuit-reset
```

---

## Redis Down

### Síntomas

```txt
Jobs detenidos
Horizon en rojo
```

### Acciones

```txt
1. Reiniciar Redis
2. Revisar memoria
3. Revisar logs
4. Reanudar workers
```

---

## Queue detenida

### Síntomas

```txt
Reportes no generan
Excel no procesa
Emails no salen
```

### Acciones

```txt
1. Revisar Horizon
2. Reiniciar workers
3. Revisar jobs failed
```

---

## Circuit Breaker Open

### Síntomas

```txt
PostgreSQL no recibe intentos desde requests web
Requests devuelven 503 con incident_id
Escrituras guardan incidente local en SQLite
```

### Acciones

```txt
1. Revisar PostgreSQL
2. Revisar red y credenciales
3. Esperar probe automático cada 1 minuto
4. Ejecutar system:database-circuit-probe si se requiere validar ahora
5. Ejecutar system:database-circuit-reset solo si PostgreSQL ya fue validado manualmente
6. Validar recovery con system:health-check
```

### Reglas

```txt
3 intentos antes de fallback
100 ms entre intentos
Circuit breaker abre al superar umbral de fallos
GET/HEAD/OPTIONS no se guardan en SQLite
POST/PUT/PATCH/DELETE sí se guardan en SQLite
```

---

## Store & Forward creciendo

### Síntomas

```txt
pending_operations > 100
```

### Acciones

```txt
1. Revisar PostgreSQL
2. Ejecutar sincronización
3. Revisar errores
```

---

## Error Crítico

### Acciones

```txt
1. Obtener incident_id
2. Revisar system_incidents
3. Revisar logs
4. Revisar stack trace interno
5. Corregir
6. Documentar RCA
```

---

## Recovery Validation

Después de cualquier incidente:

```txt
Health Checks OK
Queues OK
Redis OK
PostgreSQL OK
Circuit Breaker cerrado
Store & Forward vacío
Sin jobs fallidos
```
