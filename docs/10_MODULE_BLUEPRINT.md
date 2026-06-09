# MODULE_BLUEPRINT.md

# Blueprint Oficial de Módulos

Todo módulo nuevo debe respetar esta estructura.

```txt
Module/
├── Domain/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Rules/
│   ├── Events/
│   └── Exceptions/
│
├── Application/
│   ├── UseCases/
│   ├── DTOs/
│   └── Services/
│
├── Infrastructure/
│   ├── Repositories/
│   ├── Persistence/
│   └── External/
│
├── Presentation/
│   └── Filament/
│
├── Policies/
├── Jobs/
└── Tests/
```

## Responsabilidades

### Domain

Contiene:

```txt
Reglas de negocio
Entidades
Value Objects
Eventos
```

Prohibido:

```txt
Eloquent
Filament
Redis
HTTP
```

---

### Application

Contiene:

```txt
Use Cases
DTOs
Orquestación
```

Prohibido:

```txt
Queries SQL
UI
```

---

### Infrastructure

Contiene:

```txt
Repositorios
Persistencia
Integraciones
```

---

### Presentation

Contiene:

```txt
Filament
Forms
Tables
Actions
```

Prohibido:

```txt
Lógica de negocio
```

---

## Todo módulo debe tener

```txt
Policy
Tests
DTOs
Use Cases
Logs
Auditoría
```
