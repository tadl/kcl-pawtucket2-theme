<?php
/** Execute the owned navigation script with synthetic native DOM/media events. */
$javascript = <<<'JS'
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }

function fixture({mobile = true, ready = 'complete', markup = true, button = true, list = true, media = true} = {}) {
    const trace = [];
    class Element {
        constructor(name, parent = null, attributes = {}) {
            this.name = name; this.parentElement = parent; this.attributes = {...attributes};
            this.listeners = {}; this.children = []; this.isHidden = false;
            parent?.children.push(this);
        }
        addEventListener(type, callback, options) { (this.listeners[type] ||= []).push({callback, options}); }
        setAttribute(name, value) { this.attributes[name] = String(value); }
        getAttribute(name) { return this.attributes[name] ?? null; }
        get hidden() { return this.isHidden; }
        set hidden(value) { this.isHidden = value; trace.push('hidden:' + this.name + '=' + value); }
        contains(other) { return other === this || this.children.some(child => child.contains(other)); }
        focus() { document.activeElement = this; trace.push('focus:' + this.name); }
        closest(selector) {
            if (selector === 'a[href]' && this.attributes.href) { return this; }
            return this.parentElement?.closest(selector) ?? null;
        }
        querySelector(selector) {
            if (selector === '.kcl-menu-toggle') { return button ? toggle : null; }
            if (selector === '#kcl-primary-links') { return list ? links : null; }
            if (selector === 'a[href]') { return first; }
            return null;
        }
        dispatch(type, properties = {}) {
            const event = {
                type, target:this, defaultPrevented:false, propagationStopped:false,
                preventDefault() { this.defaultPrevented = true; },
                stopPropagation() { this.propagationStopped = true; },
                ...properties
            };
            for (let node = this; node; node = node.parentElement) {
                for (const {callback} of node.listeners[type] || []) { callback(event); }
                if (event.propagationStopped) { break; }
            }
            return event;
        }
    }
    const document = {
        readyState:ready, activeElement:null, listeners:{},
        querySelector(selector) { return markup && selector === '.kcl-nav' ? nav : null; },
        addEventListener(type, callback, options) { (this.listeners[type] ||= []).push({callback, options}); },
        ready() {
            this.readyState = 'interactive';
            for (const {callback} of this.listeners.DOMContentLoaded || []) { callback(); }
            this.listeners.DOMContentLoaded = [];
        }
    };
    const nav = new Element('nav');
    const toggle = new Element('toggle', nav, {type:'button', 'aria-controls':'kcl-primary-links', 'aria-expanded':'false'});
    toggle.hidden = true;
    const links = new Element('links', nav, {id:'kcl-primary-links'});
    const first = new Element('first', links, {href:'/Front/Index#decades'});
    const child = new Element('label', first);
    const second = new Element('second', links, {href:'/Browse/objects?clear=1'});
    const outside = new Element('outside');
    document.activeElement = outside;
    const query = {
        matches:mobile, listeners:[],
        addEventListener(type, callback) { assert.equal(type, 'change'); this.listeners.push(callback); },
        change(matches) { this.matches = matches; this.listeners.forEach(callback => callback({matches})); }
    };
    let mediaCalls = 0;
    const window = media ? {matchMedia(value) {
        assert.equal(value, '(max-width: 767px)'); mediaCalls++; return query;
    }} : {};
    return {nav, toggle, links, first, child, second, outside, query, document, trace,
        run() { vm.runInNewContext(source, {document, window}); }, mediaCalls:() => mediaCalls};
}

