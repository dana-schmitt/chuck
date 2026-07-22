import './stimulus_bootstrap.js';

// styles/app.css is linked directly in base.html.twig's <head> instead of imported here -
// AssetMapper's importmap() otherwise represents CSS imports as a data: URI "script", which a
// strict Content-Security-Policy (no unsafe data: script-src) correctly refuses to execute.
