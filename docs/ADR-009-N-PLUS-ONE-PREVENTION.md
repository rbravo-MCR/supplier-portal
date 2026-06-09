Decisión:
Toda consulta relacional debe usar eager loading.

Motivación:
Evitar degradación de rendimiento al crecer el volumen de datos.

Consecuencias:

- with()
- withCount()
- paginate()
- chunkById()
- preventLazyLoading()

Prohibiciones:

- Lazy loading en desarrollo.
- Model::all() en pantallas productivas.
