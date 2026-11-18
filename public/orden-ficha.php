<?php

session_name("taller_mecanico_v2");
session_start();

if (!isset($_SESSION["empleado"])) {
    header("Location: index.html");
    exit;
}

$ordenId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$ordenId || $ordenId < 1) {
    http_response_code(400);
    exit("Número de orden inválido.");
}

require_once __DIR__ . "/../config/database.php";

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function mostrar($valor)
{
    return $valor === null || $valor === "" ? "—" : escapar($valor);
}

function fechaLegible($valor)
{
    if (!$valor) return "—";
    $marcaTiempo = strtotime($valor);
    return $marcaTiempo === false ? escapar($valor) : date("d/m/Y H:i", $marcaTiempo);
}

function moneda($valor)
{
    return "$ " . number_format((float) $valor, 2, ",", ".");
}

function rutaImagen($ruta, $absoluta = false)
{
    $ruta = ltrim(str_replace("\\", "/", (string) $ruta), "/");
    $segmentos = array_map("rawurlencode", explode("/", $ruta));
    $url = "../" . implode("/", $segmentos);
    if ($absoluta) {
        $protocolo = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off" ? "https" : "http";
        $host = $_SERVER["HTTP_HOST"] ?? "localhost";
        $url = $protocolo . "://" . $host . dirname($_SERVER["SCRIPT_NAME"]) . "/" . $url;
    }
    return $url;
}

$esWord = ($_GET["formato"] ?? "") === "word";

try {
    $pdo = database();
    $consulta = $pdo->prepare(
        "SELECT o.id, o.estado, o.motivo_ingreso, o.reparacion, o.observacion_extra,
                o.diagnostico, o.fecha_alta, o.fecha_finalizacion, o.presupuesto,
                e.nombre AS tecnico,
                v.patente, v.marca, v.modelo, v.version, v.tipo, v.anio, v.kilometraje,
                v.color, v.combustible, v.transmision, v.motor, v.numero_motor, v.vin,
                v.imagen_principal,
                c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.dni,
                c.telefono, c.email, c.direccion, c.ciudad, c.provincia
         FROM ordenes o
         JOIN vehiculos v ON v.id = o.vehiculo_id
         JOIN clientes c ON c.id = v.cliente_id
         LEFT JOIN empleados e ON e.id = o.empleado_id
         WHERE o.id = ? AND o.fecha_baja IS NULL"
    );
    $consulta->execute([$ordenId]);
    $orden = $consulta->fetch();

    if (!$orden) {
        http_response_code(404);
        exit("No se encontró la orden solicitada.");
    }

    $consultaItems = $pdo->prepare(
        "SELECT tipo, descripcion, cantidad, precio
         FROM presupuesto_items
         WHERE orden_id = ? AND fecha_baja IS NULL
         ORDER BY id"
    );
    $consultaItems->execute([$ordenId]);
    $items = $consultaItems->fetchAll();

    $consultaImagenes = $pdo->prepare(
        "SELECT nombre_original, ruta
         FROM revision_imagenes
         WHERE orden_id = ? AND fecha_baja IS NULL
         ORDER BY id"
    );
    $consultaImagenes->execute([$ordenId]);
    $imagenes = $consultaImagenes->fetchAll();
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit("No se pudo cargar la ficha técnica. Intente nuevamente más tarde.");
}

$subtotalItems = 0;
foreach ($items as $item) {
    $subtotalItems += (float) $item["cantidad"] * (float) $item["precio"];
}
$presupuestoTotal = $orden["presupuesto"] !== null
    ? (float) $orden["presupuesto"]
    : $subtotalItems;
$estados = [
    "ingresado" => "Ingresado",
    "en_reparacion" => "En reparación",
    "finalizado" => "Finalizado"
];
$estado = $estados[$orden["estado"]] ?? $orden["estado"];
$motivo = $orden["motivo_ingreso"] ?: $orden["diagnostico"];

