# Volúmenes persistentes (Docker)

Para que los archivos subidos por usuarios (logos de campañas, fotos, evidencias, etc.) no se pierdan en cada deploy, monta como volumen persistente el directorio `storage/app`.

## Recomendado (mínimo)

- `storage/app`
  - Incluye `storage/app/public` (logos y archivos públicos que normalmente se exponen vía `public/storage`).
  - Incluye `storage/app/private` si en el futuro guardas archivos privados.

## Opcional

- `storage/logs` (si quieres conservar logs en disco)
- `storage/framework/cache` y `storage/framework/sessions` (solo si usas `file` en cache/sesiones; en producción normalmente se usa Redis o base de datos)

## Nota sobre `public/storage`

En muchos despliegues se crea el enlace simbólico con:

`php artisan storage:link`

Si tu servidor devuelve `403` al acceder a `/storage/...` (por restricciones de symlinks o permisos), puedes:

- Ajustar el servidor web (Apache: `FollowSymLinks`, Nginx: `disable_symlinks`), o
- Servir archivos desde Laravel (ruta controlada), como `GET /media/campaign-logo/{filename}`.

