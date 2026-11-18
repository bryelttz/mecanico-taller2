<?php

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
