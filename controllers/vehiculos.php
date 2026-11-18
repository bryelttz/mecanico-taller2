<?php

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
