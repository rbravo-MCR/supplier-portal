# ARCHITECTURE_GOVERNANCE.md

# Gobernanza Arquitectónica

## Objetivo

Mantener la arquitectura estable a largo plazo.

---

# Cambios permitidos

Antes de modificar:

```txt
Módulos
Persistencia
Eventos
Integraciones
Permisos
```

Debe existir:

```txt
ADR
Impact Analysis
Pruebas
```

---

# Nuevos módulos

Todo módulo nuevo debe:

```txt
Seguir MODULE_BLUEPRINT
Tener SDD parcial
Tener BDD
Tener TDD
```

---

# Nuevos eventos

Todo evento debe:

```txt
Entrar en EVENT_CATALOG
Ser idempotente
Ser auditado
```

---

# Nuevas tablas

Toda tabla debe:

```txt
Tener UUID
Tener timestamps
Tener índices
Tener owner definido
```

---

# Revisiones obligatorias

Ningún PR se aprueba si:

```txt
Rompe ADR
Rompe tests
Genera N+1
Rompe aislamiento multi-supplier
```
