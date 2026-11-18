<?php

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