const mobile = fixture();
check(mobile.toggle.hidden && !mobile.links.hidden, 'Unenhanced markup must show links and hide the toggle.');
mobile.run();
check(!mobile.toggle.hidden && mobile.links.hidden && mobile.toggle.getAttribute('aria-expanded') === 'false', 'Mobile initialization must reveal the toggle and close links.');
check(mobile.toggle.getAttribute('type') === 'button' && mobile.toggle.getAttribute('aria-controls') === 'kcl-primary-links', 'The native button and control relationship must be preserved.');
check(!mobile.toggle.listeners.keydown && !mobile.toggle.listeners.keyup, 'Native Enter/Space activation must use click, without duplicate key handlers.');
mobile.toggle.focus();
const click = mobile.toggle.dispatch('click', {detail:1});
check(!mobile.links.hidden && mobile.toggle.getAttribute('aria-expanded') === 'true' && !click.defaultPrevented, 'Pointer activation must expand using the native click.');
mobile.toggle.dispatch('click', {detail:0});
check(mobile.links.hidden && mobile.toggle.getAttribute('aria-expanded') === 'false', 'A keyboard-generated native click must close the menu.');
const closedEscape = mobile.toggle.dispatch('keydown', {key:'Escape'});
check(!closedEscape.defaultPrevented, 'Escape on a closed menu must remain untouched.');
mobile.toggle.dispatch('click', {detail:0});
mobile.first.focus();
const tab = mobile.first.dispatch('keydown', {key:'Tab'});
check(!tab.defaultPrevented && mobile.document.activeElement === mobile.first, 'Ordinary Tab navigation must not be trapped.');
const escape = mobile.first.dispatch('keydown', {key:'Escape'});
check(mobile.links.hidden && mobile.toggle.getAttribute('aria-expanded') === 'false' && mobile.document.activeElement === mobile.toggle, 'Escape within the open menu must close it and restore button focus.');
check(escape.defaultPrevented && escape.propagationStopped, 'Handled Escape must not also activate another Escape handler.');
mobile.toggle.dispatch('click');
const href = mobile.first.getAttribute('href');
mobile.first.focus();
const linkClick = mobile.child.dispatch('click');
check(mobile.links.hidden && mobile.toggle.getAttribute('aria-expanded') === 'false', 'Activating a nested link label must close mobile navigation.');
check(mobile.document.activeElement === mobile.toggle, 'Closing on link activation must not leave focus in hidden links if navigation is canceled or opens another tab.');
check(!linkClick.defaultPrevented && !linkClick.propagationStopped && mobile.first.getAttribute('href') === href, 'Link activation must preserve normal navigation and the original destination.');
mobile.toggle.dispatch('click');
const outsideClick = mobile.outside.dispatch('click');
check(!mobile.links.hidden && !outsideClick.defaultPrevented, 'Unrelated clicks must remain untouched.');
mobile.first.focus();
mobile.query.change(false);
check(!mobile.links.hidden && mobile.toggle.getAttribute('aria-expanded') === 'false' && mobile.document.activeElement === mobile.first, 'Entering desktop must show links and retain link focus.');
mobile.trace.length = 0;
mobile.query.change(true);
check(mobile.links.hidden && mobile.document.activeElement === mobile.toggle, 'Entering mobile must move focus out of links before closing them.');
check(mobile.trace.indexOf('focus:toggle') < mobile.trace.indexOf('hidden:links=true'), 'Focus must move before a resize hides the focused link.');
mobile.trace.length = 0;
mobile.query.change(false);
check(!mobile.links.hidden && mobile.document.activeElement === mobile.first, 'Entering desktop with button focus must focus the first link.');
check(mobile.trace.indexOf('hidden:links=false') < mobile.trace.indexOf('focus:first'), 'Desktop links must be visible before receiving focus.');
mobile.query.change(true);
check(mobile.links.hidden && mobile.toggle.getAttribute('aria-expanded') === 'false', 'Returning to mobile must reset to the closed state.');
check(!mobile.nav.getAttribute('role') && !mobile.links.getAttribute('role') && !mobile.first.getAttribute('role'), 'Ordinary navigation must not gain menu/menuitem roles.');

const desktop = fixture({mobile:false}); desktop.run();
check(!desktop.links.hidden && !desktop.toggle.hidden && desktop.toggle.getAttribute('aria-expanded') === 'false', 'Desktop initialization must keep links visible; desktop toggle visibility belongs to CSS.');
desktop.toggle.dispatch('click');
const desktopEscape = desktop.first.dispatch('keydown', {key:'Escape'});
check(!desktop.links.hidden && !desktopEscape.defaultPrevented, 'Programmatic desktop activation and Escape must not collapse the links.');

const delayed = fixture({ready:'loading'}); delayed.run();
check(delayed.toggle.hidden && !delayed.links.hidden && delayed.mediaCalls() === 0, 'Scripts in the head must wait for DOM ready without hiding fallback links.');
delayed.document.ready();
check(!delayed.toggle.hidden && delayed.links.hidden && delayed.mediaCalls() === 1, 'DOM ready must initialize the complete mobile navigation.');
for (const options of [{markup:false}, {button:false}, {list:false}, {media:false}]) {
    const missing = fixture(options); missing.run();
    check(missing.toggle.hidden && !missing.links.hidden, 'Missing markup or matchMedia must leave progressive fallback navigation unchanged.');
}
console.log('Navigation passed: ' + checks + ' assertions (native activation, mobile/desktop state, focus, resize and progressive fallback).');
JS;
$process = proc_open(['node', '-e', $javascript, dirname(__DIR__).'/assets/pawtucket/js/navigation.js'],
	[0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Could not execute navigation regression.'); }
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($process) !== 0) { throw new RuntimeException('Navigation regression failed: '.$output.$error); }
echo $output;
