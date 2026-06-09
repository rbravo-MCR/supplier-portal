# DATA_MODEL.md

## Supplier Portal — Data Model

## 1. Reglas generales

Base principal:

```txt
PostgreSQL
```

Fallback temporal:

```txt
SQLite
```

Identificadores:

```txt
id BIGINT
uuid UUID
```

Regla multi-supplier:

```txt
Toda tabla operativa debe incluir supplier_id.
```

---

# 2. suppliers

```txt
id BIGINT PK
uuid UUID UNIQUE
name VARCHAR
code VARCHAR UNIQUE
status VARCHAR
max_users INTEGER NULL
contact_name VARCHAR NULL
email VARCHAR NULL
phone VARCHAR NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
code
status
uuid
```

---

# 3. users

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT NULL
name VARCHAR
email VARCHAR UNIQUE
password VARCHAR
role VARCHAR
status VARCHAR
two_factor_enabled BOOLEAN
two_factor_confirmed_at TIMESTAMP NULL
last_login_at TIMESTAMP NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
supplier_id
email
role
status
```

---

# 4. bookings

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT
reservation_code VARCHAR
customer_name VARCHAR
vehicle_class VARCHAR NULL
pickup_office_code VARCHAR
dropoff_office_code VARCHAR
pickup_at TIMESTAMP
dropoff_at TIMESTAMP
total_amount NUMERIC(12,2)
currency VARCHAR(3)
status VARCHAR
metadata JSONB NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
(supplier_id, status)
(supplier_id, reservation_code)
(supplier_id, pickup_at)
uuid
```

---

# 5. booking_actions

```txt
id BIGINT PK
uuid UUID UNIQUE
booking_id BIGINT
supplier_id BIGINT
user_id BIGINT
action VARCHAR
reason TEXT NULL
metadata JSONB NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
booking_id
supplier_id
user_id
action
```

---

# 6. vehicles

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT
office_code VARCHAR
acriss_code VARCHAR
vehicle_class VARCHAR
brand VARCHAR NULL
model VARCHAR NULL
status VARCHAR
metadata JSONB NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
(supplier_id, office_code)
(supplier_id, acriss_code)
(supplier_id, status)
```

---

# 7. rate_plans

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT
code VARCHAR
name VARCHAR
status VARCHAR
metadata JSONB NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
(supplier_id, code) UNIQUE
(supplier_id, status)
```

---

# 8. rates

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT
office_code VARCHAR
vehicle_class VARCHAR
acriss_code VARCHAR
rate_plan_code VARCHAR
currency VARCHAR(3)
base_price NUMERIC(12,2)
valid_from DATE
valid_to DATE
min_days INTEGER NULL
max_days INTEGER NULL
status VARCHAR
version INTEGER
operation_uuid UUID NULL
created_by BIGINT
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
(supplier_id, office_code, acriss_code, rate_plan_code)
(supplier_id, valid_from, valid_to)
(supplier_id, status)
operation_uuid UNIQUE NULL
```

---

# 9. rate_imports

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT
original_filename VARCHAR
stored_path VARCHAR
status VARCHAR
total_rows INTEGER DEFAULT 0
valid_rows INTEGER DEFAULT 0
invalid_rows INTEGER DEFAULT 0
uploaded_by BIGINT
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
(supplier_id, status)
uploaded_by
uuid
```

---

# 10. rate_import_rows

```txt
id BIGINT PK
rate_import_id BIGINT
row_number INTEGER
raw_data JSONB
normalized_data JSONB NULL
errors JSONB NULL
status VARCHAR
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
rate_import_id
(rate_import_id, status)
```

---

# 11. promotions

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT
name VARCHAR
type VARCHAR
value NUMERIC(12,2)
valid_from DATE
valid_to DATE
status VARCHAR
metadata JSONB NULL
operation_uuid UUID NULL
created_by BIGINT
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
(supplier_id, status)
(supplier_id, valid_from, valid_to)
operation_uuid UNIQUE NULL
```

---

# 12. availability_rules

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT
office_code VARCHAR
acriss_code VARCHAR
available_from DATE
available_to DATE
quantity INTEGER
status VARCHAR
metadata JSONB NULL
operation_uuid UUID NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
(supplier_id, office_code, acriss_code)
(supplier_id, available_from, available_to)
operation_uuid UNIQUE NULL
```

