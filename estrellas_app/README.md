# Estrellas NW (PostgreSQL + .NET API + React Web)

## Requisitos
- Docker + Docker Compose

## Levantar todo
En esta carpeta:
```bash
docker compose up --build
```

## URLs
- Web (UI): http://localhost:5173
- API: http://localhost:8080
- Swagger: http://localhost:8080/swagger
- Postgres: localhost:5432 (DB: estrellas, user: estrellas_user, pass: estrellas_pass)

## Qué hace
- Cargar estrella (funcionario, fecha, tipo, desafío opcional)
- Buscar funcionarios y ver conteo por tipo
- Reportes: ranking total + lista por tipo de estrella y filtros por fecha/empresa

## WordPress (newton.com.py / Hostinger)

En `wordpress-plugin/estrellas-nw` hay un plugin que:

- Crea tablas MySQL con prefijo `wp_estrellas_*` (usa la misma base que WordPress en Hostinger).
- Expone la API REST en `https://tu-dominio/wp-json/estrellas-nw/v1/` (mismas rutas que la API .NET: `/health`, `/api/...`).
- Requiere usuario **logueado** en WordPress para llamar a la API (cookie + cabecera `X-WP-Nonce`).

**Instalación**

1. Subí la carpeta `estrellas-nw` a `wp-content/plugins/`.
2. En la carpeta `web`, ejecutá `npm install` y `npm run build` (Vite deja los assets en `estrellas-nw/public/`).
3. Activá el plugin en el escritorio de WordPress.
4. Creá una página y agregá el shortcode **`[estrellas_nw]`** (o el bloque de shortcode equivalente).

**Desarrollo local** seguís usando Docker + API .NET; la variable `VITE_API_BASE` en `web` apunta a `http://localhost:8080` como antes.
