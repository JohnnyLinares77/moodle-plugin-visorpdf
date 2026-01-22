<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Soporte de características del módulo.
 */
function visorpdf_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false; // MVP sin backup específico.
        default:
            return null;
    }
}

/**
 * Extrae el fileId desde una URL de Drive o devuelve el mismo string si ya es un ID.
 *
 * Ejemplos aceptados:
 * - https://drive.google.com/file/d/1AbCdEfGhIjKl/view?usp=sharing
 * - https://drive.google.com/open?id=1AbCdEfGhIjKl
 * - 1AbCdEfGhIjKl (solo ID)
 */
function visorpdf_extract_fileid(string $input): string {
    $input = trim($input);

    // Si parece una URL.
    if (preg_match('#^https?://#i', $input)) {
        // Patrón /file/d/FILEID/
        if (preg_match('#/file/d/([^/]+)#', $input, $matches)) {
            return $matches[1];
        }

        // Patrón ?id=FILEID
        if (preg_match('#[?&]id=([^&]+)#', $input, $matches)) {
            return $matches[1];
        }
    }

    // Por defecto devolvemos el string tal cual (asumimos que ya es un ID).
    return $input;
}

/**
 * Crea una instancia nueva de visorpdf.
 */
function visorpdf_add_instance($data) {
    global $DB;
    $data->timemodified = time();
    $id = $DB->insert_record('visorpdf', $data);
    $data->id = $id;

    // Guardar el alias del archivo
    $context = context_module::instance($data->coursemodule);
    file_save_draft_area_files($data->drivefile, $context->id, 'mod_visorpdf', 'content', 0);

    return $id;
}

/**
 * Actualiza una instancia existente.
 */
function visorpdf_update_instance($data) {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    $DB->update_record('visorpdf', $data);

    // Actualizar el archivo
    $context = context_module::instance($data->coursemodule);
    file_save_draft_area_files($data->drivefile, $context->id, 'mod_visorpdf', 'content', 0);

    return true;
}

/**
 * Elimina una instancia.
 */
function visorpdf_delete_instance($id) {
    global $DB;

    if (!$visorpdf = $DB->get_record('visorpdf', ['id' => $id])) {
        return false;
    }

    // No tenemos archivos físicos guardados, solo eliminamos el registro.
    $DB->delete_records('visorpdf', ['id' => $visorpdf->id]);

    return true;
}

/**
 * Serves the files from the visorpdf file areas.
 *
 * @param stdClass $course The course object
 * @param stdClass $cm The course module object
 * @param context $context The context object
 * @param string $filearea The file area
 * @param array $args The arguments for the file
 * @param bool $forcedownload Whether the file should be forced download
 * @param array $options Additional options
 * @return bool False if file not found, does not return if found - just checks permissions and sends it
 */
function visorpdf_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    // El área debe ser 'content', que es el estándar
    if ($filearea !== 'content') {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';

    // Esta función busca automáticamente en la tabla de archivos correcta (m_files en tu caso)
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_visorpdf', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
