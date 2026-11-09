<?php
/** Execute the rendered KCL footer against the actual shared panel adapter. */
function _t($text, ...$arguments) { return $arguments ? vsprintf($text, $arguments) : $text; }
class CookieOptionsManager { public static function cookieManagerEnabled() { return false; } }
class TooltipManager { public static function getLoadHTML() { return ''; } }
function caParseElementDimension($value, $options) { return $value ?: $options['default']; }
$view = new class {
	public function render($path) { return ''; }
	public function footer() { ob_start(); include dirname(__DIR__).'/views/pageFormat/pageFooter.php'; return ob_get_clean(); }
};
$footer = $view->footer();
$pdfView = new class {
	public $request;
	public function __construct() { $this->request = new class { public function getBaseUrlPath() { return ''; } }; }
	public function getVar($key) { return ['width'=>'100%', 'height'=>'100%', 'search'=>'', 'scroll_mode'=>0, 'original_media_url'=>'/synthetic.pdf'][$key] ?? null; }
	public function renderPdf() { ob_start(); include dirname(__DIR__).'/../tadl/views/mediaViewers/pdfjs.php'; return ob_get_clean(); }
};
$pdf = $pdfView->renderPdf();
if (in_array('--fixtures', $argv, true)) {
	echo json_encode(['footer'=>$footer, 'pdf'=>$pdf], JSON_THROW_ON_ERROR); exit;
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
function mediaPanelTree(DOMElement $element): array {
	$attributes = []; $children = [];
	foreach ($element->attributes as $attribute) { $attributes[$attribute->name] = $attribute->value; }
	foreach ($element->childNodes as $child) { if ($child instanceof DOMElement) { $children[] = mediaPanelTree($child); } }
	return ['tag'=>$element->tagName, 'attributes'=>$attributes, 'children'=>$children];
}
$pdfDocument = new DOMDocument();
$pdfDocument->loadHTML($pdf, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$pdfTree = mediaPanelTree((new DOMXPath($pdfDocument))->query('//*[@id="outerContainer"]')->item(0));
$pdfRuntime = file_get_contents(dirname(__DIR__).'/../../pawtucket2/assets/pdfjs/viewer/viewer.js');
if (!preg_match('/function webViewerKeyDown\(evt\) \{.*?(?=\nfunction apiPageLayoutToSpreadMode\()/s', $pdfRuntime, $nativeKeydown)) {
	throw new RuntimeException('The native PDF keyboard handler could not be found.');
}
$fixture = json_encode(['attributes'=>$attributes, 'scripts'=>$scripts,
	'pdf'=>$pdfTree, 'nativeKeydown'=>$nativeKeydown[0], 'helper'=>file_get_contents(dirname(__DIR__).'/assets/pawtucket/js/user-features.js')], JSON_THROW_ON_ERROR);
$javascript = <<<'JS'
'use strict';
const assert = require('node:assert/strict'), vm = require('node:vm');
const fixture = FIXTURE;
const events = {}, observers = [], ids = new Map();
const document = {
    readyState: 'loading', activeElement: null,
    addEventListener(name, callback, capture = false) { (events[name] ||= []).push({callback, capture}); },
    querySelectorAll() { return []; },
    querySelector(selector) { return selector === '#main' ? main : null; },
    getElementById(id) { return ids.get(id) || null; }
};
function notify(node, type, attributeName) {
    for (const observer of [...observers]) {
        if (!observer.active || (observer.target !== node && !(observer.options.subtree && observer.target.contains(node)))) { continue; }
        if (type === 'attributes' && (!observer.options.attributes || !observer.options.attributeFilter.includes(attributeName))) { continue; }
        if (type === 'childList' && !observer.options.childList) { continue; }
        observer.callback();
    }
}
class Element {
    constructor(tag, attributes = {}) {
        this.tagName = tag.toUpperCase(); this.attrs = {...attributes}; this.children = [];
        this.tabIndex = Number(attributes.tabindex ?? (/^(button|input|select|textarea)$/i.test(tag) || attributes.href ? 0 : -1));
        this.visible = this.isConnected = true;
        if (attributes.id) { ids.set(attributes.id, this); }
    }
    append(child) { child.parentElement = this; this.children.push(child); notify(this, 'childList'); }
    replace(children = []) { this.children.forEach(child => {child.isConnected = false;}); this.children = []; children.forEach(child => this.append(child)); notify(this, 'childList'); }
    contains(node) { return node === this || this.children.some(child => child.contains(node)); }
    hasAttribute(name) { return Object.hasOwn(this.attrs, name); }
    getAttribute(name) { return this.attrs[name] ?? null; }
    setAttribute(name, value) { if (this.attrs[name] !== value) { this.attrs[name] = value; notify(this, 'attributes', name); } }
    removeAttribute(name) { if (this.hasAttribute(name)) { delete this.attrs[name]; notify(this, 'attributes', name); } }
    get hidden() { return this.hasAttribute('hidden'); }
    get disabled() { return this.hasAttribute('disabled'); }
    get classList() {
        return {
            contains: name => (this.attrs.class || '').split(/\s+/).includes(name),
            add: name => this.setAttribute('class', [...new Set((this.attrs.class || '').split(/\s+/).filter(Boolean).concat(name))].join(' ')),
            remove: name => this.setAttribute('class', (this.attrs.class || '').split(/\s+/).filter(value => value !== name).join(' '))
        };
    }
    getClientRects() {
        for (let node = this; node; node = node.parentElement) {
            if (!node.visible || node.hidden || node.classList.contains('hidden')) { return []; }
        }
        return [{}];
    }
    closest(selector) {
        for (let node = this; node; node = node.parentElement) { if (selector === '[inert]' && node.hasAttribute('inert')) { return node; } }
        return null;
    }
    descendants() { return this.children.flatMap(child => [child, ...child.descendants()]); }
    querySelector(selector) {
        if (/^#[\w-]+$/.test(selector)) { return this.descendants().find(node => node.getAttribute('id') === selector.slice(1)) || null; }
        return this.descendants().find(node => node.closeControl) || null;
    }
    querySelectorAll() { return this.descendants().filter(child => /^(BUTTON|INPUT|SELECT|TEXTAREA)$/.test(child.tagName) || child.hasAttribute('href') || child.hasAttribute('tabindex')); }
    focus() {
        document.activeElement = this;
        for (const handler of events.focusin || []) { handler.callback({target:this}); }
    }
}
const body = document.body = new Element('body'), main = new Element('main'), opener = new Element('button');
const footer = new Element('footer'), panel = new Element('div', fixture.attributes);
const content = new Element('div', {id:'caMediaPanelContentArea'});
body.append(main); body.append(footer); body.append(panel); panel.append(content); main.append(opener); opener.focus();
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
    observe(target, options) { assert(target); this.target = target; this.options = options; this.active = true; }
    disconnect() { this.active = false; }
}
const context = vm.createContext({window:{}, document, jQuery, caUI, MutationObserver});
vm.runInContext(fixture.helper, context);
for (const script of fixture.scripts) { vm.runInContext(script, context); }
const controller = context.caMediaPanel;
assert.equal(typeof controller?.showPanel, 'function');
assert(observers.some(observer => observer.target === panel && observer.options.subtree), 'The real footer must register the shared lifecycle adapter.');
assert(observers.some(observer => observer.target === content && observer.options.childList && !observer.options.subtree), 'PDF initialization must observe direct AJAX replacements, not every text-layer update.');
for (let cycle = 0; cycle < 2; cycle++) {
    opener.focus(); controller.showPanel('/synthetic-document');
    assert.equal(panel.getAttribute('aria-hidden'), 'false', 'Opening must activate the shared CSS scroll-lock selector.');
    assert.equal(panel.contains(document.activeElement), true, 'Focus must enter the dialog, including when prior content is retained.');
    assert.equal(main.hasAttribute('inert'), true);
    assert.equal(footer.hasAttribute('inert'), true);
    const close = new Element('button'), download = new Element('button'); close.closeControl = true;
    content.replace(); content.append(close); content.append(download);
    observers[0].callback(); assert.equal(document.activeElement, close, 'Loaded content must receive focus.');
    let trapped = false;
    for (const handler of events.keydown || []) {
        handler.callback({key:'Tab', shiftKey:true, target:close, preventDefault() { trapped = true; }});
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
function hydrate(tree) {
    const element = new Element(tree.tag, tree.attributes);
    tree.children.filter(child => !/^(script|style)$/i.test(child.tag)).forEach(child => element.append(hydrate(child)));
    return element;
}
const reader = hydrate(fixture.pdf), wrapper = new Element('div'), media = new Element('div'), outerClose = new Element('button');
outerClose.closeControl = true; media.append(reader); wrapper.append(media); wrapper.append(outerClose);
opener.focus(); controller.showPanel('/synthetic-pdf'); content.replace([wrapper]);
const node = id => reader.querySelector('#' + id);
assert.equal(node('pageNumber').getAttribute('aria-label'), 'Page number');
assert.equal(node('scaleSelect').getAttribute('aria-label'), node('scaleSelect').getAttribute('title'));
assert.equal(node('findInput').getAttribute('aria-label'), 'Find in document');
assert.equal(node('viewerContainer').getAttribute('role'), 'region');
assert.equal(node('viewFind').getAttribute('aria-controls'), 'findbar');
assert.equal(node('viewFind').getAttribute('aria-expanded'), 'false');
assert.equal(node('sidebarContainer').getAttribute('aria-hidden'), 'true');
assert(node('viewThumbnail').getClientRects().length, 'An offscreen sidebar still has CSS client rectangles.');
assert(node('viewThumbnail').closest('[inert]'), 'Collapsed sidebar controls must be excluded independently of layout rectangles.');
node('cursorHandTool').classList.add('toggled'); assert.equal(node('cursorHandTool').getAttribute('aria-pressed'), 'true');
node('cursorHandTool').classList.remove('toggled'); assert.equal(node('cursorHandTool').getAttribute('aria-pressed'), 'false');
for (const id of ['findMsg', 'findResultsCount']) {
    assert.equal(node(id).getAttribute('role'), 'status'); assert.equal(node(id).getAttribute('aria-live'), 'polite');
}
let nativeKeys = 0;
function key(type, key, nativeHandler, targetHandler) {
    let stopped = false, prevented = false;
    const event = {key, keyCode:{Escape:27, Tab:9, Enter:13, ' ':32}[key], target:document.activeElement,
        preventDefault() { prevented = true; }, stopPropagation() { stopped = true; }};
    for (const handler of events[type] || []) { if (handler.capture) { handler.callback(event); } }
    if (!stopped && targetHandler) { targetHandler(event); }
    for (const handler of events[type] || []) { if (!handler.capture && !stopped) { handler.callback(event); } }
    if (!stopped && nativeHandler) { nativeHandler(event); }
    return {stopped, prevented};
}
function nativeEscape() { if (panel.getAttribute('aria-hidden') === 'false') { controller.hidePanel(); } }
context.PDFViewerApplication = {
    overlayManager:{active:null}, page:1, pagesCount:3,
    pdfViewer:{isInPresentationMode:false, currentScaleValue:'page-fit', containsElement() { return true; }}
};
vm.runInContext(fixture.nativeKeydown, context);
for (const activationKey of ['Enter', ' ']) {
    node('viewFind').focus();
    let canceled = false;
    context.webViewerKeyDown({keyCode:activationKey === 'Enter' ? 13 : 32, preventDefault() { canceled = true; }});
    assert(canceled, 'The real legacy window shortcut would suppress button activation at page-fit.');
    let targetHandled = false;
    const outcome = key('keydown', activationKey, context.webViewerKeyDown, () => { targetHandled = true; });
    assert.equal(targetHandled, true, 'Native target key handlers must run before the document propagation guard.');
    assert.equal(outcome.stopped, true, 'Button activation must not reach the PDF window page shortcut.');
    assert.equal(outcome.prevented, false, 'Native button activation must retain its browser default action.');
}
node('viewerContainer').focus();
assert.equal(key('keydown', 'Enter', context.webViewerKeyDown).prevented, true, 'Page navigation must remain native when the document viewport has focus.');
outerClose.focus(); key('keydown', 'Tab');
assert.equal(document.activeElement, node('sidebarToggle'), 'The outer trap must skip the offscreen sidebar.');
reader.classList.add('sidebarOpen');
assert.equal(node('sidebarToggle').getAttribute('aria-expanded'), 'true');
assert.equal(node('sidebarContainer').getAttribute('aria-hidden'), 'false');
assert.equal(node('viewThumbnail').closest('[inert]'), null);
outerClose.focus(); key('keydown', 'Tab');
assert.equal(document.activeElement, node('viewThumbnail'), 'Reopened sidebar controls must rejoin native keyboard navigation.');
reader.classList.remove('sidebarOpen');
assert.equal(document.activeElement, node('sidebarToggle'), 'Closing the sidebar must move focus out of its inert content.');
node('findbar').classList.remove('hidden');
assert.equal(node('viewFind').getAttribute('aria-expanded'), 'true');
node('findInput').focus();
let outcome = key('keydown', 'Escape', () => { nativeKeys++; node('findbar').classList.add('hidden'); });
assert.equal(outcome.stopped, false); assert.equal(outcome.prevented, false, 'Native PDF keydown must remain untouched.');
key('keyup', 'Escape', nativeEscape);
assert.equal(panel.getAttribute('aria-hidden'), 'false', 'Escape must first close PDF search, not the reader.');
assert.equal(document.activeElement, node('viewFind'));
node('secondaryToolbar').classList.remove('hidden'); node('firstPage').focus();
assert.equal(node('secondaryToolbarToggle').getAttribute('aria-expanded'), 'true');
key('keydown', 'Escape', () => { nativeKeys++; node('secondaryToolbar').classList.add('hidden'); });
key('keyup', 'Escape', nativeEscape);
assert.equal(panel.getAttribute('aria-hidden'), 'false', 'Escape must first close Tools, not the reader.');
assert.equal(document.activeElement, node('secondaryToolbarToggle'));
assert.equal(node('secondaryToolbarToggle').getAttribute('aria-expanded'), 'false');
node('secondaryToolbar').classList.remove('hidden'); node('documentProperties').focus();
node('documentPropertiesOverlay').classList.remove('hidden'); node('overlayContainer').classList.remove('hidden');
assert.equal(node('documentPropertiesOverlay').getAttribute('role'), 'dialog');
assert.equal(node('documentPropertiesOverlay').getAttribute('aria-modal'), 'true');
assert.equal(document.activeElement, node('documentPropertiesClose'));
assert(node('pageNumber').closest('[inert]'), 'The outer toolbar must be inert while properties are open.');
assert.equal(key('keydown', 'Tab').prevented, true);
assert.equal(document.activeElement, node('documentPropertiesClose'));
node('secondaryToolbar').classList.add('hidden');
outcome = key('keydown', 'Escape', () => {
    nativeKeys++; node('overlayContainer').classList.add('hidden'); node('documentPropertiesOverlay').classList.add('hidden');
});
assert.equal(outcome.stopped, false); assert.equal(outcome.prevented, false);
key('keyup', 'Escape', nativeEscape);
assert.equal(panel.getAttribute('aria-hidden'), 'false');
assert.equal(document.activeElement, node('secondaryToolbarToggle'), 'Closing properties must return to a visible opener when Tools has closed.');
assert.equal(node('pageNumber').closest('[inert]'), null);
const activeObservers = () => observers.filter(observer => observer.active).length;
const beforePages = activeObservers(); node('viewer').append(new Element('span'));
assert.equal(activeObservers(), beforePages, 'Page text changes must not rebuild PDF observers.');
key('keydown', 'Escape', () => { nativeKeys++; }); key('keyup', 'Escape', nativeEscape);
assert.equal(panel.getAttribute('aria-hidden'), 'true'); assert.equal(document.activeElement, opener);
assert.equal(nativeKeys, 4);
content.replace(); assert.equal(activeObservers(), 2, 'Removing an AJAX viewer must disconnect its scoped observers.');
console.log('Media panel passed: real KCL footer/shared adapter, modal focus, PDF states/statuses, nested properties, native Escape handlers and bounded observers.');
JS;
$javascript = str_replace('FIXTURE', $fixture, $javascript);
$process = proc_open(['node', '-'], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Node.js is required for the panel lifecycle check.'); }
fwrite($pipes[0], $javascript); fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($process) !== 0) { throw new RuntimeException($stderr ?: $stdout); }
echo $stdout;
