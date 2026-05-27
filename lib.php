<?php
// This file is part of Moodle - http://moodle.org/

use local_spotaward\local\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Require the plugin stylesheet with a cache-busting version.
 *
 * @return void
 */
function local_spotaward_require_stylesheet(): void {
    global $PAGE;

    $stylepath = __DIR__ . '/styles.css';
    $styleurl = new moodle_url('/local/spotaward/styles.css', [
        'v' => is_readable($stylepath) ? filemtime($stylepath) : time(),
    ]);
    $PAGE->requires->css($styleurl);
}

/**
 * Require the immediate success overlay shown while actions are processing.
 *
 * @return void
 */
function local_spotaward_require_action_success_overlay(): void {
    global $PAGE;

    static $required = false;
    if ($required) {
        return;
    }

    $title = get_string('successfullycompleted', 'local_spotaward');
    $subtitle = get_string('redirecting', 'local_spotaward');
    $countdowntemplate = get_string('closinginseconds', 'local_spotaward', '__SECONDS__');
    $goback = get_string('gobacknow', 'local_spotaward');
    $cancel = get_string('cancel');
    $close = get_string('close', 'local_spotaward');

    $PAGE->requires->js_init_code(
        '(function(){' .
        'if(window.localSpotawardSuccessOverlayReady){return;}window.localSpotawardSuccessOverlayReady=true;' .
        'var css=[' .
        '"body.spotaward-success-busy{overflow:hidden;}",' .
        '".spotaward-success-overlay{position:fixed;inset:0;background:rgba(0,0,0,.58);display:none;align-items:center;justify-content:center;padding:20px;z-index:99999;}",' .
        '".spotaward-success-overlay.is-open{display:flex;}",' .
        '".spotaward-success-modal{width:min(420px,100%);background:#fff;border-radius:22px;padding:20px 20px 18px;box-shadow:0 24px 64px rgba(0,0,0,.24);animation:spotawardSuccessPop .32s cubic-bezier(.2,.8,.2,1) forwards;}",' .
        '".spotaward-success-main{display:flex;align-items:center;gap:18px;text-align:left;}",' .
        '".spotaward-success-spinner{width:92px;height:92px;flex:0 0 92px;}",' .
        '".spotaward-success-content{flex:1;min-width:0;}",' .
        '".spin-wrap{width:92px;height:92px;}",' .
        '".spin-svg{width:92px;height:92px;animation:spotawardSuccessSpin .9s linear infinite;}",' .
        '".spin-svg.stopped{animation:none;}",' .
        '".ring-track{fill:none;stroke:#e4e4e4;stroke-width:7;}",' .
        '".ring-arc{fill:none;stroke:#1D9E75;stroke-width:7;stroke-linecap:round;stroke-dasharray:85 226;transition:stroke .3s,stroke-dasharray .45s ease,stroke-linecap .1s;}",' .
        '".ring-arc.full-green{stroke:#1D9E75;stroke-dasharray:400 0;stroke-linecap:butt;}",' .
        '".tick-path{fill:none;stroke:#1D9E75;stroke-width:7;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:62;stroke-dashoffset:62;transition:stroke-dashoffset .45s ease .2s;}",' .
        '".tick-path.drawn{stroke-dashoffset:0;}",' .
        '".spotaward-success-title{margin:0;font-size:22px;font-weight:700;color:#10212b;transition:color .25s ease;}",' .
        '".spotaward-success-title.is-done{color:#1D9E75;}",' .
        '".spotaward-success-subtitle{margin:6px 0 0;font-size:15px;color:#6c7b88;font-weight:500;}",' .
        '".spotaward-success-count-label{margin-top:14px;color:#5f6e7a;font-size:13px;}",' .
        '".spotaward-success-count-value{margin-top:4px;font-size:30px;line-height:1;font-weight:700;color:#1D9E75;}",' .
        '".spotaward-success-close{position:absolute;top:10px;right:10px;width:34px;height:34px;border:0;border-radius:999px;background:#f4f6f8;color:#51606d;font-size:20px;line-height:1;cursor:pointer;}",' .
        '".spotaward-success-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;}",' .
        '".spotaward-success-actions .btn{min-width:90px;}",' .
        '"@keyframes spotawardSuccessSpin{to{transform:rotate(360deg);}}",' .
        '"@keyframes spotawardSuccessPop{from{opacity:0;transform:scale(.92) translateY(14px);}to{opacity:1;transform:scale(1) translateY(0);}}"' .
        '].join("");' .
        'var style=document.createElement("style");style.textContent=css;document.head.appendChild(style);' .
        'var overlay=document.createElement("div");overlay.className="spotaward-success-overlay";' .
        'overlay.innerHTML=' . json_encode(
            '<div class="spotaward-success-modal" role="dialog" aria-modal="true" aria-labelledby="spotaward-success-inline-title">' .
                '<button type="button" class="spotaward-success-close" data-spotaward-success-close="1" aria-label="' . s($close) . '">&times;</button>' .
                '<div class="spotaward-success-main">' .
                    '<div class="spotaward-success-spinner">' .
                        '<div class="spin-wrap">' .
                            '<svg id="spotaward-inline-spin" class="spin-svg" viewBox="0 0 88 88" xmlns="http://www.w3.org/2000/svg">' .
                                '<circle class="ring-track" cx="44" cy="44" r="36"></circle>' .
                                '<circle id="spotaward-inline-ring" class="ring-arc" cx="44" cy="44" r="36" transform="rotate(-90 44 44)"></circle>' .
                                '<path id="spotaward-inline-tick" class="tick-path" d="M26 45 L37 56 L62 31"></path>' .
                            '</svg>' .
                        '</div>' .
                    '</div>' .
                    '<div class="spotaward-success-content">' .
                        '<h2 class="spotaward-success-title" id="spotaward-success-inline-title">' . s($title) . '</h2>' .
                        '<p class="spotaward-success-subtitle">' . s($subtitle) . '</p>' .
                        '<div class="spotaward-success-count-label" id="spotaward-success-inline-label"></div>' .
                        '<div class="spotaward-success-count-value" id="spotaward-success-inline-count"></div>' .
                    '</div>' .
                '</div>' .
                '<div class="spotaward-success-actions">' .
                    '<button type="button" class="btn btn-primary" data-spotaward-success-back="1">' . s($goback) . '</button>' .
                    '<button type="button" class="btn btn-secondary" data-spotaward-success-close="1">' . s($cancel) . '</button>' .
                '</div>' .
            '</div>'
        ) . ';' .
        'document.body.appendChild(overlay);' .
        'var spin=document.getElementById("spotaward-inline-spin");var ring=document.getElementById("spotaward-inline-ring");var tick=document.getElementById("spotaward-inline-tick");' .
        'var titleEl=document.getElementById("spotaward-success-inline-title");var subtitleEl=overlay.querySelector(".spotaward-success-subtitle");var label=document.getElementById("spotaward-success-inline-label");var count=document.getElementById("spotaward-success-inline-count");' .
        'var open=false;var timer=null;' .
        'function normaliseActionText(text){return String(text||"").replace(/\s+/g," ").trim().toLowerCase();}' .
        'function getActionMessages(text){var key=normaliseActionText(text);var map={' .
            '"submit":{progress:"Submitting nomination...",success:"Nomination successfully submitted"},' .
            '"approve all":{progress:"Approving all nominations...",success:"All nominations successfully approved"},' .
            '"approve":{progress:"Approving nomination...",success:"Nomination successfully approved"},' .
            '"save rejection":{progress:"Saving rejection...",success:"Rejection successfully saved"},' .
            '"share to admin":{progress:"Opening share to admin...",success:"Ready to share to admin"},' .
            '"send to admin":{progress:"Sharing to admin...",success:"Successfully shared to admin"},' .
            '"share certificate to students":{progress:"Sharing certificates to students...",success:"Certificates successfully shared to students"},' .
            '"share certificate to selected student":{progress:"Sharing certificate to selected student...",success:"Certificate shared to selected student"},' .
            '"re-generate certificate":{progress:"Re-generating certificate...",success:"Re-generated certificate"},' .
            '"distributed":{progress:"Opening distribution form...",success:"Distribution form ready"},' .
            '"close ticket":{progress:"Closing ticket...",success:"Ticket successfully closed"},' .
            '"submitbutton":{progress:"Processing request...",success:' . json_encode($title) . '},' .
            '"save changes":{progress:"Saving changes...",success:"Changes successfully saved"},' .
            '"delete":{progress:"Deleting nomination...",success:"Nomination successfully deleted"}' .
        '};return map[key]||{progress:"Processing request...",success:' . json_encode($title) . '};}' .
        'function getElementMessages(el){if(!el){return null;}var progress=el.getAttribute("data-spotaward-progress-message");var success=el.getAttribute("data-spotaward-success-message");if(progress&&success){return{progress:progress,success:success};}return null;}' .
        'function getElementActionText(el){if(!el){return "";}if(typeof el.value==="string"&&el.value.trim()!==""){return el.value;}return String(el.textContent||"").replace(/\s+/g," ").trim();}' .
        'overlay.addEventListener("click",function(e){if(e.target.closest("[data-spotaward-success-back]")){window.history.back();return;}if(e.target.closest("[data-spotaward-success-close]")){overlay.classList.remove("is-open");document.body.classList.remove("spotaward-success-busy");}});' .
        'function updateCountdown(seconds){label.textContent=' . json_encode($countdowntemplate) . '.replace("__SECONDS__", String(seconds));count.textContent=String(seconds);}' .
        'function shouldStartForSubmitter(submitter){if(!submitter){return false;}var name=String(submitter.name||"");if(name==="previewdraft"||name==="cleardraft"){return false;}if(name==="submitnominations"||name==="submitbutton"){return true;}var id=String(submitter.id||"");if(id==="id_submitbutton"){return true;}return submitter.hasAttribute("data-spotaward-success-submit");}' .
        'function startOverlay(actionText, explicitMessages){if(open){return;}open=true;var messages=explicitMessages||getActionMessages(actionText);document.body.classList.add("spotaward-success-busy");overlay.classList.add("is-open");if(titleEl){titleEl.textContent=messages.progress;titleEl.classList.remove("is-done");}if(subtitleEl){subtitleEl.textContent="Please wait...";}if(spin){spin.className="spin-svg";}if(ring){ring.className="ring-arc";}if(tick){tick.className="tick-path";}updateCountdown(3);window.setTimeout(function(){if(spin){spin.classList.add("stopped");}if(ring){ring.classList.add("full-green");}},850);window.setTimeout(function(){if(tick){tick.classList.add("drawn");}if(titleEl){titleEl.textContent=messages.success;titleEl.classList.add("is-done");}if(subtitleEl){subtitleEl.textContent=' . json_encode($subtitle) . ';}},1150);var left=3;timer=window.setInterval(function(){left-=1;if(left<0){left=0;}updateCountdown(left);if(left===0){window.clearInterval(timer);}},1000);}' .
        'document.addEventListener("submit",function(e){var submitter=e.submitter;if(submitter&&submitter.name&&submitter.name.toLowerCase().indexOf("cancel")!==-1){return;}if(!shouldStartForSubmitter(submitter)){return;}startOverlay(getElementActionText(submitter),getElementMessages(submitter));},true);' .
        'document.addEventListener("click",function(e){var link=e.target.closest("a[data-spotaward-success]");if(!link||link.target==="_blank"||e.defaultPrevented){return;}startOverlay(getElementActionText(link),getElementMessages(link));},false);' .
        'window.localSpotawardStartSuccessOverlay=startOverlay;' .
        '}());'
    );

    $required = true;
}

