<?php
require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT); // ID del Course Module
$cm = get_coursemodule_from_id('visorpdf', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$visorpdf = $DB->get_record('visorpdf', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/visorpdf:view', $context);

// 1. Obtener el archivo de la tabla m_files
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_visorpdf', 'content', 0, 'itemid, filepath, filename', false);

if (empty($files)) {
    print_error('filenotfound', 'error');
}

$file = reset($files);
// Generar la URL protegida que pasará por visorpdf_pluginfile en lib.php
$fileurl = moodle_url::make_pluginfile_url($context->id, 'mod_visorpdf', 'content', 0, $file->get_filepath(), $file->get_filename());

$PAGE->set_url('/mod/visorpdf/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($visorpdf->name));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// 2. Interfaz del Visor con Protección
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
    #pdf-container { position: relative; background: #525659; overflow: auto; height: 80vh; user-select: none; }
    canvas { display: block; margin: 10px auto; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
    /* Capa de Marca de Agua */
    .watermark {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        pointer-events: none; z-index: 10; overflow: hidden;
        display: flex; flex-wrap: wrap; justify-content: space-around;
        opacity: 0.2; font-size: 20px; color: red; transform: rotate(-45deg);
    }
    /* Bloqueo de Impresión */
    @media print { body { display: none !important; } }
</style>

<div id="pdf-container" oncontextmenu="return false;">
    <div class="watermark">
        <?php for($i=0; $i<50; $i++) echo "<span>" . $USER->username . " - " . $USER->email . "</span> "; ?>
    </div>
    <div id="pdf-render"></div>
</div>

<script>
    const url = '<?php echo $fileurl; ?>';
    const pdfjsLib = window['pdfjs-dist/build/pdf'];
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    let pdfDoc = null;

    pdfjsLib.getDocument(url).promise.then(pdf => {
        pdfDoc = pdf;
        for (let i = 1; i <= pdf.numPages; i++) {
            renderPage(i);
        }
    });

    function renderPage(num) {
        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({ scale: 1.5 });
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            document.getElementById('pdf-render').appendChild(canvas);

            page.render({ canvasContext: ctx, viewport: viewport });
        });
    }

    // Bloqueos de teclado adicionales
    document.addEventListener('keydown', e => {
        if (e.ctrlKey && (e.key === 'p' || e.key === 's' || e.key === 'u')) {
            e.preventDefault();
            alert('Acción no permitida por seguridad.');
        }
    });
</script>

<?php
echo $OUTPUT->footer();