---

# 13. report_exports

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT NULL
user_id BIGINT
report_type VARCHAR
filters JSONB
format VARCHAR
status VARCHAR
file_path VARCHAR NULL
rows_count INTEGER NULL
error_message TEXT NULL
created_at TIMESTAMP
completed_at TIMESTAMP NULL
expires_at TIMESTAMP NULL
```

Índices:

```txt
(supplier_id, report_type)
(user_id, status)
status
```

---

# 14. audit_logs

```txt
id BIGINT PK
uuid UUID UNIQUE
supplier_id BIGINT NULL
user_id BIGINT NULL
module VARCHAR
action VARCHAR
entity_type VARCHAR
entity_id BIGINT NULL
old_values JSONB NULL
new_values JSONB NULL
ip_address VARCHAR NULL
user_agent TEXT NULL
created_at TIMESTAMP
```

Índices:

```txt
supplier_id
user_id
module
action
created_at
```

---

# 15. system_incidents

```txt
id BIGINT PK
incident_id VARCHAR UNIQUE
correlation_id VARCHAR
supplier_id BIGINT NULL
user_id BIGINT NULL
module VARCHAR
action VARCHAR
severity VARCHAR
exception_class VARCHAR
safe_message TEXT
technical_message TEXT NULL
stack_trace TEXT NULL
payload_json JSONB NULL
status VARCHAR
resolved_at TIMESTAMP NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
incident_id
correlation_id
severity
status
created_at
```

---

# 16. outbox_events

```txt
id BIGINT PK
uuid UUID UNIQUE
aggregate_type VARCHAR
aggregate_id BIGINT
event_type VARCHAR
payload JSONB
status VARCHAR
attempts INTEGER DEFAULT 0
available_at TIMESTAMP NULL
processed_at TIMESTAMP NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Índices:

```txt
(status, available_at)
event_type
aggregate_type
```

---

# 17. SQLite fallback: pending_operations

SQLite solo debe usarse como almacenamiento temporal.

```txt
id INTEGER PK
operation_uuid TEXT UNIQUE
supplier_id INTEGER NULL
user_id INTEGER NULL
module TEXT
entity_type TEXT
operation_type TEXT
payload_json TEXT
status TEXT
attempts INTEGER DEFAULT 0
last_error TEXT NULL
created_at TEXT
synced_at TEXT NULL
```

Regla:

```txt
Al sincronizar exitosamente con PostgreSQL, eliminar de SQLite.
```

---

# 18. SQLite fallback: local_incidents

```txt
id INTEGER PK
incident_id TEXT UNIQUE
correlation_id TEXT
supplier_id INTEGER NULL
user_id INTEGER NULL
module TEXT
action TEXT
severity TEXT
safe_message TEXT
technical_message TEXT NULL
payload_json TEXT NULL
status TEXT
attempts INTEGER DEFAULT 0
created_at TEXT
synced_at TEXT NULL
```

Regla:

```txt
Solo almacenar fallos de requests mutables:
POST
PUT
PATCH
DELETE

No almacenar consultas:
GET
HEAD
OPTIONS

Al sincronizar exitosamente con PostgreSQL, marcar synced_at.
```

---

# 19. Índices obligatorios por multi-supplier

Toda tabla con `supplier_id` debe tener mínimo:

```txt
supplier_id
(supplier_id, status)
(supplier_id, created_at)
```

Según aplique.

---

# 20. Prohibiciones

```txt
No usar SQLite como base principal.
No guardar business data final en SQLite.
No crear queries supplier sin supplier_id.
No usar Model::all() en pantallas productivas.
No usar Excel directo a rates.
No borrar SQLite antes de confirmar PostgreSQL.
```
