<?php

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
