<?php

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
