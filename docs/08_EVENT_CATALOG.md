# EVENT_CATALOG.md

# Supplier Portal — Event Catalog

## Booking Events

### BookingConfirmed

Payload:

```json
{
  "booking_uuid": "",
  "supplier_id": "",
  "user_id": "",
  "confirmed_at": ""
}
```

---

### BookingRejected

```json
{
  "booking_uuid": "",
  "supplier_id": "",
  "user_id": "",
  "reason": "",
  "rejected_at": ""
}
```

---

## Pricing Events

### RateImportUploaded

```json
{
  "rate_import_uuid": "",
  "supplier_id": "",
  "uploaded_by": ""
}
```

---

### RateImportValidated

```json
{
  "rate_import_uuid": "",
  "valid_rows": 0,
  "invalid_rows": 0
}
```

---

### RatesPublished

```json
{
  "supplier_id": "",
  "rate_plan_code": "",
  "version": 0
}
```

---

## Promotion Events

### PromotionCreated

```json
{
  "promotion_uuid": "",
  "supplier_id": ""
}
```

---

### PromotionActivated

```json
{
  "promotion_uuid": "",
  "supplier_id": ""
}
```

---

## Availability Events

### AvailabilityUpdated

```json
{
  "availability_uuid": "",
  "supplier_id": ""
}
```

---

## Reporting Events

### ReportExportRequested

```json
{
  "report_export_uuid": "",
  "requested_by": ""
}
```

---

### ReportExportCompleted

```json
{
  "report_export_uuid": "",
  "file_path": ""
}
```

---

## User Events

### UserCreated

### UserActivated

### UserBlocked

### UserDisabled

---

## System Events

### IncidentCreated

### CircuitBreakerOpened

### CircuitBreakerClosed

### StoreForwardQueued

### StoreForwardSynced

---

# Reglas

Todos los eventos:

```txt
UUID obligatorio
timestamp obligatorio
idempotentes
auditables
persistidos en outbox_events
```
