# THREAT_MODEL.md

# Supplier Portal — Threat Model

## Objetivo

Identificar amenazas de seguridad antes de la implementación.

---

# Riesgo 1

## Acceso entre suppliers

### Ataque

```txt
Supplier A intenta consultar datos de Supplier B
```

### Mitigación

```txt
Policies
SupplierContext
Filtros obligatorios por supplier_id
Tests multi-supplier
```

---

# Riesgo 2

## Manipulación de requests

### Ataque

```txt
Modificar supplier_id
Modificar booking_id
Modificar promotion_id
```

### Mitigación

```txt
Nunca confiar en IDs enviados por el cliente
Usar supplier_id autenticado
Policies
```

---

# Riesgo 3

## Fuerza bruta

### Ataque

```txt
Intentos masivos de login
```

### Mitigación

```txt
Rate limiting
Bloqueo temporal
2FA
```

---

# Riesgo 4

## Robo de sesión

### Mitigación

```txt
Secure Cookies
HttpOnly
SameSite
Session Rotation
```

---

# Riesgo 5

## SQL Injection

### Mitigación

```txt
Eloquent
Query Builder
Validación
DTOs
```

---

# Riesgo 6

## Excel malicioso

### Ataque

```txt
Fórmulas
Macros
Archivos alterados
```

### Mitigación

```txt
Validación de estructura
Validación de columnas
Procesamiento en staging
```

---

# Riesgo 7

## Escalada de privilegios

### Ataque

```txt
supplier_user intenta actuar como supplier_admin
```

### Mitigación

```txt
Spatie Permission
Policies
Permission Matrix
```

---

# Riesgo 8

## Exposición de errores

### Ataque

```txt
Obtención de stack traces
Obtención de rutas internas
Obtención de SQL
```

### Mitigación

```txt
APP_DEBUG=false
Safe Error Responses
Incident Tracking
```

---

# Riesgo 9

## Caída PostgreSQL

### Mitigación

```txt
Store & Forward
SQLite temporal
Sincronización posterior
```

---

# Riesgo 10

## Denegación de servicio

### Mitigación

```txt
Rate Limiting
Queues
Redis
Circuit Breaker
Health Checks
```

---

# Riesgo 11

## N+1 oculto

### Impacto

```txt
Degradación progresiva
Aumento de latencia
Sobrecarga PostgreSQL
```

### Mitigación

```txt
with()
withCount()
preventLazyLoading()
Architecture Tests
```

---

# Riesgo 12

## Fuga de datos en reportes

### Mitigación

```txt
Filtrar siempre por supplier_id
Policies
Tests de exportación
Auditoría de descargas
```

---

# Riesgo 13

## Duplicación Store & Forward

### Mitigación

```txt
operation_uuid
Idempotencia
Unique Constraints
```

---

# Nivel de Riesgo Objetivo

```txt
Crítico → Mitigado
Alto → Mitigado
Medio → Controlado
Bajo → Monitoreado
```
