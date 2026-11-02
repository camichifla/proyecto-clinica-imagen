const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const noop = () => {};
const documento = new Proxy({}, { get: () => () => [] });
const ventana = { addEventListener: noop, location: {}, matchMedia: () => ({ matches: false, addEventListener: noop }) };
const Observador = class { observe() {} disconnect() {} };
const contexto = vm.createContext({ document: documento, window: ventana, console, ResizeObserver: Observador, MutationObserver: Observador, IntersectionObserver: Observador });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../assets/js/form-api.js'), 'utf8'), contexto, { filename: 'form-api.js' });
const f = vm.runInContext('({ normalizar, coincidePalabras, parseISO, aISO, pad, fechaHora })', contexto);

test('normalizar quita tildes, mayúsculas y espacios', () => {
    assert.strictEqual(f.normalizar('  ÁÉÍ Ñandú '), 'aei nandu');
    assert.strictEqual(f.normalizar(null), '');
});

test('coincidePalabras exige todas las palabras en cualquier orden', () => {
    assert.ok(f.coincidePalabras('perez juan', ['Juan', 'Pérez', '1234567']));
    assert.ok(!f.coincidePalabras('perez maria', ['Juan', 'Pérez']));
    assert.ok(f.coincidePalabras('', ['x']));
});

test('pad, parseISO y aISO son inversos', () => {
    assert.strictEqual(f.pad(7), '07');
    const d = f.parseISO('2026-03-09');
    assert.strictEqual([d.getFullYear(), d.getMonth(), d.getDate()].join('-'), '2026-2-9');
    assert.strictEqual(f.aISO(d), '2026-03-09');
});

test('fechaHora formatea fecha y hora', () => {
    assert.strictEqual(f.fechaHora('2026-03-09 14:30:00'), '09/03/2026 14:30');
    assert.strictEqual(f.fechaHora('2026-03-09'), '09/03/2026');
});
