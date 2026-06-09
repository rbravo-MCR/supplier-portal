# QUERY_BUDGET.md

# Query Budget

## Objetivo

Evitar degradación progresiva.

---

## Dashboard

Máximo:

```txt
15 queries
```

---

## Bookings List

Máximo:

```txt
10 queries
```

---

## Pricing List

Máximo:

```txt
10 queries
```

---

## Promotions List

Máximo:

```txt
10 queries
```

---

## Report Detail

Máximo:

```txt
20 queries
```

---

## Exportaciones

Obligatorio:

```txt
chunkById()
cursor()
lazy()
```

---

## Reglas

Prohibido:

```txt
Model::all()
Lazy Loading
Loops con relaciones
```

Obligatorio:

```txt
with()
withCount()
paginate()
```

---

## CI

Si una pantalla supera su Query Budget:

```txt
Build Failed
```
