# 🚀 Laravel Docker Template

> Backend API listo para usar, construido con **Laravel 11**, autenticación por tokens con **Sanctum**, sistema de **roles**, documentación automática con **Scramble** y entorno de desarrollo con **Docker**.

---

## 📦 ¿Qué incluye este template?

- ✅ API REST con Laravel 11
- ✅ Autenticación con tokens (Laravel Sanctum)
- ✅ Sistema de roles y permisos por middleware
- ✅ CRUD completo de usuarios
- ✅ Documentación visual automática de endpoints (Scramble)
- ✅ Entorno Docker listo (PHP + MySQL + phpMyAdmin)
- ✅ Usuario administrador por defecto al instalar

---

## 📋 Requisitos previos

Asegúrate de tener instalado lo siguiente antes de comenzar:

| Herramienta | Versión mínima | Descarga |
|-------------|----------------|----------|
| Docker Desktop | Cualquiera | [docker.com](https://www.docker.com/products/docker-desktop) |
| PHP | 8.2+ | [php.net](https://www.php.net) |
| Composer | Cualquiera | [getcomposer.org](https://getcomposer.org) |

---

## ⚡ Instalación paso a paso

### Paso 1 — Clonar el repositorio

```bash
git clone https://github.com/tu-usuario/laravel-docker-template.git
cd laravel-docker-template
```

---

### Paso 2 — Crear el archivo de entorno

```bash
cp .env.example .env
```

---

### Paso 3 — Configurar las variables de entorno

Abre el archivo `.env` que acabas de crear y ajusta los siguientes valores:

```env
# Nombre del proyecto
# ⚠️ Sin espacios — se usa para nombrar los contenedores Docker
# ✅ Correcto:   APP_NAME=mi-proyecto
# ❌ Incorrecto: APP_NAME=mi proyecto
APP_NAME=mi-proyecto

# Puertos donde correrá cada servicio
# Si algún puerto ya está en uso en tu máquina, cámbialo por otro (ej: 8001, 3307, 8091)
APP_PORT=8000        # Tu API Laravel
DB_PORT=3306         # MySQL
PMA_PORT=8090        # phpMyAdmin (interfaz visual de la DB)

# Base de datos — usa los valores que prefieras
DB_DATABASE=mi_base_de_datos
DB_USERNAME=mi_usuario
DB_PASSWORD=mi_password
DB_ROOT_PASSWORD=mi_root_password
```

---

### Paso 4 — Instalar dependencias de PHP

```bash
composer install
```

> Esto descarga todas las librerías de Laravel y las coloca en la carpeta `vendor/`.

---

### Paso 5 — Generar la clave de la aplicación

```bash
php artisan key:generate
```

> Laravel necesita esta clave para encriptar sesiones y tokens. Se guarda automáticamente en tu `.env`.

---

### Paso 6 — Levantar los contenedores Docker

```bash
docker-compose up -d
```

Esto construye y levanta **3 contenedores**:

| Contenedor | Qué es | URL |
|------------|--------|-----|
| `{APP_NAME}_app` | Tu API Laravel | `http://localhost:{APP_PORT}` |
| `{APP_NAME}_db` | Base de datos MySQL | Puerto `{DB_PORT}` |
| `{APP_NAME}_phpmyadmin` | Interfaz visual de la DB | `http://localhost:{PMA_PORT}` |

> La primera vez tarda un poco más porque Docker descarga e instala las imágenes.

---

### Paso 7 — Correr migraciones y seeders

```bash
docker exec {APP_NAME}_app php artisan migrate --seed
```

> 💡 Reemplaza `{APP_NAME}` con el valor exacto que pusiste en `.env`.
>
> Ejemplo: si `APP_NAME=mi-proyecto` el comando sería:
> ```bash
> docker exec mi-proyecto_app php artisan migrate --seed
> ```

Esto hace dos cosas:
- **`migrate`** → crea todas las tablas en la base de datos
- **`--seed`** → inserta los datos iniciales (roles y usuario administrador)

---

## ✅ Verificar que todo funciona

Después de la instalación, verifica que los 3 servicios respondan correctamente:

### 1. API Laravel

Abre en el navegador:
http://localhost:{APP_PORT}

Debes ver este JSON:
```json
{
  "name": "mi-proyecto",
  "version": "1.0.0",
  "status": "running"
}
```

---

### 2. Documentación de la API
http://localhost:{APP_PORT}/docs/api

Aquí puedes ver **todos los endpoints** disponibles y probarlos directamente desde el navegador sin necesidad de Postman.

---

### 3. Base de datos (phpMyAdmin)
http://localhost:{PMA_PORT}

Ingresa con:
- **Usuario:** `root`
- **Contraseña:** el valor de `DB_ROOT_PASSWORD` en tu `.env`

---

## 🔐 Credenciales por defecto

Al correr los seeders se crea automáticamente este usuario:

| Campo | Valor |
|-------|-------|
| Email | `admin@admin.com` |
| Password | `Admin1234` |
| Rol | Administrador |

> ⚠️ **Importante:** Cambia estas credenciales antes de subir a producción.

---

## 📡 Endpoints de la API

**Base URL:** `http://localhost:{APP_PORT}/api`

### 🔓 Públicos — no requieren token

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `POST` | `/login` | Iniciar sesión — devuelve un token |

### 🔒 Protegidos — requieren token en el header

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `POST` | `/logout` | Cerrar sesión |
| `GET` | `/user` | Ver datos del usuario autenticado |

### 👑 Solo administrador

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/roles` | Listar todos los roles |
| `GET` | `/usuarios` | Listar todos los usuarios |
| `POST` | `/usuarios` | Crear un usuario nuevo |
| `GET` | `/usuarios/{id}` | Ver un usuario específico |
| `PUT` | `/usuarios/{id}` | Actualizar un usuario |
| `DELETE` | `/usuarios/{id}` | Eliminar un usuario |

---

## 🔑 Cómo autenticarse

### 1. Hacer login

Envía una petición `POST` con tus credenciales:
POST http://localhost:{APP_PORT}/api/login
Content-Type: application/json
{
"email": "admin@admin.com",
"password": "Admin1234"
}

Recibirás una respuesta como esta:

```json
{
  "user": {
    "id": 1,
    "name": "Administrador",
    "email": "admin@admin.com",
    "role": { "slug": "admin" }
  },
  "token": "1|abc123xyz..."
}
```

### 2. Usar el token

Copia el `token` de la respuesta y agrégalo en el **header** de todas tus peticiones protegidas:
Authorization: Bearer 1|abc123xyz...

> 💡 En Thunder Client (VS Code): ve a la pestaña **Auth → Bearer** y pega el token ahí.

---

## 🏗️ Cómo agregar un módulo nuevo

Este es el flujo estándar para construir cualquier módulo nuevo (clientes, productos, órdenes, etc.):

### 1. Crear el modelo y su migración juntos

```bash
docker exec {APP_NAME}_app php artisan make:model NombreModelo -m
```

> El flag `-m` crea automáticamente el archivo de migración junto con el modelo.

### 2. Definir las columnas de la tabla

Abre el archivo de migración en `database/migrations/` y agrega tus columnas:

```php
public function up(): void
{
    Schema::create('nombre_tabla', function (Blueprint $table) {
        $table->id();
        $table->string('nombre');
        $table->decimal('precio', 10, 2)->nullable();
        $table->boolean('activo')->default(true);
        $table->timestamps(); // created_at y updated_at automáticos
    });
}
```

### 3. Ejecutar la migración

```bash
docker exec {APP_NAME}_app php artisan migrate
```

### 4. Crear el controlador

```bash
docker exec {APP_NAME}_app php artisan make:controller Api/NombreModeloController
```

### 5. Agregar las rutas en `routes/api.php`

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('nombre-modulo', NombreModeloController::class);
});
```

Una sola línea `apiResource` crea automáticamente **5 endpoints**:

| Método | Endpoint | Método del Controller |
|--------|----------|-----------------------|
| `GET` | `/api/nombre-modulo` | `index()` — listar todos |
| `POST` | `/api/nombre-modulo` | `store()` — crear |
| `GET` | `/api/nombre-modulo/{id}` | `show()` — ver uno |
| `PUT` | `/api/nombre-modulo/{id}` | `update()` — actualizar |
| `DELETE` | `/api/nombre-modulo/{id}` | `destroy()` — eliminar |

### 6. Implementar la lógica en el controlador

```php
public function index(): JsonResponse
{
    $items = NombreModelo::all();
    return response()->json($items);
}

public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'nombre' => 'required|string|max:255',
    ]);

    $item = NombreModelo::create($validated);
    return response()->json($item, 201);
}
```

---

## 🐳 Comandos Docker de referencia

```bash
# ── Gestión de contenedores ──────────────────────────────
docker-compose up -d              # Levantar todos los contenedores
docker-compose down               # Apagar todos los contenedores
docker-compose down -v            # Apagar y eliminar volúmenes (borra la DB)
docker-compose ps                 # Ver estado de los contenedores
docker-compose logs -f app        # Ver logs en tiempo real

# ── Comandos Artisan ─────────────────────────────────────
docker exec {APP_NAME}_app php artisan migrate          # Correr migraciones
docker exec {APP_NAME}_app php artisan migrate --seed   # Migrar y sembrar datos
docker exec {APP_NAME}_app php artisan migrate:fresh --seed  # Borrar todo y volver a migrar
docker exec {APP_NAME}_app php artisan db:seed          # Solo correr seeders
docker exec {APP_NAME}_app php artisan route:list       # Ver todas las rutas registradas
docker exec {APP_NAME}_app php artisan make:model X -m  # Crear modelo + migración
docker exec {APP_NAME}_app php artisan make:controller Api/XController  # Crear controlador

# ── Limpiar cache ────────────────────────────────────────
docker exec {APP_NAME}_app php artisan config:clear
docker exec {APP_NAME}_app php artisan cache:clear
docker exec {APP_NAME}_app php artisan route:clear

# ── Acceder al contenedor ────────────────────────────────
docker exec -it {APP_NAME}_app bash
```

---

## 🔄 Reutilizar el template en un proyecto nuevo

```bash
# 1. Copiar el template con nuevo nombre
cp -r laravel-docker-template nombre-nuevo-proyecto
cd nombre-nuevo-proyecto

# 2. Crear y configurar el .env
cp .env.example .env
# Editar: APP_NAME, APP_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_ROOT_PASSWORD, PMA_PORT

# 3. Instalar dependencias
composer install

# 4. Generar key
php artisan key:generate

# 5. Levantar Docker
docker-compose up -d

# 6. Migrar y sembrar
docker exec {APP_NAME}_app php artisan migrate --seed

# 7. Verificar
# http://localhost:{APP_PORT}         → API corriendo
# http://localhost:{APP_PORT}/docs/api → Documentación
# http://localhost:{PMA_PORT}          → phpMyAdmin
```

---

## 📁 Estructura del proyecto
```
laravel-docker-template/
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/                  # Controladores de la API
│   │   │       ├── AuthController.php
│   │   │       └── UserController.php
│   │   └── Middleware/
│   │       └── CheckRole.php         # Validación de roles
│   ├── Models/
│   │   ├── User.php                  # Modelo de usuario
│   │   └── Role.php                  # Modelo de rol
│   └── Providers/
│       └── AppServiceProvider.php
│
├── config/                           # Configuración de Laravel
│   ├── cors.php                      # Orígenes permitidos
│   ├── sanctum.php                   # Config de autenticación
│   └── scramble.php                  # Config de documentación
│
├── database/
│   ├── migrations/                   # Estructura de la base de datos
│   └── seeders/
│       ├── DatabaseSeeder.php        # Orquestador de seeders
│       ├── RoleSeeder.php            # Crea el rol admin
│       └── UserSeeder.php            # Crea el usuario admin
│
├── docker/
│   └── php/
│       └── local.ini                 # Configuración de PHP
│
├── routes/
│   ├── api.php                       # Rutas de la API
│   └── web.php                       # Health check (GET /)
│
├── .env.example                      # Variables de entorno de ejemplo
├── .gitignore
├── docker-compose.yml                # Definición de contenedores
├── Dockerfile                        # Imagen PHP + Apache
└── README.md
```

---

## 🛠️ Stack tecnológico

| Tecnología | Versión | Función |
|------------|---------|---------|
| Laravel | 11 | Framework PHP para la API |
| PHP | 8.2+ | Lenguaje de programación |
| MySQL | 8.0 | Base de datos relacional |
| Apache | 2.4 | Servidor web |
| Laravel Sanctum | 4.0 | Autenticación por tokens |
| Scramble | Latest | Documentación automática de la API |
| Docker | - | Entorno de desarrollo containerizado |

---

## 📄 Licencia

MIT