<?php

session_name("taller_mecanico_v2");
session_start();
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config/database.php";

function responder($datos, $status = 200)
{
    http_response_code($status);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function errorRespuesta($mensaje, $status = 400)
{
    responder(["error" => $mensaje], $status);
}

function usuarioActual()
{
    if (!isset($_SESSION["empleado"])) {
        errorRespuesta("Debe iniciar sesión", 401);
    }
    return $_SESSION["empleado"];
}

function auditoria($pdo, $usuario, $entidad, $entidadId, $accion)
{
    if ($accion == "crear") $accion = "alta";
    if ($accion == "editar") $accion = "modificacion";
    if ($accion == "eliminar") $accion = "baja";
    $consulta = $pdo->prepare(
        "INSERT INTO auditoria (empleado_id, entidad, entidad_id, accion, fecha)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $consulta->execute([$usuario["id"], $entidad, $entidadId, $accion]);
}

$pdo = database();
$accion = $_GET["action"] ?? "";
$datos = json_decode(file_get_contents("php://input"), true);
if (!is_array($datos)) $datos = [];
if (!empty($_POST)) $datos = array_merge($datos, $_POST);

function guardarImagenVehiculo($vehiculoId)
{
    if (empty($_FILES["imagen_vehiculo"]) || $_FILES["imagen_vehiculo"]["error"] == UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $archivo = $_FILES["imagen_vehiculo"];
    if ($archivo["error"] != UPLOAD_ERR_OK) errorRespuesta("No se pudo subir la imagen del vehículo");
    if ($archivo["size"] > 5 * 1024 * 1024) errorRespuesta("La imagen no puede superar los 5 MB");
    $tipos = ["image/jpeg" => "jpg", "image/png" => "png"];
    $tipo = mime_content_type($archivo["tmp_name"]);
    if (!isset($tipos[$tipo])) errorRespuesta("La imagen debe ser JPG, JPEG o PNG");
    $directorio = __DIR__ . "/public/images";
    if (!is_dir($directorio)) mkdir($directorio, 0775, true);
    $nombre = $vehiculoId . "_" . date("YmdHis") . "_" . basename($archivo["name"]);
    if (!move_uploaded_file($archivo["tmp_name"], $directorio . "/" . $nombre)) {
        errorRespuesta("No se pudo guardar la imagen del vehículo", 500);
    }
    return "public/images/" . $nombre;
}

function guardarImagenesObservacion($ordenId)
{
    if (empty($_FILES["observacion_imagenes"]) || empty($_FILES["observacion_imagenes"]["name"])) {
        return;
    }
    global $pdo, $usuario;
    $directorio = __DIR__ . "/public/images";
    if (!is_dir($directorio)) mkdir($directorio, 0775, true);
    foreach ($_FILES["observacion_imagenes"]["name"] as $indice => $nombreOriginal) {
        $error = $_FILES["observacion_imagenes"]["error"][$indice];
        if ($error == UPLOAD_ERR_NO_FILE) continue;
        if ($error != UPLOAD_ERR_OK) errorRespuesta("No se pudo subir una imagen de observación");
        $temporal = $_FILES["observacion_imagenes"]["tmp_name"][$indice];
        $tamano = $_FILES["observacion_imagenes"]["size"][$indice];
        if ($tamano > 5 * 1024 * 1024) errorRespuesta("Cada imagen no puede superar los 5 MB");
        $tipo = mime_content_type($temporal);
        if (!in_array($tipo, ["image/jpeg", "image/png"])) {
            errorRespuesta("Las imágenes deben ser JPG, JPEG o PNG");
        }
        $nombre = $ordenId . "_" . date("YmdHis") . "_" . basename($nombreOriginal);
        if (!move_uploaded_file($temporal, $directorio . "/" . $nombre)) {
            errorRespuesta("No se pudo guardar una imagen de observación", 500);
        }
        $consulta = $pdo->prepare(
            "INSERT INTO revision_imagenes
             (orden_id, nombre_archivo, nombre_original, ruta, tipo_mime, usuario_alta_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $consulta->execute([
            $ordenId, $nombre, basename($nombreOriginal),
            "public/images/" . $nombre, $tipo, $usuario["id"]
        ]);
    }
}

try {
    if ($accion == "register") {
        if (empty($datos["nombre"]) || empty($datos["email"]) || empty($datos["password"])) {
            errorRespuesta("Complete nombre, email y contraseña");
        }
        $password = password_hash($datos["password"], PASSWORD_DEFAULT);
        $consulta = $pdo->prepare(
            "INSERT INTO empleados (nombre, email, password_hash, rol, usuario_alta_id)
             VALUES (?, ?, ?, ?, NULL)"
        );
        $consulta->execute([$datos["nombre"], strtolower(trim($datos["email"])), $password, "empleado"]);
        $empleadoId = (int) $pdo->lastInsertId();
        $consulta = $pdo->prepare("UPDATE empleados SET usuario_alta_id=? WHERE id=?");
        $consulta->execute([$empleadoId, $empleadoId]);
        auditoria($pdo, ["id" => $empleadoId], "empleados", $empleadoId, "alta");
        $_SESSION["empleado"] = ["id" => $empleadoId, "nombre" => $datos["nombre"], "rol" => "empleado"];
        responder(["ok" => true]);
    }

    if ($accion == "login") {
        $consulta = $pdo->prepare(
            "SELECT id, nombre, email, password_hash, rol
             FROM empleados WHERE email=? AND activo=1 AND fecha_baja IS NULL"
        );
        $consulta->execute([strtolower(trim($datos["email"] ?? ""))]);
        $empleado = $consulta->fetch();
        if (!$empleado || !password_verify($datos["password"] ?? "", $empleado["password_hash"])) {
            errorRespuesta("Email o contraseña incorrectos", 401);
        }
        $_SESSION["empleado"] = [
            "id" => (int) $empleado["id"],
            "nombre" => $empleado["nombre"],
            "rol" => $empleado["rol"]
        ];
        responder(["ok" => true]);
    }

    if ($accion == "logout") {
        session_destroy();
        responder(["ok" => true]);
    }

    if ($accion == "session") {
        responder(["user" => usuarioActual()]);
    }

    $usuario = usuarioActual();

    if ($accion == "dashboard") {
        $clientes = $pdo->query("SELECT COUNT(*) FROM clientes WHERE fecha_baja IS NULL")->fetchColumn();
        $vehiculos = $pdo->query("SELECT COUNT(*) FROM vehiculos WHERE fecha_baja IS NULL")->fetchColumn();
        $pendientes = $pdo->query(
            "SELECT COUNT(*) FROM ordenes WHERE fecha_baja IS NULL AND estado<> 'finalizado'"
        )->fetchColumn();
        $finalizadas = $pdo->query(
            "SELECT COUNT(*) FROM ordenes WHERE fecha_baja IS NULL AND estado='finalizado'"
        )->fetchColumn();
        $ordenes = $pdo->query(
            "SELECT o.id, o.estado, o.fecha_alta, o.fecha_edicion, o.fecha_finalizacion,
                    v.patente, v.marca, v.modelo,
                    CONCAT(c.nombre, ' ', c.apellido) AS cliente
             FROM ordenes o
             JOIN vehiculos v ON v.id=o.vehiculo_id
             JOIN clientes c ON c.id=v.cliente_id
             WHERE o.fecha_baja IS NULL
             ORDER BY o.fecha_alta DESC LIMIT 8"
        )->fetchAll();
        $actividad = $pdo->query(
            "SELECT a.id, a.entidad, a.entidad_id, a.accion, a.fecha, e.nombre AS empleado
             FROM auditoria a
             LEFT JOIN empleados e ON e.id=a.empleado_id
             ORDER BY a.fecha DESC LIMIT 12"
        )->fetchAll();
        responder([
            "clientes" => (int) $clientes,
            "vehiculos" => (int) $vehiculos,
            "pendientes" => (int) $pendientes,
            "finalizadas" => (int) $finalizadas,
            "ordenes" => $ordenes,
            "actividad" => $actividad
        ]);
    }

    if ($accion == "clientes_listar") {
        $consulta = $pdo->query(
            "SELECT * FROM clientes WHERE fecha_baja IS NULL ORDER BY nombre, apellido"
        );
        responder($consulta->fetchAll());
    }

    if ($accion == "clientes_crear") {
        if (empty($datos["nombre"]) || empty($datos["apellido"]) || empty($datos["dni"])) {
            errorRespuesta("Nombre, apellido y DNI son obligatorios");
        }
        $consulta = $pdo->prepare(
            "INSERT INTO clientes
             (nombre, apellido, dni, telefono, email, direccion, ciudad, provincia, usuario_alta_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $consulta->execute([
            $datos["nombre"], $datos["apellido"], $datos["dni"],
            $datos["telefono"] ?? null, $datos["email"] ?? null,
            $datos["direccion"] ?? null, $datos["ciudad"] ?? null,
            $datos["provincia"] ?? null, $usuario["id"]
        ]);
        auditoria($pdo, $usuario, "clientes", (int) $pdo->lastInsertId(), "alta");
        responder(["ok" => true]);
    }

    if ($accion == "clientes_editar") {
        $consulta = $pdo->prepare(
            "UPDATE clientes SET nombre=?, apellido=?, dni=?, telefono=?, email=?,
             direccion=?, ciudad=?, provincia=?, fecha_edicion=NOW(), usuario_edicion_id=?
             WHERE id=? AND fecha_baja IS NULL"
        );
        $consulta->execute([
            $datos["nombre"], $datos["apellido"], $datos["dni"],
            $datos["telefono"] ?? null, $datos["email"] ?? null,
            $datos["direccion"] ?? null, $datos["ciudad"] ?? null,
            $datos["provincia"] ?? null, $usuario["id"], $datos["id"]
        ]);
        auditoria($pdo, $usuario, "clientes", (int) $datos["id"], "modificacion");
        responder(["ok" => true]);
    }

    if ($accion == "clientes_eliminar") {
        $consulta = $pdo->prepare(
            "UPDATE clientes SET fecha_baja=NOW(), usuario_baja_id=? WHERE id=?"
        );
        $consulta->execute([$usuario["id"], (int) $datos["id"]]);
        auditoria($pdo, $usuario, "clientes", (int) $datos["id"], "baja");
        responder(["ok" => true]);
    }

    if ($accion == "vehiculos_listar") {
        $consulta = $pdo->query(
            "SELECT v.*, CONCAT(c.nombre, ' ', c.apellido) AS cliente,
                    (SELECT o.estado FROM ordenes o
                     WHERE o.vehiculo_id=v.id AND o.fecha_baja IS NULL
                     ORDER BY o.fecha_alta DESC LIMIT 1) AS estado
             FROM vehiculos v
             JOIN clientes c ON c.id=v.cliente_id
             WHERE v.fecha_baja IS NULL
             ORDER BY v.fecha_alta DESC"
        );
        responder($consulta->fetchAll());
    }

    if ($accion == "vehiculos_crear") {
        errorRespuesta("Los vehículos se crean desde la nueva ficha técnica", 400);
    }

    if ($accion == "vehiculos_editar") {
        $vehiculoIdEditar = (int) $datos["id"];
        $imagenVehiculoEditar = guardarImagenVehiculo($vehiculoIdEditar);
        if (!empty($datos["eliminar_imagen"])) {
            $imagenVehiculoEditar = null;
        }
        $marcarEliminarImagen = !empty($datos["eliminar_imagen"]) ? "__remove__" : "";
        $consulta = $pdo->prepare(
            "UPDATE vehiculos SET cliente_id=?, patente=?, marca=?, modelo=?, version=?,
             tipo=?, anio=?, kilometraje=?, color=?, combustible=?, transmision=?,
             motor=?, numero_motor=?, vin=?,
             imagen_principal=CASE WHEN ?='__remove__' THEN NULL ELSE COALESCE(?, imagen_principal) END,
             fecha_edicion=NOW(), usuario_edicion_id=?
             WHERE id=? AND fecha_baja IS NULL"
        );
        $consulta->execute([
            $datos["cliente_id"], strtoupper($datos["patente"]), $datos["marca"], $datos["modelo"],
            $datos["version"] ?? null, $datos["tipo"] ?? null, $datos["anio"] ?: null,
            $datos["kilometraje"] ?: null, $datos["color"] ?? null,
            $datos["combustible"] ?? null, $datos["transmision"] ?? null,
            $datos["motor"] ?? null, $datos["numero_motor"] ?? null, $datos["vin"] ?? null,
            $marcarEliminarImagen,
            $imagenVehiculoEditar,
            $usuario["id"], $datos["id"]
        ]);
        auditoria($pdo, $usuario, "vehiculos", (int) $datos["id"], "modificacion");
        responder(["ok" => true]);
    }

    if ($accion == "vehiculos_eliminar") {
        $consulta = $pdo->prepare(
            "UPDATE vehiculos SET fecha_baja=NOW(), usuario_baja_id=? WHERE id=?"
        );
        $consulta->execute([$usuario["id"], (int) $datos["id"]]);
        auditoria($pdo, $usuario, "vehiculos", (int) $datos["id"], "baja");
        responder(["ok" => true]);
    }

    if ($accion == "ficha_crear") {
        $clienteId = (int) ($datos["cliente_id"] ?? 0);
        $vehiculoId = (int) ($datos["vehiculo_id"] ?? 0);
        if ($clienteId < 1) errorRespuesta("Seleccione un cliente");

        if ($vehiculoId > 0) {
            $consulta = $pdo->prepare(
                "UPDATE vehiculos SET cliente_id=?, patente=?, marca=?, modelo=?, version=?,
                 tipo=?, anio=?, kilometraje=?, color=?, combustible=?, transmision=?,
                 motor=?, numero_motor=?, vin=?,
                 fecha_edicion=NOW(), usuario_edicion_id=?
                 WHERE id=? AND fecha_baja IS NULL"
            );
            $consulta->execute([
                $clienteId, strtoupper($datos["patente"]), $datos["marca"], $datos["modelo"],
                $datos["version"] ?? null, $datos["tipo"] ?? null, $datos["anio"] ?: null,
                $datos["kilometraje"] ?: null, $datos["color"] ?? null,
                $datos["combustible"] ?? null, $datos["transmision"] ?? null,
                $datos["motor"] ?? null, $datos["numero_motor"] ?? null, $datos["vin"] ?? null,
                $usuario["id"], $vehiculoId
            ]);
        } else {
            if (empty($datos["patente"]) || empty($datos["marca"]) || empty($datos["modelo"])) {
                errorRespuesta("Patente, marca y modelo son obligatorios");
            }
            $consulta = $pdo->prepare(
                "INSERT INTO vehiculos
                 (cliente_id, patente, marca, modelo, version, tipo, anio, kilometraje,
                  color, combustible, transmision, motor, numero_motor, vin, usuario_alta_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $consulta->execute([
                $clienteId, strtoupper($datos["patente"]), $datos["marca"], $datos["modelo"],
                $datos["version"] ?? null, $datos["tipo"] ?? null, $datos["anio"] ?: null,
                $datos["kilometraje"] ?: null, $datos["color"] ?? null,
                $datos["combustible"] ?? null, $datos["transmision"] ?? null,
                $datos["motor"] ?? null, $datos["numero_motor"] ?? null, $datos["vin"] ?? null,
                $usuario["id"]
            ]);
            $vehiculoId = (int) $pdo->lastInsertId();
        }

        $imagenVehiculo = guardarImagenVehiculo($vehiculoId);
        if ($imagenVehiculo) {
            $consultaImagen = $pdo->prepare(
                "UPDATE vehiculos SET imagen_principal=? WHERE id=?"
            );
            $consultaImagen->execute([$imagenVehiculo, $vehiculoId]);
        }

        $items = $datos["presupuesto_items"];
        if (is_string($items)) $items = json_decode($items, true);
        if (!is_array($items)) $items = [];
        $total = 0;
        foreach ($items as $item) {
            $cantidad = (float) ($item["cantidad"] ?: 1);
            $precio = (float) ($item["precio"] ?: 0);
            $total += $cantidad * $precio;
        }

        $consulta = $pdo->prepare(
            "INSERT INTO ordenes
             (vehiculo_id, empleado_id, diagnostico, motivo_ingreso, reparacion, observacion_extra,
              estado, presupuesto, fecha_finalizacion, usuario_alta_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ?='finalizado' THEN NOW() ELSE NULL END, ?)"
        );
        $consulta->execute([
            $vehiculoId, $datos["empleado_id"] ?: null,
            $datos["motivo_ingreso"] ?? null,
            $datos["motivo_ingreso"] ?? null,
            $datos["reparacion"] ?? null,
            $datos["observaciones"] ?? null, $datos["estado"] ?? "ingresado",
            $total > 0 ? $total : null, $datos["estado"] ?? "ingresado", $usuario["id"]
        ]);
        $ordenId = (int) $pdo->lastInsertId();
        guardarImagenesObservacion($ordenId);

        $consultaItem = $pdo->prepare(
            "INSERT INTO presupuesto_items
             (orden_id, tipo, descripcion, cantidad, precio, usuario_alta_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($items as $item) {
            $descripcion = trim($item["descripcion"] ?? "");
            $precio = (float) ($item["precio"] ?? 0);
            if ($descripcion === "") continue;
            $consultaItem->execute([
                $ordenId, $item["tipo"] ?? "pieza", $descripcion,
                (float) ($item["cantidad"] ?: 1), $precio, $usuario["id"]
            ]);
        }

        auditoria($pdo, $usuario, "vehiculos", $vehiculoId, "modificacion");
        auditoria($pdo, $usuario, "ordenes", $ordenId, "alta");
        responder(["ok" => true, "orden_id" => $ordenId]);
    }

    if ($accion == "ordenes_listar") {
        $consulta = $pdo->query(
            "SELECT o.*, v.patente, v.marca, v.modelo,
                    CONCAT(c.nombre, ' ', c.apellido) AS cliente,
                    e.nombre AS tecnico
             FROM ordenes o
             JOIN vehiculos v ON v.id=o.vehiculo_id
             JOIN clientes c ON c.id=v.cliente_id
             LEFT JOIN empleados e ON e.id=o.empleado_id
             WHERE o.fecha_baja IS NULL
             ORDER BY o.fecha_alta DESC"
        );
        responder($consulta->fetchAll());
    }

    if ($accion == "vehiculo_revisiones") {
        $vehiculoId = (int) ($_GET["vehiculo_id"] ?? 0);
        $consulta = $pdo->prepare(
            "SELECT o.*, e.nombre AS tecnico
             FROM ordenes o
             LEFT JOIN empleados e ON e.id=o.empleado_id
             WHERE o.vehiculo_id=? AND o.fecha_baja IS NULL
             ORDER BY o.fecha_alta DESC"
        );
        $consulta->execute([$vehiculoId]);
        $revisiones = $consulta->fetchAll();
        foreach ($revisiones as &$revision) {
            $consultaItems = $pdo->prepare(
                "SELECT tipo, descripcion, cantidad, precio
                 FROM presupuesto_items
                 WHERE orden_id=? AND fecha_baja IS NULL
                 ORDER BY id"
            );
            $consultaItems->execute([(int) $revision["id"]]);
            $revision["items"] = $consultaItems->fetchAll();
            $consultaImagenes = $pdo->prepare(
                "SELECT id, nombre_original, ruta FROM revision_imagenes
                 WHERE orden_id=? AND fecha_baja IS NULL ORDER BY id"
            );
            $consultaImagenes->execute([(int) $revision["id"]]);
            $revision["imagenes"] = $consultaImagenes->fetchAll();
        }
        responder($revisiones);
    }

    if ($accion == "ordenes_crear") {
        if (empty($datos["vehiculo_id"]) || empty($datos["diagnostico"])) {
            errorRespuesta("Vehículo y diagnóstico son obligatorios");
        }
        $consulta = $pdo->prepare(
            "INSERT INTO ordenes
             (vehiculo_id, empleado_id, diagnostico, estado, presupuesto, usuario_alta_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $consulta->execute([
            $datos["vehiculo_id"], $datos["empleado_id"] ?: null,
            $datos["diagnostico"], $datos["estado"] ?? "ingresado",
            $datos["presupuesto"] ?: null, $usuario["id"]
        ]);
        auditoria($pdo, $usuario, "ordenes", (int) $pdo->lastInsertId(), "alta");
        responder(["ok" => true]);
    }

    if ($accion == "ordenes_editar") {
        $consulta = $pdo->prepare(
            "UPDATE ordenes SET empleado_id=?, motivo_ingreso=?, reparacion=?, observacion_extra=?, estado=?, presupuesto=?,
             fecha_edicion=NOW(), usuario_edicion_id=?,
             fecha_finalizacion=CASE WHEN ?='finalizado' THEN COALESCE(fecha_finalizacion, NOW()) ELSE NULL END
             WHERE id=? AND fecha_baja IS NULL"
        );
        $items = $datos["presupuesto_items"];
        if (is_string($items)) $items = json_decode($items, true);
        if (!is_array($items)) $items = [];
        $total = 0;
        foreach ($items as $item) {
            $cantidad = (float) ($item["cantidad"] ?: 1);
            $precio = (float) ($item["precio"] ?: 0);
            $total += $cantidad * $precio;
        }
        $consulta->execute([
            $datos["empleado_id"] ?: null,
            $datos["motivo_ingreso"] ?? null,
            $datos["reparacion"] ?? null,
            $datos["observacion_extra"] ?? null,
            $datos["estado"],
            $total > 0 ? $total : null,
            $usuario["id"],
            $datos["estado"],
            $datos["id"]
        ]);
        guardarImagenesObservacion((int) $datos["id"]);
        if (!empty($datos["eliminar_observacion_imagenes"])) {
            $ids = is_array($datos["eliminar_observacion_imagenes"])
                ? $datos["eliminar_observacion_imagenes"]
                : [$datos["eliminar_observacion_imagenes"]];
            $eliminarImagen = $pdo->prepare(
                "UPDATE revision_imagenes SET fecha_baja=NOW(), usuario_baja_id=?
                 WHERE id=? AND orden_id=?"
            );
            foreach ($ids as $imagenId) {
                $eliminarImagen->execute([$usuario["id"], (int) $imagenId, (int) $datos["id"]]);
            }
        }

        $consulta = $pdo->prepare(
            "UPDATE presupuesto_items SET fecha_baja=NOW()
             WHERE orden_id=? AND fecha_baja IS NULL"
        );
        $consulta->execute([(int) $datos["id"]]);

        $consultaItem = $pdo->prepare(
            "INSERT INTO presupuesto_items
             (orden_id, tipo, descripcion, cantidad, precio, usuario_alta_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($items as $item) {
            $descripcion = trim($item["descripcion"] ?? "");
            if ($descripcion == "") continue;
            $consultaItem->execute([
                (int) $datos["id"],
                $item["tipo"] ?? "pieza",
                $descripcion,
                (float) ($item["cantidad"] ?: 1),
                (float) ($item["precio"] ?: 0),
                $usuario["id"]
            ]);
        }
        auditoria($pdo, $usuario, "ordenes", (int) $datos["id"], "modificacion");
        responder(["ok" => true]);
    }

    if ($accion == "ordenes_eliminar") {
        $consulta = $pdo->prepare(
            "UPDATE ordenes SET fecha_baja=NOW(), usuario_baja_id=? WHERE id=?"
        );
        $consulta->execute([$usuario["id"], (int) $datos["id"]]);
        auditoria($pdo, $usuario, "ordenes", (int) $datos["id"], "baja");
        responder(["ok" => true]);
    }

    if ($accion == "empleados_listar") {
        $consulta = $pdo->query(
            "SELECT id, nombre, email, rol, activo, fecha_alta
             FROM empleados WHERE fecha_baja IS NULL ORDER BY nombre"
        );
        responder($consulta->fetchAll());
    }

    if ($accion == "empleados_crear") {
        if (empty($datos["nombre"]) || empty($datos["email"]) || empty($datos["password"])) {
            errorRespuesta("Nombre, email y contraseña son obligatorios");
        }
        $consulta = $pdo->prepare(
            "INSERT INTO empleados
             (nombre, email, password_hash, rol, usuario_alta_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        $consulta->execute([
            $datos["nombre"], strtolower(trim($datos["email"])),
            password_hash($datos["password"], PASSWORD_DEFAULT),
            $datos["rol"] ?? "empleado", $usuario["id"]
        ]);
        auditoria($pdo, $usuario, "empleados", (int) $pdo->lastInsertId(), "alta");
        responder(["ok" => true]);
    }

    if ($accion == "empleados_editar") {
        $consulta = $pdo->prepare(
            "UPDATE empleados SET nombre=?, email=?, rol=?, fecha_edicion=NOW(), usuario_edicion_id=?
             WHERE id=? AND fecha_baja IS NULL"
        );
        $consulta->execute([
            $datos["nombre"], strtolower(trim($datos["email"])),
            $datos["rol"], $usuario["id"], $datos["id"]
        ]);
        auditoria($pdo, $usuario, "empleados", (int) $datos["id"], "modificacion");
        responder(["ok" => true]);
    }

    if ($accion == "empleados_eliminar") {
        $consulta = $pdo->prepare(
            "UPDATE empleados SET fecha_baja=NOW(), usuario_baja_id=?, activo=0 WHERE id=?"
        );
        $consulta->execute([$usuario["id"], (int) $datos["id"]]);
        auditoria($pdo, $usuario, "empleados", (int) $datos["id"], "baja");
        responder(["ok" => true]);
    }

    errorRespuesta("Acción no válida", 404);
} catch (PDOException $exception) {
    if (!empty($exception->errorInfo[1]) && $exception->errorInfo[1] == 1062) {
        errorRespuesta("Ya existe un registro con esos datos");
    }
    errorRespuesta("Error de base de datos", 500);
}
