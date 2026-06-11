# Registro de Estrellas NW

Sistema para registrar, consultar y reportar **estrellas** otorgadas a funcionarios. Incluye una interfaz web, API REST, base de datos PostgreSQL y un plugin de WordPress para desplegarlo en producción.

---

## Funcionalidades

- **Cargar estrellas** — Registrar una estrella por funcionario (fecha, tipo y desafío opcional).
- **Funcionarios** — Buscar empleados y ver el conteo de estrellas por tipo.
- **Misiones** — Gestión de desafíos asociados a las estrellas.
- **Reportes** — Ranking general, listados por tipo y filtros por fecha o empresa.
- **Exportación** — Descarga de datos en CSV y JSON.

### Tipos de estrella

| Código    | Descripción        |
|-----------|--------------------|
| `FUNNY`   | Funny Star         |
| `TEACHE`  | Teacher Star       |
| `EARLY`   | Early Bird Star    |
| `BUDDY`   | Buddy Star         |
| `SMARTY`  | Smarty Star        |
| `BIRTHDAY`| Birthday Star      |

---

## Tecnologías

| Componente | Stack |
|------------|-------|
| Frontend   | React 18 + Vite |
| Backend    | .NET 8 (Minimal API) |
| Base de datos (local) | PostgreSQL 16 |
| Base de datos (WordPress) | MySQL |
| Contenedores | Docker + Docker Compose |

---

## Estructura del proyecto

```
estrellas_app/
├── api/                  # API REST (.NET 8)
├── web/                  # Interfaz React (Vite)
├── db/                   # Scripts SQL e importación de datos
├── wordpress-plugin/     # Plugin para WordPress / Hostinger
│   └── estrellas-nw/
├── docker-compose.yml    # Orquestación local
├── INICIO.bat            # Atajo para levantar el entorno en Windows
└── README.md             # Documentación técnica detallada
```

---

## Inicio rápido (desarrollo local)

### Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) con Docker Compose

### Levantar el entorno

```bash
cd estrellas_app
docker compose up --build
```

En Windows también podés ejecutar `INICIO.bat` desde la carpeta `estrellas_app`.

### URLs locales

| Servicio | URL |
|----------|-----|
| Interfaz web | http://localhost:5173 |
| API REST | http://localhost:8080 |
| Swagger | http://localhost:8080/swagger |
| PostgreSQL | `localhost:5432` |

**Credenciales de la base de datos (desarrollo):**

| Campo | Valor |
|-------|-------|
| Base de datos | `estrellas` |
| Usuario | `estrellas_user` |
| Contraseña | `estrellas_pass` |

---

## Despliegue en WordPress

El plugin en `estrellas_app/wordpress-plugin/estrellas-nw` permite usar el sistema en un sitio WordPress (por ejemplo, en Hostinger):

1. Subí la carpeta `estrellas-nw` a `wp-content/plugins/`.
2. En `estrellas_app/web`, ejecutá:
   ```bash
   npm install
   npm run build
   ```
   Vite generará los assets en `estrellas-nw/public/`.
3. Activá el plugin desde el panel de WordPress.
4. Creá una página y agregá el shortcode **`[estrellas_nw]`**.

La API del plugin queda disponible en:

```
https://tu-dominio/wp-json/estrellas-nw/v1/
```

Requiere usuario **logueado** en WordPress (cookie de sesión + cabecera `X-WP-Nonce`).

---

## Autor

**David Villar** — [@DavidVillarM](https://github.com/DavidVillarM)

Estudiante de Ingeniería en Informática · Newton Centro de Estudios · Paraguay
