<?php
defined('MOODLE_INTERNAL') || die();

class block_codesandbox extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_codesandbox');
    }

    public function applicable_formats() {
        return array('course-view' => true, 'mod-codesandbox-view' => true);
    }

    public function get_content() {
        global $PAGE, $DB, $USER;

        if ($this->content !== null) return $this->content;
        $this->content = new stdClass();

        $cmid = ($PAGE->cm && $PAGE->cm->modname === 'codesandbox') ? $PAGE->cm->id : 0;

        if (!$cmid) {
            $this->content->text = '<div class="small text-muted text-center">'.get_string('not_in_context', 'block_codesandbox').'</div>';
            return $this->content;
        }

        $note = $DB->get_record('block_codesandbox_notes', ['userid' => $USER->id, 'cmid' => $cmid]);
        $snap = $DB->get_record('block_codesandbox_snap', ['userid' => $USER->id, 'cmid' => $cmid]);
        $history = $DB->get_records('block_codesandbox_hist', ['userid' => $USER->id, 'cmid' => $cmid], 'timestamp DESC', '*', 0, 5);

        $js_params = [
            'typing_msg' => get_string('status_typing', 'block_codesandbox'),
            'saved_msg'  => get_string('status_saved', 'block_codesandbox', '')
        ];
        $PAGE->requires->js_call_amd('block_codesandbox/notes', 'init', [$js_params]);

        // Renderizado de la interfaz del bloque con snapshot, historial y scratchpad 
        $html = '<div class="block_codesandbox_container">';
        $html .= $this->render_snapshot($snap);
        $html .= $this->render_history($history);
        $html .= $this->render_scratchpad($note ? $note->note_text : '', $cmid);
        $html .= '</div>';

        $this->content->text = $html;
        return $this->content;
    }

    private function render_scratchpad($text, $cmid) {
        return '
        <div class="block-section border-top pt-2">
            <h6 class="small font-weight-bold">'.get_string('scratchpad_title', 'block_codesandbox').'</h6>
            <textarea id="sandbox-notes" class="form-control form-control-sm" 
                      style="font-size: 0.8rem; height: 120px; resize: none;" 
                      placeholder="'.get_string('scratchpad_placeholder', 'block_codesandbox').'"
                      data-cmid="'.$cmid.'">'.s($text).'</textarea>
            <div id="notes-status" class="text-muted mt-1" style="font-size: 0.65rem;">
                '.get_string('status_ready', 'block_codesandbox').'
            </div>
        </div>';
    }

    private function render_snapshot($snap) {
        $title = get_string('last_run_title', 'block_codesandbox');
        if (!$snap) {
            return '<div class="block-section mb-3 border-bottom pb-2">
                        <h6 class="small font-weight-bold">'.$title.'</h6>
                        <div class="text-muted small italic">'.get_string('no_data', 'block_codesandbox').'</div>
                    </div>';
        }

        $time = userdate($snap->timestamp, get_string('strftimetime', 'langconfig'));
        $status_class = ($snap->status == 'success') ? 'text-success' : 'text-danger';
        
        $status_label = get_string('status_'.$snap->status, 'block_codesandbox');
        return '
        <div class="block-section mb-3 p-2 bg-light border rounded shadow-sm">
            <h6 class="small font-weight-bold mb-1">'.$title.'</h6>
            <div class="d-flex justify-content-between mb-1" style="font-size: 0.7rem;">
                <span class="'.$status_class.' font-weight-bold">'.strtoupper($status_label).'</span>
                <span class="text-muted">'.$time.'</span>
            </div>
            <pre class="bg-dark text-white p-1 rounded mb-0" style="font-size: 0.65rem; max-height: 80px; overflow-y: auto; font-family: monospace;">'.s($snap->code_preview).'</pre>
        </div>';
    }

    private function render_history($history) {
        $html = '<div class="block-section mb-3">
                <h6 class="small font-weight-bold mb-2">'.get_string('history_title', 'block_codesandbox').'</h6>
                <div class="run-history-list" style="max-height: 150px; overflow-y: auto;">';
    
        if (!$history) {
            $html .= '<div class="text-muted small italic">'.get_string('no_history', 'block_codesandbox').'</div>';
        } else {
            foreach ($history as $h) {
                $is_ok = ($h->status == 'success');
                $icon = $is_ok ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
                $bg_color = $is_ok ? '#e6fffa' : '#fff5f5';
                $runtime_label = get_string('runtime_label', 'block_codesandbox');
                
                $html .= '
                <div class="d-flex align-items-center mb-1 p-1 rounded" style="background: '.$bg_color.'; font-size: 0.7rem; border: 1px solid rgba(0,0,0,0.05);">
                    <i class="fa '.$icon.' mr-2"></i>
                    <div class="flex-grow-1">
                        <span class="font-weight-bold">'.userdate($h->timestamp, get_string('strftimetime', 'langconfig')).'</span>
                    </div>
                    <div class="text-muted">'.($h->runtime_ms ?? 0).$runtime_label.'</div>
                </div>';
            }
        }

        $html .= '</div></div>';
        return $html;
    }
}