if ($esWord) {
    header("Content-Type: application/msword; charset=utf-8");
    header('Content-Disposition: attachment; filename="ficha-orden-' . $ordenId . '.doc"');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ficha técnica - Orden #<?= escapar($ordenId) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #20242a; background: #f1f3f5; font: 14px Arial, sans-serif; }
        .toolbar { display: flex; justify-content: flex-end; gap: 10px; max-width: 980px; margin: 20px auto 12px; }
        .toolbar a, .toolbar button { padding: 10px 14px; border: 1px solid #25364a; border-radius: 4px; background: #25364a; color: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .sheet { max-width: 980px; margin: 0 auto 24px; padding: 36px; background: #fff; }
        .heading { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; padding-bottom: 18px; border-bottom: 2px solid #26384d; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        h2 { margin: 26px 0 12px; padding-bottom: 7px; border-bottom: 1px solid #cbd1d7; color: #26384d; font-size: 16px; }
        p { margin: 0; }
        .muted { color: #626b75; }
        .status { padding: 6px 10px; border: 1px solid #8994a0; border-radius: 16px; white-space: nowrap; }
        .vehicle { display: flex; align-items: flex-start; gap: 18px; }
        .vehicle-photo { width: 210px; max-height: 150px; object-fit: cover; border: 1px solid #d5d9de; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 18px; }
        .field { min-width: 0; }
        .field-label { display: block; margin-bottom: 3px; color: #626b75; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .field-value { overflow-wrap: anywhere; }
        .text-block { min-height: 24px; white-space: pre-wrap; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 8px; border: 1px solid #cbd1d7; text-align: left; }
        th { background: #f2f4f6; }
        .number { text-align: right; white-space: nowrap; }
        .total { margin-top: 12px; text-align: right; font-size: 18px; font-weight: bold; }
        .photos { display: flex; flex-wrap: wrap; gap: 12px; }
        .photos figure { width: 180px; margin: 0; page-break-inside: avoid; }
        .photos img { display: block; width: 100%; max-height: 150px; object-fit: contain; border: 1px solid #d5d9de; }
        .photos figcaption { margin-top: 4px; color: #626b75; font-size: 11px; }
        .empty { color: #626b75; font-style: italic; }
        @media (max-width: 650px) {
            .sheet { margin: 0; padding: 20px; }
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .vehicle { flex-direction: column; }
            .toolbar { margin: 12px; flex-wrap: wrap; }
        }
        @media print {
            @page { size: A4; margin: 14mm; }
            body { background: #fff; font-size: 11px; }
            .toolbar { display: none; }
            .sheet { max-width: none; margin: 0; padding: 0; }
            h2 { margin-top: 18px; }
            tr, figure, .vehicle { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <?php if (!$esWord): ?>
        <nav class="toolbar" aria-label="Opciones de ficha">
            <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
            <a href="orden-ficha.php?id=<?= escapar($ordenId) ?>&amp;formato=word">Descargar ficha para Word</a>
        </nav>
    <?php endif; ?>
    <main class="sheet">
        <header class="heading">
            <div>
                <h1>Ficha técnica de orden #<?= escapar($ordenId) ?></h1>
                <p class="muted">Taller Mecánico</p>
            </div>
            <span class="status">Estado: <?= escapar($estado) ?></span>
        </header>

        <section>
            <h2>Cliente</h2>
            <div class="grid">
                <div class="field"><span class="field-label">Nombre</span><div class="field-value"><?= mostrar(trim($orden["cliente_nombre"] . " " . $orden["cliente_apellido"])) ?></div></div>
                <div class="field"><span class="field-label">DNI</span><div class="field-value"><?= mostrar($orden["dni"]) ?></div></div>
                <div class="field"><span class="field-label">Teléfono</span><div class="field-value"><?= mostrar($orden["telefono"]) ?></div></div>
                <div class="field"><span class="field-label">Email</span><div class="field-value"><?= mostrar($orden["email"]) ?></div></div>
                <div class="field"><span class="field-label">Dirección</span><div class="field-value"><?= mostrar($orden["direccion"]) ?></div></div>
                <div class="field"><span class="field-label">Ciudad / Provincia</span><div class="field-value"><?= mostrar(trim(($orden["ciudad"] ?? "") . " " . ($orden["provincia"] ?? ""))) ?></div></div>
            </div>
        </section>

        <section>
            <h2>Vehículo</h2>
            <div class="vehicle">
                <?php if ($orden["imagen_principal"]): ?>
                    <img class="vehicle-photo" src="<?= escapar(rutaImagen($orden["imagen_principal"], $esWord)) ?>" alt="Imagen principal del vehículo">
                <?php endif; ?>
                <div class="grid" style="flex: 1">
                    <div class="field"><span class="field-label">Patente</span><div class="field-value"><?= mostrar($orden["patente"]) ?></div></div>
                    <div class="field"><span class="field-label">Marca</span><div class="field-value"><?= mostrar($orden["marca"]) ?></div></div>
                    <div class="field"><span class="field-label">Modelo</span><div class="field-value"><?= mostrar($orden["modelo"]) ?></div></div>
                    <div class="field"><span class="field-label">Año</span><div class="field-value"><?= mostrar($orden["anio"]) ?></div></div>
                    <div class="field"><span class="field-label">Kilometraje</span><div class="field-value"><?= $orden["kilometraje"] === null ? "—" : escapar(number_format((float) $orden["kilometraje"], 0, ",", ".") . " km") ?></div></div>
                    <div class="field"><span class="field-label">Versión</span><div class="field-value"><?= mostrar($orden["version"]) ?></div></div>
                    <div class="field"><span class="field-label">Tipo</span><div class="field-value"><?= mostrar($orden["tipo"]) ?></div></div>
                    <div class="field"><span class="field-label">Color</span><div class="field-value"><?= mostrar($orden["color"]) ?></div></div>
                    <div class="field"><span class="field-label">Combustible</span><div class="field-value"><?= mostrar($orden["combustible"]) ?></div></div>
                    <div class="field"><span class="field-label">Transmisión</span><div class="field-value"><?= mostrar($orden["transmision"]) ?></div></div>
                    <div class="field"><span class="field-label">Motor</span><div class="field-value"><?= mostrar($orden["motor"]) ?></div></div>
                    <div class="field"><span class="field-label">Número de motor</span><div class="field-value"><?= mostrar($orden["numero_motor"]) ?></div></div>
                    <div class="field"><span class="field-label">VIN</span><div class="field-value"><?= mostrar($orden["vin"]) ?></div></div>
                </div>
            </div>
        </section>

        <section>
            <h2>Orden de trabajo</h2>
            <div class="grid">
                <div class="field"><span class="field-label">Técnico asignado</span><div class="field-value"><?= mostrar($orden["tecnico"]) ?></div></div>
                <div class="field"><span class="field-label">Estado actual</span><div class="field-value"><?= escapar($estado) ?></div></div>
                <div class="field"><span class="field-label">Fecha de ingreso</span><div class="field-value"><?= fechaLegible($orden["fecha_alta"]) ?></div></div>
                <div class="field"><span class="field-label">Fecha de finalización</span><div class="field-value"><?= fechaLegible($orden["fecha_finalizacion"]) ?></div></div>
            </div>
            <h2>Motivo de ingreso</h2>
            <div class="text-block"><?= mostrar($motivo) ?></div>
            <h2>Reparación realizada</h2>
            <div class="text-block"><?= mostrar($orden["reparacion"]) ?></div>
            <h2>Observación</h2>
            <div class="text-block"><?= mostrar($orden["observacion_extra"]) ?></div>
            <?php if ($imagenes): ?>
                <h2>Imágenes de observación</h2>
                <div class="photos">
                    <?php foreach ($imagenes as $imagen): ?>
                        <figure>
                            <img src="<?= escapar(rutaImagen($imagen["ruta"], $esWord)) ?>" alt="<?= escapar($imagen["nombre_original"]) ?>">
                            <figcaption><?= escapar($imagen["nombre_original"]) ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section>
            <h2>Piezas y mano de obra</h2>
            <?php if ($items): ?>
                <table>
                    <thead><tr><th>Tipo</th><th>Descripción</th><th class="number">Cantidad</th><th class="number">Precio unitario</th><th class="number">Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $subtotal = (float) $item["cantidad"] * (float) $item["precio"]; ?>
                            <tr>
                                <td><?= $item["tipo"] === "pieza" ? "Pieza" : "Mano de obra" ?></td>
                                <td><?= escapar($item["descripcion"]) ?></td>
                                <td class="number"><?= escapar(number_format((float) $item["cantidad"], 2, ",", ".")) ?></td>
                                <td class="number"><?= escapar(moneda($item["precio"])) ?></td>
                                <td class="number"><?= escapar(moneda($subtotal)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty">Sin piezas ni mano de obra registrados.</p>
            <?php endif; ?>
            <p class="total">Presupuesto total: <?= escapar(moneda($presupuestoTotal)) ?></p>
        </section>
    </main>
</body>
</html>
