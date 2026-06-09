# SLA_SLO_SLI.md

# SLA / SLO / SLI

## SLA

Compromiso con usuarios.

Disponibilidad:

```txt
99.9%
```

Tiempo máximo mensual de caída:

```txt
43 minutos
```

---

## SLO

### Login

```txt
95% < 1 segundo
99% < 2 segundos
```

---

### Consultas

```txt
95% < 500 ms
99% < 1 segundo
```

---

### Dashboard

```txt
95% < 2 segundos
```

---

### Exportación Excel

```txt
95% < 5 minutos
```

---

## SLI

Métricas observadas.

### Disponibilidad

```txt
uptime %
```

---

### Errores

```txt
errores/minuto
errores por módulo
```

---

### Base de Datos

```txt
queries lentas
conexiones activas
locks
circuit breaker state
database probe result
```

---

### Queue

```txt
jobs pendientes
jobs fallidos
tiempo promedio ejecución
```

---

## Alertas

Generar alerta cuando:

```txt
Disponibilidad < 99.9%
Jobs fallidos > 20
Store & Forward > 100
Circuit Breaker Open
PostgreSQL Down
Redis Down
```

## Circuit Breaker PostgreSQL

```txt
3 intentos antes de fallback
100 ms entre intentos
Probe automático cada 1 minuto
Reset manual: php artisan system:database-circuit-reset
```

Persistencia local:

```txt
POST/PUT/PATCH/DELETE guardan incidente en SQLite si PostgreSQL no responde.
GET/HEAD/OPTIONS no guardan incidente local.
```