/**
 * Redirect after a successful action.
 *
 * @param moodle_url $destination
 * @param string $details
 * @param int $seconds
 * @return void
 */
function local_spotaward_success_redirect(moodle_url $destination, string $details = '', int $seconds = 3): void {
    if ($details !== '') {
        redirect($destination, $details);
    }
    redirect($destination);
}

function local_spotaward_extend_navigation(global_navigation $nav): void {
    global $PAGE, $USER;
    if (!isloggedin() || isguestuser() || empty($USER->id) || !api::user_can_see_menu($USER->id)) {
        return;
    }
    $name = get_string('spotaward', 'local_spotaward');
    $link = new moodle_url('/local/spotaward/index.php');
    $PAGE->requires->js_init_code('
        (function() {
            var menuItem = document.createElement("li");
            menuItem.setAttribute("data-key", "spotaward");
            menuItem.className = "nav-item";
            menuItem.innerHTML = "<a class=\"nav-link\" href=\"' . $link->out(false) . '\">' . $name . '</a>";
            var myCourses = document.querySelector(".nav-item[data-key=\"mycourses\"]") ||
                            document.querySelector(".nav-link[href*=\"my/\"]") ||
                            document.querySelector(".moremenu > ul > li:first-child");
            if (myCourses && myCourses.nextSibling) {
                myCourses.parentNode.insertBefore(menuItem, myCourses.nextSibling);
            } else {
                var menu = document.querySelector("#header .primary-navigation .moremenu > ul") ||
                           document.querySelector(".navbar .primary-navigation .moremenu > ul") ||
                           document.querySelector("#main-navigation .mb2mm");
                if (menu) menu.appendChild(menuItem);
            }
        })();
    ');
}

function local_spotaward_nomination_form_js(moodle_url $ajaxurl): string {
    $ajaxUrl = $ajaxurl->out(false);
    $coursemodulemap = json_encode(\local_spotaward\local\constants::course_module_map());
    $advancedcategories = json_encode(array_values(\local_spotaward\local\constants::advanced_c_award_categories()));
    $standardcategories = json_encode(array_values(\local_spotaward\local\constants::standard_award_categories()));
    $strSelCourse = addslashes(get_string('selectcourse', 'local_spotaward'));
    $strSelStudents = addslashes(get_string('selectstudents', 'local_spotaward'));
    $strSelPm = addslashes(get_string('selectprogrammanager', 'local_spotaward'));
    $strEmbedded = addslashes(get_string('embeddedprofessional', 'local_spotaward'));
    $strIot = addslashes(get_string('iotprofessional', 'local_spotaward'));
    $strLoading = addslashes(get_string('loading', 'core'));
    $strNoResults = addslashes(get_string('noresults', 'core'));

    return <<<JS
(function () {
    'use strict';

    /* =====================================================================
       SHARED CSS  (injected once)
    ===================================================================== */
    var style = document.createElement('style');
    style.textContent = [
        '.sa-wrap { position:relative; width:100%; font-size:14px; }',
        '.sa-input-row { display:flex; flex-wrap:wrap; align-items:center;',
        '  gap:4px; min-height:38px; padding:4px 8px;',
        '  border:1px solid #ced4da; border-radius:4px; background:#fff;',
        '  cursor:text; box-sizing:border-box; }',
        '.sa-wrap.sa-disabled .sa-input-row { background:#e9ecef; cursor:not-allowed; }',
        '.sa-wrap.sa-disabled .sa-text { cursor:not-allowed; }',
        '.sa-wrap.sa-open .sa-input-row { border-color:#80bdff;',
        '  box-shadow:0 0 0 3px rgba(0,123,255,.25); }',
        '.sa-chip { display:inline-flex; align-items:center; gap:4px;',
        '  background:#0066cc; color:#fff; border-radius:3px;',
        '  padding:2px 6px; font-size:13px; white-space:nowrap; }',
        '.sa-chip-x { cursor:pointer; font-weight:bold; line-height:1;',
        '  background:none; border:none; color:#fff; padding:0 2px; font-size:15px; }',
        '.sa-chip-x:hover { color:#ffd; }',
        '.sa-text { flex:1; min-width:80px; border:none; outline:none;',
        '  padding:2px 4px; font-size:14px; background:transparent; }',
        '.sa-placeholder { color:#999; pointer-events:none; }',
        '.sa-dropdown { position:absolute; z-index:9999; width:100%;',
        '  background:#fff; border:1px solid #ced4da; border-top:none;',
        '  border-radius:0 0 4px 4px; max-height:220px; overflow-y:auto;',
        '  box-shadow:0 4px 12px rgba(0,0,0,.12); display:none; }',
        '.sa-wrap.sa-open .sa-dropdown { display:block; }',
        '.sa-option { padding:8px 12px; cursor:pointer; }',
        '.sa-option:hover, .sa-option.sa-hl { background:#e8f0fe; }',
        '.sa-option.sa-selected { background:#f0f7ff; font-weight:500; }',
        '.sa-option.sa-selected::after { content:" \u2714"; color:#0066cc; }',
        '.sa-empty { padding:8px 12px; color:#999; font-style:italic; }',
        '.sa-loading { padding:8px 12px; color:#666; }',
        '.sa-tag { font-size:11px; color:#6c757d; margin-top:3px; display:block; }',
        '.spotaward-report-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;padding:16px;z-index:1050;}',
        '.spotaward-report-backdrop.is-open{display:flex;}',
        '.spotaward-report-modal{background:#fff;border-radius:12px;width:min(560px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 18px 50px rgba(0,0,0,.25);}',
        '.spotaward-report-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #dee2e6;gap:12px;}',
        '.spotaward-report-title{margin:0;font-size:1.1rem;font-weight:600;}',
        '.spotaward-report-close{border:0;background:#f1f3f5;border-radius:6px;padding:8px 12px;cursor:pointer;}',
        '.spotaward-report-body{padding:20px;overflow:auto;}',
        '.spotaward-report-loading,.spotaward-report-error{padding:16px;border-radius:8px;background:#f8f9fa;}',
        '.spotaward-missing-awards{margin:0;padding-left:18px;}',
        '.spotaward-hide{display:none;}',
        '.spotaward-report-warning{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-bottom:8px;}',
        '.spotaward-category-list{margin-top:12px;}',
        '.spotaward-summary-bar{background:#f0f7f5;border:1px solid #d7e3df;border-radius:8px;padding:10px 14px;font-size:0.95rem;}',
        '.spotaward-section-label{font-weight:600;font-size:0.85rem;color:#5d746d;margin-bottom:6px;}',
        '.spotaward-category-items{display:flex;flex-direction:column;gap:4px;}',
        '.spotaward-category-item{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:0.92rem;}',
        '.spotaward-category-item.is-ok{background:#e7f5f1;}',
        '.spotaward-category-item.is-missing{background:#fff3cd;}',
        '.spotaward-category-icon{flex-shrink:0;width:20px;text-align:center;}',
        '.spotaward-category-name{flex:1;font-weight:500;}',
        '.spotaward-category-count{color:#5d746d;font-size:0.85rem;white-space:nowrap;}',
        '@media (max-width: 767px){.spotaward-report-backdrop{padding:8px;}.spotaward-report-header,.spotaward-report-body{padding:14px;}}'
    ].join('');
    document.head.appendChild(style);

    /* =====================================================================
       WIDGET FACTORY
       Creates a searchable select widget that REPLACES a native <select>.
       opts.multiple  = true  -> chip-based multi-select
       opts.multiple  = false -> single value select, shows chosen in input
    ===================================================================== */
    function makeWidget(nativeSelect, opts) {
        opts = opts || {};
        var multi    = !!opts.multiple;
        var ph       = opts.placeholder || 'Search...';
        var selected = [];   // [{value, label}]
        var allItems = [];   // [{value, label}] full list
        var hlIdx    = -1;

        /* --- build DOM --- */
        var wrap = document.createElement('div');
        wrap.className = 'sa-wrap';

        var row = document.createElement('div');
        row.className = 'sa-input-row';

        var textInput = document.createElement('input');
        textInput.type = 'text';
        textInput.className = 'sa-text';
        textInput.autocomplete = 'off';
        textInput.spellcheck   = false;

        var phSpan = document.createElement('span');
        phSpan.className   = 'sa-placeholder';
        phSpan.textContent = ph;

        var dropdown = document.createElement('div');
        dropdown.className = 'sa-dropdown';

        row.appendChild(phSpan);
        row.appendChild(textInput);
        wrap.appendChild(row);
        wrap.appendChild(dropdown);

        /* hide the native select but keep it in DOM for form submit */
        nativeSelect.style.display = 'none';
        nativeSelect.parentNode.insertBefore(wrap, nativeSelect);
        wrap.appendChild(nativeSelect);

        /* --- render chips (multi) or selected text (single) --- */
        function renderChips() {
            /* remove old chips */
            var old = row.querySelectorAll('.sa-chip');
            for (var i = 0; i < old.length; i++) row.removeChild(old[i]);
            phSpan.style.display = (selected.length === 0 && textInput.value === '') ? '' : 'none';

            if (multi) {
                selected.forEach(function (s) {
                    var chip = document.createElement('span');
                    chip.className = 'sa-chip';
                    chip.textContent = s.label;
                    var x = document.createElement('button');
                    x.type = 'button';
                    x.className = 'sa-chip-x';
                    x.textContent = '\u00d7';
                    x.setAttribute('data-val', s.value);
                    chip.appendChild(x);
                    row.insertBefore(chip, phSpan);
                });
            } else {
                if (selected.length > 0) {
                    textInput.value = selected[0].label;
                }
            }
        }

        /* --- sync native select --- */
        function syncNative() {
            var vals = selected.map(function (s) { return String(s.value); });
            for (var i = 0; i < nativeSelect.options.length; i++) {
                nativeSelect.options[i].selected = vals.indexOf(String(nativeSelect.options[i].value)) !== -1;
            }
            /* fire change so other listeners notice */
            var ev = document.createEvent('Event');
            ev.initEvent('change', true, true);
            nativeSelect.dispatchEvent(ev);
        }

        /* --- build dropdown options from allItems filtered by query --- */
        function renderDropdown(q) {
            dropdown.innerHTML = '';
            hlIdx = -1;
            var q2 = (q || '').toLowerCase();
            var shown = allItems.filter(function (it) {
                return it.label.toLowerCase().indexOf(q2) !== -1;
            });
            if (shown.length === 0) {
                var em = document.createElement('div');
                em.className = 'sa-empty';
                em.textContent = '$strNoResults';
                dropdown.appendChild(em);
                return;
            }
            var selVals = selected.map(function (s) { return String(s.value); });
            shown.forEach(function (it, idx) {
                var div = document.createElement('div');
                div.className = 'sa-option' + (selVals.indexOf(String(it.value)) !== -1 ? ' sa-selected' : '');
                div.textContent = it.label;
                div.setAttribute('data-idx', idx);
                div.setAttribute('data-val', it.value);
                div.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    toggleItem(it);
                    if (!multi) closeDropdown();
                });
                dropdown.appendChild(div);
            });
        }

        function openDropdown() {
            wrap.classList.add('sa-open');
            renderDropdown(multi ? textInput.value : '');
        }
        function closeDropdown() {
            wrap.classList.remove('sa-open');
            if (!multi && selected.length > 0) {
                textInput.value = selected[0].label;
            } else if (!multi) {
                textInput.value = '';
            }
        }

        function toggleItem(it) {
            var idx = -1;
            for (var i = 0; i < selected.length; i++) {
                if (String(selected[i].value) === String(it.value)) { idx = i; break; }
            }
            if (idx !== -1) {
                /* single-select: clicking the already-selected item keeps it selected */
                if (multi) selected.splice(idx, 1);
            } else {
                if (!multi) selected = [];
                selected.push(it);
            }
            renderChips();
            syncNative();
            if (multi) {
                renderDropdown(textInput.value);
                textInput.focus();
            }
        }

        /* --- keyboard navigation --- */
        textInput.addEventListener('keydown', function (e) {
            var opts2 = dropdown.querySelectorAll('.sa-option');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                hlIdx = Math.min(hlIdx + 1, opts2.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                hlIdx = Math.max(hlIdx - 1, 0);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (hlIdx >= 0 && opts2[hlIdx]) opts2[hlIdx].dispatchEvent(new MouseEvent('mousedown'));
            } else if (e.key === 'Escape') {
                closeDropdown();
            } else if (e.key === 'Backspace' && textInput.value === '' && multi && selected.length > 0) {
                selected.pop();
                renderChips();
                syncNative();
                renderDropdown('');
            }
            opts2.forEach(function (o, i) {
                o.classList.toggle('sa-hl', i === hlIdx);
            });
        });

        textInput.addEventListener('input', function () {
            phSpan.style.display = 'none';
            if (!wrap.classList.contains('sa-open')) openDropdown();
            renderDropdown(textInput.value);
        });

        textInput.addEventListener('focus', function () { openDropdown(); });
        textInput.addEventListener('blur',  function () {
            setTimeout(function () { closeDropdown(); }, 150);
        });

        /* remove chip on X click */
        row.addEventListener('click', function (e) {
            if (e.target.classList.contains('sa-chip-x')) {
                var val = e.target.getAttribute('data-val');
                selected = selected.filter(function (s) { return String(s.value) !== val; });
                renderChips();
                syncNative();
                textInput.focus();
            } else {
                textInput.focus();
            }
        });

        /* --- Public API --- */
        function setItems(items) {
            allItems = items;
            /* rebuild native options */
            nativeSelect.innerHTML = '';
            items.forEach(function (it) {
                var o = document.createElement('option');
                o.value = it.value;
                o.text  = it.label;
                nativeSelect.add(o);
            });
            /* clear selection */
            selected = [];
            renderChips();
            if (wrap.classList.contains('sa-open')) renderDropdown(textInput.value);
        }

        function setLoading(on) {
            textInput.disabled = on;
            dropdown.innerHTML = '';
            if (on) {
                var ld = document.createElement('div');
                ld.className = 'sa-loading';
                ld.textContent = '$strLoading';
                dropdown.appendChild(ld);
                wrap.classList.add('sa-open');
            } else {
                wrap.classList.remove('sa-open');
            }
        }

        /* seed from existing native options */
        function seedFromNative() {
            allItems = [];
            for (var i = 0; i < nativeSelect.options.length; i++) {
                var o = nativeSelect.options[i];
                if (o.value && o.value !== '0' && o.value !== '') {
                    allItems.push({ value: o.value, label: o.text });
                }
            }
            /* pre-select if native has a selected option */
            selected = [];
            for (var i = 0; i < nativeSelect.options.length; i++) {
                if (nativeSelect.options[i].selected && nativeSelect.options[i].value && nativeSelect.options[i].value !== '0') {
                    selected.push({ value: nativeSelect.options[i].value, label: nativeSelect.options[i].text });
                }
            }
            renderChips();
        }

        seedFromNative();

        function setLocked(locked) {
            wrap.classList.toggle('sa-disabled', !!locked);
            textInput.disabled = !!locked;
            if (locked) {
                closeDropdown();
            }
        }

        function setSelectedValues(values) {
            selected = [];
            var vals = values.map(function(v) { return String(v); });
            for (var i = 0; i < allItems.length; i++) {
                if (vals.indexOf(String(allItems[i].value)) !== -1) {
                    selected.push(allItems[i]);
                }
            }
            renderChips();
            syncNative();
        }

        return {
            setItems: setItems,
            setLoading: setLoading,
            setLocked: setLocked,
            setSelectedValues: setSelectedValues,
            getNativeSelect: function () { return nativeSelect; }
        };
    }

    /* =====================================================================
       INIT
    ===================================================================== */
    var coursePicker = document.getElementById('id_coursepicker');
    var courseNative = coursePicker;
    /* Moodle may omit id= on hidden inputs, so fall back to name-based lookup */
    var nominationForm = coursePicker ? coursePicker.form : null;
    function findByNameOrId(id, name) {
        return document.getElementById(id)
            || (nominationForm ? nominationForm.querySelector('[name="' + name + '"]') : null)
            || document.querySelector('[name="' + name + '"]');
    }
    var hiddenCourseEl = findByNameOrId('id_courseid', 'courseid');
    var professionalEl = document.getElementById('id_professional');
    var programManagerEl = document.getElementById('id_programmanagerid');
    var maacExecutiveEl = document.getElementById('id_maacexecutiveid');
    var moduleEl = findByNameOrId('id_modulename', 'modulename');
    var awardFieldMapEl = findByNameOrId('id_awardfieldmap', 'awardfieldmap');
    var courseModuleMap = $coursemodulemap || {};

    if (!courseNative) return;

    function selectedCourseText() {
        if (coursePicker && typeof coursePicker.selectedIndex !== 'undefined') {
            var pickerOption = coursePicker.options[coursePicker.selectedIndex];
            return pickerOption ? String(pickerOption.text || '') : '';
        }

        return '';
    }

    function selectedCourseValue() {
        return String(courseNative.value || '');
    }

    function syncHiddenCourseId() {
        if (!hiddenCourseEl) {
            return;
        }

        hiddenCourseEl.value = selectedCourseValue();
    }

    function applyCourseModuleRule() {
        if (!moduleEl) {
            return;
        }

        var courseText = selectedCourseText().toUpperCase();
        var mappedModule = '';

        Object.keys(courseModuleMap).some(function(fragment) {
            if (courseText.indexOf(fragment) !== -1) {
                mappedModule = courseModuleMap[fragment];
                return true;
            }

            return false;
        });

        moduleEl.value = mappedModule;
    }

    function applyProfessionalRule() {
        if (!professionalEl) {
            return;
        }

        var courseText = selectedCourseText().toUpperCase();
        var hasCourse = selectedCourseValue() !== '0' && selectedCourseValue() !== '';
        var embeddedOnly = courseText.indexOf('24024C') !== -1;
        var embeddedOption = null;
        var iotOption = null;

        for (var i = 0; i < professionalEl.options.length; i++) {
            var option = professionalEl.options[i];
            if (option.text === '$strEmbedded') {
                embeddedOption = option;
            } else if (option.text === '$strIot') {
                iotOption = option;
            }
        }

        if (iotOption) {
            iotOption.disabled = embeddedOnly;
        }

        if (!hasCourse) {
            professionalEl.value = '0';
            return;
        }

        if (embeddedOption && (professionalEl.value === '' || professionalEl.value === '0')) {
            professionalEl.value = embeddedOption.value;
        }

        if (embeddedOnly && embeddedOption) {
            professionalEl.value = embeddedOption.value;
        }
    }

    function enhanceAwardStudentSelects() {
        var selects = document.querySelectorAll('select[name^="awardstudents_"], select.spotaward-award-students');
        for (var i = 0; i < selects.length; i++) {
            if (selects[i].getAttribute('data-sa-enhanced') === '1') {
                continue;
            }

            selects[i].setAttribute('data-sa-enhanced', '1');
            var widget = makeWidget(selects[i], { multiple: true, placeholder: '$strSelStudents' });
        }
    }

    var pmWidget = null;
    var maacWidget = null;
    var awardWidgets = {};

    function enhanceProgramManagerPicker() {
        if (!programManagerEl || programManagerEl.getAttribute('data-sa-enhanced') === '1') {
            return;
        }

        programManagerEl.setAttribute('data-sa-enhanced', '1');
        pmWidget = makeWidget(programManagerEl, {
            multiple: false,
            placeholder: '$strSelPm'
        });
    }

    function enhanceMaacExecutivePicker() {
        if (!maacExecutiveEl || maacExecutiveEl.getAttribute('data-sa-enhanced') === '1') {
            return;
        }

        maacExecutiveEl.setAttribute('data-sa-enhanced', '1');
        maacWidget = makeWidget(maacExecutiveEl, {
            multiple: false,
            placeholder: maacExecutiveEl.getAttribute('data-placeholder') || 'Search...'
        });
    }

    function buildAwardFields(categories, students) {
        var container = document.getElementById('spotaward-award-fields');
        if (!container) return;

        /* remove previously built fields */
        Object.keys(awardWidgets).forEach(function(fn) {
            var old = nominationForm ? nominationForm.querySelector('[name="' + fn + '"]') : null;
            if (old && old.parentNode) old.parentNode.removeChild(old.parentNode.querySelector('.sa-wrap') || old);
        });
        container.innerHTML = '';
        awardWidgets = {};

        var fieldmap = {};

        categories.forEach(function(category, index) {
            var fieldname = 'awardstudents_' + index;
            fieldmap[fieldname] = category;

            var label = document.createElement('label');
            label.textContent = category;
            label.className = 'col-form-label d-block mt-2';

            var sel = document.createElement('select');
            sel.name = fieldname + '[]';
            sel.multiple = true;
            sel.size = 8;
            sel.className = 'spotaward-award-students';
            students.forEach(function(s) {
                var o = document.createElement('option');
                o.value = s.id;
                o.text = s.name + ' (' + s.email + ')';
                sel.add(o);
            });

            var wrap = document.createElement('div');
            wrap.className = 'form-group row fitem';
            wrap.appendChild(label);
            wrap.appendChild(sel);
            container.appendChild(wrap);

            awardWidgets[fieldname] = makeWidget(sel, { multiple: true, placeholder: '$strSelStudents' });
        });

        if (awardFieldMapEl) {
            awardFieldMapEl.value = JSON.stringify(fieldmap);
        }
    }

    function fetchCourseData(courseid, onLoaded) {
        if (!courseid || courseid === '0') {
            if (pmWidget) pmWidget.setItems([]);
            if (maacWidget) maacWidget.setItems([]);
            var pmWarn0 = document.getElementById('spotaward-pm-noassign');
            if (pmWarn0) pmWarn0.style.display = 'none';
            var maacWarn0 = document.getElementById('spotaward-maac-noassign');
            if (maacWarn0) maacWarn0.style.display = 'none';
            buildAwardFields([], []);
            if (onLoaded) onLoaded(null);
            return;
        }

        var url = '$ajaxUrl' + '?courseid=' + encodeURIComponent(courseid);

        if (pmWidget) pmWidget.setLoading(true);
        if (maacWidget) maacWidget.setLoading(true);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.onload = function() {
            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch(e) {}
            if (!data) {
                if (pmWidget) pmWidget.setLoading(false);
                if (maacWidget) maacWidget.setLoading(false);
                if (onLoaded) onLoaded(null);
                return;
            }

            var pmItems = (data.programmanagers || []).map(function(p) {
                return { value: p.id, label: p.name };
            });
            var maacItems = (data.maacexecutives || []).map(function(m) {
                return { value: m.id, label: m.name };
            });

            if (pmWidget) {
                pmWidget.setLoading(false);
                pmWidget.setItems(pmItems);
                if (pmItems.length === 1) pmWidget.setSelectedValues([String(pmItems[0].value)]);
            }
            var pmWarn = document.getElementById('spotaward-pm-noassign');
            if (pmWarn) pmWarn.style.display = pmItems.length === 0 ? '' : 'none';

            if (maacWidget) {
                maacWidget.setLoading(false);
                maacWidget.setItems(maacItems);
                if (maacItems.length === 1) maacWidget.setSelectedValues([String(maacItems[0].value)]);
            }
            var maacWarn = document.getElementById('spotaward-maac-noassign');
            if (maacWarn) maacWarn.style.display = maacItems.length === 0 ? '' : 'none';

            buildAwardFields(data.categories || [], data.students || []);
            if (onLoaded) onLoaded(data);
        };
        xhr.onerror = function() {
            if (pmWidget) pmWidget.setLoading(false);
            if (maacWidget) maacWidget.setLoading(false);
            if (onLoaded) onLoaded(null);
        };
        xhr.send();
    }

    function enhanceCoursePicker() {
        if (!coursePicker || coursePicker.getAttribute('data-sa-enhanced') === '1') {
            return;
        }

        coursePicker.setAttribute('data-sa-enhanced', '1');
        makeWidget(coursePicker, {
            multiple: false,
            placeholder: '$strSelCourse'
        });
    }

    function applyCourseSelectionState() {
        syncHiddenCourseId();
        applyCourseModuleRule();
        applyProfessionalRule();
    }

    function toggleAwardSection() {
        var section = document.getElementById('spotaward-award-section');
        if (!section) return;
        var hasCourse = selectedCourseValue() && selectedCourseValue() !== '0';
        section.style.display = hasCourse ? '' : 'none';
    }

    if (coursePicker) {
        coursePicker.setAttribute('data-formchangechecker-override', '1');
        coursePicker.addEventListener('change', function() {
            applyCourseSelectionState();
            toggleAwardSection();
            fetchCourseData(selectedCourseValue(), null);
        });
    }

    applyCourseSelectionState();
    toggleAwardSection();
    enhanceCoursePicker();
    enhanceProgramManagerPicker();
    if (maacExecutiveEl) {
        maacExecutiveEl.setAttribute('data-placeholder', maacExecutiveEl.options.length ? maacExecutiveEl.options[0].text : 'Search...');
    }
    enhanceMaacExecutivePicker();

    /* restore draft state via AJAX on page load */
    var draftForm = nominationForm;
    var hasDraft = draftForm && draftForm.getAttribute('data-has-draft-lock') === '1';
    if (hasDraft) {
        var draftCourseid = draftForm.getAttribute('data-draft-courseid') || '0';
        var draftPmid = draftForm.getAttribute('data-draft-programmanagerid') || '0';
        var draftMaacid = draftForm.getAttribute('data-draft-maacexecutiveid') || '0';
        var draftPayloadRaw = draftForm.getAttribute('data-draft-awardpayload') || '';
        var draftPayload = {};
        try { draftPayload = JSON.parse(decodeURIComponent(draftPayloadRaw)); } catch(e) {}

        fetchCourseData(draftCourseid, function(data) {
            if (!data) return;
            if (pmWidget && draftPmid !== '0') pmWidget.setSelectedValues([draftPmid]);
            if (maacWidget && draftMaacid !== '0') maacWidget.setSelectedValues([draftMaacid]);
            /* restore award allocations */
            Object.keys(draftPayload).forEach(function(category) {
                var studentids = draftPayload[category];
                /* find which fieldname maps to this category */
                var fieldmap = {};
                try { fieldmap = JSON.parse(awardFieldMapEl ? awardFieldMapEl.value : '{}'); } catch(e) {}
                Object.keys(fieldmap).forEach(function(fn) {
                    if (fieldmap[fn] === category && awardWidgets[fn]) {
                        awardWidgets[fn].setSelectedValues(studentids.map(String));
                    }
                });
            });
        });
    } else {
        enhanceAwardStudentSelects();
    }

}());
JS;
}

