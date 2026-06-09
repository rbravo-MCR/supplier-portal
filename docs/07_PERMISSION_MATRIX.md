# PERMISSION_MATRIX.md

# Supplier Portal — Permission Matrix

## Roles

```txt
super_admin
admin
auditor
supplier_admin
supplier_user
```

---

# Suppliers

| Acción              | super_admin | admin | auditor | supplier_admin | supplier_user |
| ------------------- | ----------- | ----- | ------- | -------------- | ------------- |
| Ver suppliers       | ✅           | ✅     | ✅       | ❌              | ❌             |
| Crear supplier      | ✅           | ❌     | ❌       | ❌              | ❌             |
| Editar supplier     | ✅           | ❌     | ❌       | ❌              | ❌             |
| Desactivar supplier | ✅           | ❌     | ❌       | ❌              | ❌             |

---

# Usuarios

| Acción              | super_admin | admin | auditor | supplier_admin   | supplier_user |
| ------------------- | ----------- | ----- | ------- | ---------------- | ------------- |
| Ver usuarios        | ✅           | ✅     | ✅       | ✅ (solo propios) | ❌             |
| Crear usuarios      | ✅           | ✅     | ❌       | ✅ (solo propios) | ❌             |
| Editar usuarios     | ✅           | ✅     | ❌       | ✅ (solo propios) | ❌             |
| Desactivar usuarios | ✅           | ✅     | ❌       | ✅ (solo propios) | ❌             |

---

# Reservas

| Acción            | super_admin | admin | auditor | supplier_admin | supplier_user |
| ----------------- | ----------- | ----- | ------- | -------------- | ------------- |
| Ver reservas      | ✅           | ✅     | ✅       | ✅              | ✅             |
| Confirmar reserva | ✅           | ✅     | ❌       | ✅              | ✅             |
| Rechazar reserva  | ✅           | ✅     | ❌       | ✅              | ✅             |

---

# Tarifas

| Acción           | super_admin | admin | auditor | supplier_admin | supplier_user |
| ---------------- | ----------- | ----- | ------- | -------------- | ------------- |
| Ver tarifas      | ✅           | ✅     | ✅       | ✅              | ✅             |
| Crear tarifas    | ✅           | ✅     | ❌       | ✅              | ❌             |
| Editar tarifas   | ✅           | ✅     | ❌       | ✅              | ❌             |
| Publicar tarifas | ✅           | ✅     | ❌       | ✅              | ❌             |
| Importar Excel   | ✅           | ✅     | ❌       | ✅              | ❌             |

---

# Promociones

| Acción              | super_admin | admin | auditor | supplier_admin | supplier_user |
| ------------------- | ----------- | ----- | ------- | -------------- | ------------- |
| Ver promociones     | ✅           | ✅     | ✅       | ✅              | ✅             |
| Crear promociones   | ✅           | ✅     | ❌       | ✅              | ❌             |
| Activar promociones | ✅           | ✅     | ❌       | ✅              | ❌             |

---

# Reportes

| Acción             | super_admin | admin | auditor | supplier_admin | supplier_user |
| ------------------ | ----------- | ----- | ------- | -------------- | ------------- |
| Consultar reportes | ✅           | ✅     | ✅       | ✅              | ✅             |
| Exportar Excel     | ✅           | ✅     | ✅       | ✅              | ❌             |

---

# Auditoría

| Acción        | super_admin | admin | auditor | supplier_admin | supplier_user |
| ------------- | ----------- | ----- | ------- | -------------- | ------------- |
| Ver auditoría | ✅           | ✅     | ✅       | ❌              | ❌             |

---

# Regla Global

Todo acceso debe validarse mediante:

```txt
Policy
Permission
SupplierContext
```

Nunca únicamente por rol.
