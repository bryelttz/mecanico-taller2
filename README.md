# Taller Mecánico V2

Proyecto nuevo creado desde cero, basado en PHP sencillo y una interfaz HTML/CSS/JavaScript.

## Requisitos

- PHP 8
- MySQL/MariaDB
- Apache o servidor PHP

## Instalación

1. Importar `database/schema.sql`.
2. Ejecutar `php -S localhost:8001` dentro de esta carpeta.
3. Abrir `http://localhost:8001/public/index.html`.

## Funciones

- Registro e inicio de sesión de empleados.
- CRUD de clientes.
- CRUD de vehículos y ficha técnica.
- CRUD de órdenes de trabajo.
- Ficha técnica imprimible por orden, con opción de guardar como PDF o descargarla para Word.
- Estado ingresado, en reparación o finalizado.
- Presupuesto y técnico asignado.
- Soft delete y trazabilidad básica.
- Búsqueda y filtros de clientes y vehículos.
- Ficha técnica con revisiones, observación, presupuesto y trazabilidad.

En **Órdenes**, usar el ícono de impresora para abrir la ficha completa. Desde allí se puede imprimir o guardar como PDF y descargar una copia compatible con Microsoft Word.

## Uso de inteligencia artificial

El diseño visual actualizado de `public/css/styles.css` fue realizado con asistencia de inteligencia artificial.
