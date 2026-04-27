#!/bin/bash

echo "🐟 Iniciando configuración del Cotizador API..."

# Copiar .env si no existe
if [ ! -f .env ]; then
    cp .env.example .env
    echo "✅ Archivo .env creado"
fi

# Esperar a que MySQL esté listo
echo "⏳ Esperando a que MySQL esté listo..."
sleep 10

# Instalar dependencias
echo "📦 Instalando dependencias..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# Generar key
echo "🔑 Generando APP_KEY..."
php artisan key:generate --force

# Ejecutar migraciones
echo "🗄️ Ejecutando migraciones..."
php artisan migrate --force

# Ejecutar seeders
echo "🌱 Insertando datos iniciales..."
php artisan db:seed --force

# Limpiar cache
echo "🧹 Limpiando cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Permisos
echo "🔒 Configurando permisos..."
chmod -R 775 storage bootstrap/cache

echo "✅ Configuración completada!"
echo ""
echo "🌐 API disponible en: http://localhost:${APP_PORT:-8000}"
echo "🗄️ phpMyAdmin disponible en: http://localhost:${PMA_PORT:-8080}"
