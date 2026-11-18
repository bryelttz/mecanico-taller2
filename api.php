<?php

// session para manejar el login del taller, ojo con cambiar el nombre despues porque se pisa
session_name("taller_mecanico_v2");
session_start();
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/uploads.php";

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
    switch ($accion) {
        case "dashboard":
            require __DIR__ . "/controllers/dashboard.php";
            break;
        case "clientes_listar":
        case "clientes_crear":
        case "clientes_editar":
        case "clientes_eliminar":
            require __DIR__ . "/controllers/clientes.php";
            break;
        case "vehiculos_listar":
        case "vehiculos_crear":
        case "vehiculos_editar":
        case "vehiculos_eliminar":
        case "ficha_crear":
            require __DIR__ . "/controllers/vehiculos.php";
            break;
        case "ordenes_listar":
        case "vehiculo_revisiones":
        case "ordenes_crear":
        case "ordenes_editar":
        case "ordenes_eliminar":
            require __DIR__ . "/controllers/ordenes.php";
            break;
        case "empleados_listar":
        case "empleados_crear":
        case "empleados_editar":
        case "empleados_eliminar":
            require __DIR__ . "/controllers/empleados.php";
            break;
        default:
            errorRespuesta("Acción no válida", 404);
    }
} catch (PDOException $exception) {
    if (!empty($exception->errorInfo[1]) && $exception->errorInfo[1] == 1062) {
        errorRespuesta("Ya existe un registro con esos datos");
    }
    errorRespuesta("Error de base de datos", 500);
}