/**
 * Ensure the sortable/filterable table tools are loaded once.
 *
 * @return void
 */
function local_spotaward_require_table_tools(): void {
    global $PAGE;

    static $required = false;
    if ($required) {
        return;
    }

    $PAGE->requires->js_call_amd('local_spotaward/table_tools', 'init');
    $required = true;
}

/**
 * Render a status label as a badge.
 *
 * @param string $status
 * @return string
 */
function local_spotaward_render_badge(string $status): string {
    $statusclean = preg_replace('/[^a-z]/', '', strtolower($status));
    $badgeclass = 'spotaward-badge spotaward-badge-' . $statusclean;
    return html_writer::tag('span', s($status), ['class' => $badgeclass]);
}

/**
 * Build a reusable table cell definition.
 *
 * @param string $display
 * @param array $options
 * @return array
 */
function local_spotaward_table_cell(string $display, array $options = []): array {
    $text = $options['text'] ?? trim(html_entity_decode(strip_tags($display), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    return [
        'display' => $display,
        'text' => $text,
        'sort' => $options['sort'] ?? $text,
        'filter' => $options['filter'] ?? $text,
        'date' => $options['date'] ?? '',
        'search' => $options['search'] ?? $text,
        'class' => $options['class'] ?? '',
    ];
}

/**
 * Render a reusable sortable and filterable data table.
 *
 * @param array $columns
 * @param array $rows
 * @param array $options
 * @return string
 */
function local_spotaward_render_data_table(array $columns, array $rows, array $options = []): string {
    local_spotaward_require_table_tools();

    $tableid = $options['id'] ?? ('spotaward-table-' . substr(sha1(serialize([$columns, $options])), 0, 12));
    $tableclass = trim('generaltable table table-striped spotaward-enhanced-table ' . ($options['tableclass'] ?? ''));

    $columnconfig = [];
    foreach ($columns as $column) {
        $columnconfig[] = [
            'key' => $column['key'],
            'label' => !empty($column['labelhtml'])
                ? trim(html_entity_decode(strip_tags((string)$column['label']), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : $column['label'],
            'type' => $column['type'] ?? 'text',
            'sortable' => array_key_exists('sortable', $column) ? !empty($column['sortable']) : true,
            'filter' => $column['filter'] ?? 'none',
            'searchable' => array_key_exists('searchable', $column) ? !empty($column['searchable']) : true,
        ];
    }

    $thead = html_writer::start_tag('thead');
    $thead .= html_writer::start_tag('tr');
    foreach ($columns as $column) {
        $thattrs = [
            'scope' => 'col',
            'data-column-key' => $column['key'],
            'data-column-type' => $column['type'] ?? 'text',
            'data-column-filter' => $column['filter'] ?? 'none',
            'data-column-sortable' => empty($column['sortable']) ? '0' : '1',
        ];
        $label = !empty($column['labelhtml']) ? (string)$column['label'] : s($column['label']);
        $thead .= html_writer::tag('th', $label, $thattrs);
    }
    $thead .= html_writer::end_tag('tr');
    $thead .= html_writer::end_tag('thead');

    $tbody = html_writer::start_tag('tbody');
    foreach ($rows as $row) {
        $rowattrs = [];
        if (!empty($row['_rowclass'])) {
            $rowattrs['class'] = (string)$row['_rowclass'];
        }
        $tbody .= html_writer::start_tag('tr', $rowattrs);
        foreach ($columns as $column) {
            $cell = $row[$column['key']] ?? local_spotaward_table_cell('');
            if (!is_array($cell)) {
                $cell = local_spotaward_table_cell(s((string)$cell), [
                    'text' => (string)$cell,
                ]);
            }

            $cellattrs = [
                'data-column-key' => $column['key'],
                'data-search-value' => $cell['search'] ?? '',
                'data-filter-value' => $cell['filter'] ?? '',
                'data-sort-value' => (string)($cell['sort'] ?? ''),
                'data-date-value' => $cell['date'] ?? '',
            ];

            if (!empty($cell['class'])) {
                $cellattrs['class'] = $cell['class'];
            }

            $tbody .= html_writer::tag('td', $cell['display'] ?? '', $cellattrs);
        }
        $tbody .= html_writer::end_tag('tr');
    }
    $tbody .= html_writer::end_tag('tbody');

    $tableattrs = [
        'id' => $tableid,
        'class' => $tableclass,
        'data-enhanced-table' => '1',
        'data-table-label' => $options['label'] ?? '',
        'data-columns' => json_encode($columnconfig),
    ];

    $tablehtml = html_writer::tag('table', $thead . $tbody, $tableattrs);
    $toolbar = html_writer::div('', 'spotaward-table-toolbar', ['data-table-toolbar' => '1']);
    $results = html_writer::div('', 'spotaward-table-status', ['data-table-status' => '1']);
    $empty = html_writer::div(get_string('notablerowsmatchfilters', 'local_spotaward'), 'spotaward-empty spotaward-table-empty hidden', [
        'data-table-empty' => '1',
        'hidden' => 'hidden',
    ]);

    $rootattrs = [
        'data-table-root' => '1',
        'data-search-label' => $options['searchlabel'] ?? get_string('searchtable', 'local_spotaward'),
        'data-search-placeholder' => $options['searchplaceholder'] ?? get_string('keywordsearch', 'local_spotaward'),
        'data-clear-label' => get_string('clearfilters', 'local_spotaward'),
        'data-filter-prefix' => get_string('filterby', 'local_spotaward'),
        'data-all-label' => get_string('alloptions', 'local_spotaward'),
        'data-date-from-label' => get_string('datefrom', 'local_spotaward'),
        'data-date-to-label' => get_string('dateto', 'local_spotaward'),
        'data-results-label' => get_string('matchingrecords', 'local_spotaward'),
        'data-sort-asc-label' => get_string('sortascending', 'local_spotaward'),
        'data-sort-desc-label' => get_string('sortdescending', 'local_spotaward'),
        'data-export-label' => get_string('exportcsv', 'local_spotaward'),
    ];

    if (!empty($options['nosearch'])) {
        $rootattrs['data-table-nosearch'] = '1';
    }

    if (!empty($options['noclear'])) {
        $rootattrs['data-table-noclear'] = '1';
    }

    return html_writer::div(
        $toolbar .
        $results .
        html_writer::div($tablehtml, 'spotaward-table-wrap') .
        $empty,
        'spotaward-data-view',
        $rootattrs
    );
}

/**
 * Render report rows table.
 *
 * @param array $rows
 * @param bool $showstudent
 * @return string
 */
function local_spotaward_render_report_rows_table(array $rows, bool $showstudent = false): string {
    if (empty($rows)) {
        return html_writer::tag('p', get_string('reportnotavailable', 'local_spotaward'), ['class' => 'spotaward-empty']);
    }

    $columns = [];
    if ($showstudent) {
        $columns[] = [
            'key' => 'studentname',
            'label' => get_string('studentname', 'local_spotaward'),
            'type' => 'text',
            'filter' => 'none',
        ];
    }
    $columns[] = [
        'key' => 'activityname',
        'label' => get_string('activityname', 'local_spotaward'),
        'type' => 'text',
        'filter' => 'none',
    ];
    $columns[] = [
        'key' => 'activitycategory',
        'label' => get_string('activitycategory', 'local_spotaward'),
        'type' => 'text',
        'filter' => 'none',
    ];
    $columns[] = [
        'key' => 'activitytype',
        'label' => get_string('activitytype', 'local_spotaward'),
        'type' => 'text',
        'filter' => 'none',
    ];
    $columns[] = [
        'key' => 'marksgrade',
        'label' => get_string('marksgrade', 'local_spotaward'),
        'type' => 'number',
        'filter' => 'none',
    ];

    $tablerows = [];
    foreach ($rows as $row) {
        $tablerow = [];
        if ($showstudent) {
            $tablerow['studentname'] = local_spotaward_table_cell(s($row['studentname']), [
                'text' => (string)$row['studentname'],
            ]);
        }
        $rawgrade = trim((string)($row['grade'] ?? ''));
        $numericgrade = is_numeric($rawgrade) ? (float)$rawgrade : $rawgrade;
        $tablerow['activityname'] = local_spotaward_table_cell(s($row['activityname']), [
            'text' => (string)$row['activityname'],
        ]);
        $tablerow['activitycategory'] = local_spotaward_table_cell(s($row['categorylabel']), [
            'text' => (string)$row['categorylabel'],
        ]);
        $tablerow['activitytype'] = local_spotaward_table_cell(s($row['typelabel']), [
            'text' => (string)$row['typelabel'],
        ]);
        $tablerow['marksgrade'] = local_spotaward_table_cell(s($rawgrade), [
            'text' => $rawgrade,
            'sort' => $numericgrade,
        ]);
        $tablerows[] = $tablerow;
    }

    return local_spotaward_render_data_table($columns, $tablerows, [
        'id' => $showstudent ? 'spotaward-course-report-rows' : 'spotaward-student-report-rows',
        'label' => get_string('activitydetails', 'local_spotaward'),
        'tableclass' => 'mb-0',
        'nosearch' => true,
        'noclear' => true,
    ]);
}

/**
 * Render student report content HTML.
 *
 * @param stdClass $student
 * @param stdClass $course
 * @param array $report
 * @return string
 */
function local_spotaward_render_student_report_content(stdClass $student, stdClass $course, array $report): string {
    $summarycolumns = [
        [
            'key' => 'activitytype',
            'label' => get_string('activitytype', 'local_spotaward'),
            'type' => 'text',
            'filter' => 'none',
        ],
        [
            'key' => 'percentage',
            'label' => get_string('percentage', 'local_spotaward'),
            'type' => 'number',
            'filter' => 'none',
        ],
        [
            'key' => 'completionrate',
            'label' => get_string('completionrate', 'local_spotaward'),
            'type' => 'number',
            'filter' => 'none',
        ],
    ];

    $summaryrows = [];
    foreach ($report['summaryrows'] ?? [] as $row) {
        $summaryrows[] = [
            'activitytype' => local_spotaward_table_cell(s($row['activity']), [
                'text' => (string)$row['activity'],
            ]),
            'percentage' => local_spotaward_table_cell(s($row['percentage']), [
                'text' => (string)$row['percentage'],
                'sort' => (float)preg_replace('/[^0-9.\-]/', '', (string)$row['percentage']),
            ]),
            'completionrate' => local_spotaward_table_cell(s($row['completionrate']), [
                'text' => (string)$row['completionrate'],
                'sort' => (float)preg_replace('/[^0-9.\-]/', '', (string)$row['completionrate']),
            ]),
        ];
    }

    $output = '';
    $output .= html_writer::tag('h4', s(fullname($student)), ['class' => 'spotaward-section-title']);
    $output .= html_writer::start_div('spotaward-summary-grid is-compact');
    $output .= html_writer::div(
        html_writer::div(format_string($course->fullname), 'spotaward-stat-value') .
        html_writer::div(get_string('course', 'local_spotaward'), 'spotaward-stat-label'),
        'spotaward-stat-card'
    );
    $output .= html_writer::div(
        html_writer::div((int)($report['activitycount'] ?? 0), 'spotaward-stat-value') .
        html_writer::div(get_string('showingactivities', 'local_spotaward'), 'spotaward-stat-label'),
        'spotaward-stat-card is-primary'
    );
    $output .= html_writer::end_div();

    $output .= html_writer::start_div('spotaward-card');
    $output .= html_writer::start_div('spotaward-card-header');
    $output .= html_writer::tag('h5', get_string('categorysummary', 'local_spotaward'), ['class' => 'spotaward-subsection-title']);
    $output .= html_writer::end_div();
    $output .= html_writer::start_div('spotaward-card-body');
    $output .= local_spotaward_render_data_table($summarycolumns, $summaryrows, [
        'id' => 'spotaward-student-report-summary',
        'label' => get_string('categorysummary', 'local_spotaward'),
        'tableclass' => 'mb-0',
        'nosearch' => true,
        'noclear' => true,
    ]);
    $output .= html_writer::end_div();
    $output .= html_writer::end_div();

    $output .= html_writer::start_div('spotaward-card');
    $output .= html_writer::start_div('spotaward-card-header');
    $output .= html_writer::tag('h5', get_string('activitydetails', 'local_spotaward'), ['class' => 'spotaward-subsection-title']);
    $output .= html_writer::end_div();
    $output .= html_writer::start_div('spotaward-card-body');
    $output .= local_spotaward_render_report_rows_table($report['rows'] ?? []);
    $output .= html_writer::end_div();
    $output .= html_writer::end_div();

    return $output;
}

/**
 * Render course report content HTML.
 *
 * @param stdClass $course
 * @param array $report
 * @return string
 */
function local_spotaward_render_course_report_content(stdClass $course, array $report): string {
    $output = '';
    $output .= html_writer::tag('h4', format_string($course->fullname), ['class' => 'spotaward-section-title']);
    $output .= html_writer::start_div('spotaward-summary-grid is-compact');
    $output .= html_writer::div(
        html_writer::div((int)($report['studentcount'] ?? 0), 'spotaward-stat-value') .
        html_writer::div(get_string('students', 'local_spotaward'), 'spotaward-stat-label'),
        'spotaward-stat-card'
    );
    $output .= html_writer::div(
        html_writer::div((int)($report['activitycount'] ?? 0), 'spotaward-stat-value') .
        html_writer::div(get_string('showingactivities', 'local_spotaward'), 'spotaward-stat-label'),
        'spotaward-stat-card is-primary'
    );
    $output .= html_writer::end_div();
    $output .= html_writer::start_div('spotaward-card');
    $output .= html_writer::start_div('spotaward-card-header');
    $output .= html_writer::tag('h5', get_string('activitydetails', 'local_spotaward'), ['class' => 'spotaward-subsection-title']);
    $output .= html_writer::end_div();
    $output .= html_writer::start_div('spotaward-card-body');
    $output .= local_spotaward_render_report_rows_table($report['rows'] ?? [], true);
    $output .= html_writer::end_div();
    $output .= html_writer::end_div();

    return $output;
}

/**
 * JS for the submission-page student report modal.
 *
 * @param moodle_url $ajaxurl
 * @return string
 */
function local_spotaward_submission_report_modal_js(moodle_url $ajaxurl): string {
    $ajax = $ajaxurl->out(false);
    $title = addslashes(get_string('studentreport', 'local_spotaward'));
    $close = '&times;';
    $loading = addslashes(get_string('loading', 'core'));
    $error = addslashes(get_string('error'));
    $sesskey = sesskey();

    return <<<JS
(function() {
    'use strict';

    if (document.getElementById('spotaward-report-modal')) {
        return;
    }

    var style = document.createElement('style');
    style.textContent = [
        '.spotaward-report-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;padding:16px;z-index:1050;}',
        '.spotaward-report-backdrop.is-open{display:flex;}',
        '.spotaward-report-modal{background:#fff;border-radius:12px;width:min(900px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 18px 50px rgba(0,0,0,.25);}',
        '.spotaward-report-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #dee2e6;gap:12px;}',
        '.spotaward-report-title{margin:0;font-size:1.1rem;font-weight:600;}',
        '.spotaward-report-close{border:0;background:#f1f3f5;border-radius:6px;padding:8px 12px;cursor:pointer;}',
        '.spotaward-report-body{padding:20px;overflow:auto;}',
        '.spotaward-report-loading,.spotaward-report-error{padding:16px;border-radius:8px;background:#f8f9fa;}',
        '@media (max-width: 767px){.spotaward-report-backdrop{padding:8px;}.spotaward-report-header,.spotaward-report-body{padding:14px;}}'
    ].join('');
    document.head.appendChild(style);

    var backdrop = document.createElement('div');
    backdrop.id = 'spotaward-report-modal';
    backdrop.className = 'spotaward-report-backdrop';
    backdrop.innerHTML =
        '<div class="spotaward-report-modal" role="dialog" aria-modal="true" aria-labelledby="spotaward-report-title">' +
            '<div class="spotaward-report-header">' +
                '<h3 class="spotaward-report-title" id="spotaward-report-title">$title</h3>' +
                '<button type="button" class="spotaward-report-close" aria-label="Close">$close</button>' +
            '</div>' +
            '<div class="spotaward-report-body"><div class="spotaward-report-loading">$loading</div></div>' +
        '</div>';
    document.body.appendChild(backdrop);

    var body = backdrop.querySelector('.spotaward-report-body');
    var closeButton = backdrop.querySelector('.spotaward-report-close');
    var activeTrigger = null;

    function closeModal() {
        backdrop.classList.remove('is-open');
        body.innerHTML = '<div class="spotaward-report-loading">$loading</div>';
        if (activeTrigger) {
            activeTrigger.focus();
        }
    }

    function openModal(trigger) {
        activeTrigger = trigger || null;
        backdrop.classList.add('is-open');
    }

    closeButton.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && backdrop.classList.contains('is-open')) {
            closeModal();
        }
    });

    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('.spotaward-view-report');
        if (!trigger) {
            return;
        }

        e.preventDefault();
        var itemid = trigger.getAttribute('data-itemid');
        if (!itemid) {
            return;
        }

        openModal(trigger);

        var url = '$ajax' + '?action=studentreport&itemid=' + encodeURIComponent(itemid) + '&sesskey=' + encodeURIComponent('$sesskey');
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function(data) {
            if (data.error) {
                throw new Error(data.error);
            }
            body.innerHTML = data.html || '<div class="spotaward-report-error">$error</div>';
        }).catch(function(err) {
            var errDiv = document.createElement('div');
            errDiv.className = 'spotaward-report-error';
            errDiv.textContent = (err && err.message ? err.message : '$error');
            body.innerHTML = '';
            body.appendChild(errDiv);
        });
    });
})();
JS;
}
