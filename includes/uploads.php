<?php

// Me costo hacer andar esto por el tema de los permisos de la carpeta public/images pero ahi quedo

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
    $directorio = dirname(__DIR__) . "/public/images";
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
    $directorio = dirname(__DIR__) . "/public/images";
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
