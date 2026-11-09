<?php
/** Execute the rendered KCL footer against the actual shared panel adapter. */
function _t($text, ...$arguments) { return $arguments ? vsprintf($text, $arguments) : $text; }
class CookieOptionsManager { public static function cookieManagerEnabled() { return false; } }
class TooltipManager { public static function getLoadHTML() { return ''; } }
$view = new class {
	public function render($path) { return ''; }
	public function footer() { ob_start(); include dirname(__DIR__).'/views/pageFormat/pageFooter.php'; return ob_get_clean(); }
};
$footer = $view->footer();
if (in_array('--fixtures', $argv, true)) {
	echo json_encode(['footer'=>$footer], JSON_THROW_ON_ERROR); exit;
}
$document = new DOMDocument();
$document->loadHTML('<!doctype html><html><body>'.$footer, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);
$panel = $xpath->query('//*[@id="caMediaPanel"]')->item(0);
if (!$panel || $panel->getAttribute('role') !== 'dialog' || $panel->getAttribute('aria-modal') !== 'true'
	|| $panel->getAttribute('aria-hidden') !== 'true' || $panel->getAttribute('tabindex') !== '-1') {
	throw new RuntimeException('The rendered media panel must start as a hidden, focusable modal dialog.');
}
$attributes = [];
foreach ($panel->attributes as $attribute) { $attributes[$attribute->name] = $attribute->value; }
$scripts = [];
foreach ($xpath->query('//script[not(@src)]') as $script) { $scripts[] = $script->textContent; }
$fixture = json_encode(['attributes'=>$attributes, 'scripts'=>$scripts,
	'helper'=>file_get_contents(dirname(__DIR__).'/assets/pawtucket/js/user-features.js')], JSON_THROW_ON_ERROR);
$javascript = <<<'JS'
'use strict';
const assert = require('node:assert/strict'), vm = require('node:vm');
const fixture = FIXTURE;
const events = {}, observers = [];
const document = {
    readyState: 'loading', activeElement: null,
    addEventListener(name, callback) { (events[name] ||= []).push(callback); },
    querySelectorAll() { return []; },
    querySelector(selector) { return selector === '#main' ? main : null; },
    getElementById(id) { return id === 'caMediaPanel' ? panel : null; }
};
class Element {
    constructor(tag, attributes = {}) {
        this.tagName = tag.toUpperCase(); this.attrs = {...attributes}; this.children = [];
        this.tabIndex = Number(attributes.tabindex ?? 0); this.visible = this.isConnected = true;
    }
    append(child) { child.parentElement = this; this.children.push(child); }
    contains(node) { return node === this || this.children.some(child => child.contains(node)); }
    hasAttribute(name) { return Object.hasOwn(this.attrs, name); }
    getAttribute(name) { return this.attrs[name] ?? null; }
    setAttribute(name, value) { this.attrs[name] = value; }
    removeAttribute(name) { delete this.attrs[name]; }
    getClientRects() { return this.visible ? [{}] : []; }
    closest(selector) { return selector === '[inert]' && this.hasAttribute('inert') ? this : null; }
    querySelector() { return this.querySelectorAll().find(node => node.closeControl) || null; }
    querySelectorAll() { return this.children.flatMap(child => [child, ...child.querySelectorAll()]).filter(child => child.tagName === 'BUTTON'); }
    focus() {
        document.activeElement = this;
        for (const callback of events.focusin || []) { callback({target:this}); }
    }
}
const body = document.body = new Element('body'), main = new Element('main'), opener = new Element('button');
const footer = new Element('footer'), panel = new Element('div', fixture.attributes);
body.append(main); body.append(footer); body.append(panel); main.append(opener); opener.focus();
let opens = 0, closes = 0;
const caUI = {initPanel(options) {
    assert.equal(options.panelID, panel.getAttribute('id'));
    assert.equal(options.panelContentID, 'caMediaPanelContentArea');
    return {
        ...options,
        onOpenCallback() { opens++; }, finallyCallback() { closes++; },
        showPanel() { this.onOpenCallback(); },
        hidePanel() { this.onCloseCallback(); this.finallyCallback(); }
    };
}};
function jQuery() { return {on() {}, ready(callback) { callback(); }}; }
class MutationObserver {
    constructor(callback) { this.callback = callback; observers.push(this); }
    observe(target) { assert.equal(target, panel); }
}
const context = vm.createContext({window:{}, document, jQuery, caUI, MutationObserver});
vm.runInContext(fixture.helper, context);
for (const script of fixture.scripts) { vm.runInContext(script, context); }
const controller = context.caMediaPanel;
assert.equal(typeof controller?.showPanel, 'function');
assert.equal(observers.length, 1, 'The real footer must register the shared lifecycle adapter.');
for (let cycle = 0; cycle < 2; cycle++) {
    opener.focus(); controller.showPanel('/synthetic-document');
    assert.equal(panel.getAttribute('aria-hidden'), 'false', 'Opening must activate the shared CSS scroll-lock selector.');
    assert.equal(panel.contains(document.activeElement), true, 'Focus must enter the dialog, including when prior content is retained.');
    assert.equal(main.hasAttribute('inert'), true);
    assert.equal(footer.hasAttribute('inert'), true);
    const close = new Element('button'), download = new Element('button'); close.closeControl = true;
    panel.children = []; panel.append(close); panel.append(download);
    observers[0].callback(); assert.equal(document.activeElement, close, 'Loaded content must receive focus.');
    let trapped = false;
    for (const callback of events.keydown || []) {
        callback({key:'Tab', shiftKey:true, target:close, preventDefault() { trapped = true; }});
    }
    assert.equal(trapped, true); assert.equal(document.activeElement, download, 'Reverse Tab must remain inside the modal.');
    controller.hidePanel();
    assert.equal(panel.getAttribute('aria-hidden'), 'true', 'Closing must release the CSS scroll-lock selector.');
    assert.equal(document.activeElement, opener, 'Closing must restore the thumbnail opener.');
    assert.equal(main.hasAttribute('inert'), false);
    assert.equal(footer.hasAttribute('inert'), false);
    assert.equal(main.getAttribute('aria-hidden'), null);
}
assert.equal(opens, 2); assert.equal(closes, 2, 'Native open/close callbacks must remain intact across repeated openings.');
console.log('Media panel passed: rendered KCL initialization, shared lifecycle, repeated modal state, background inertness and focus restoration.');
JS;
$javascript = str_replace('FIXTURE', $fixture, $javascript);
$process = proc_open(['node', '-'], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Node.js is required for the panel lifecycle check.'); }
fwrite($pipes[0], $javascript); fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($process) !== 0) { throw new RuntimeException($stderr ?: $stdout); }
echo $stdout;
