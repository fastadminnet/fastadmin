'use strict';

// Run with: node tests/attachment-formatters.test.js
// The real AMD modules run in a VM; no network or script payload is executed.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

function loadBackendFormatters() {
    let tableOptions;
    const chain = new Proxy(function () {}, {
        get: (target, property) => property === 'bootstrapTable' ? options => {
            tableOptions = options;
            return chain;
        } : chain,
        apply: () => chain
    });
    const jquery = Object.assign(function () { return chain; }, {
        fn: {extend() {}, bootstrapTable: {defaults: {extend: {}}}},
        ajaxSetup() {}, extend: Object.assign
    });
    const layer = {config() {}};
    const context = vm.createContext({
        window: {}, document: {}, console, Config: {categoryList: {}},
        define(dependencies, factory) {
            context.module = factory(jquery, undefined, {}, layer, {});
        }
    });
    const jsRoot = path.resolve(process.argv[2] || path.join(__dirname, '../public/assets/js'));
    vm.runInContext(fs.readFileSync(path.join(jsRoot, 'fast.js'), 'utf8'), context);
    context.Fast = context.module;
    context.__ = context.module.lang;
    context.Fast.api.fixurl = value => '/admin/' + value;
    context.define = (dependencies, factory) => {
        context.module = factory(jquery, undefined, {}, {}, {
            api: {init() {}, bindevent() {}, events: {operate: {}}, formatter: {search: value => value}}
        });
    };
    vm.runInContext(fs.readFileSync(path.join(jsRoot, 'backend/general/attachment.js'), 'utf8'), context);
    context.module.index();
    return {
        formatters: context.module.api ? context.module.api.formatter : {},
        columns: tableOptions.columns[0]
    };
}

function loadFrontendFormatters() {
    let tableOptions;
    const chain = new Proxy(function () {}, {
        get: (target, property) => property === 'bootstrapTable' ? options => {
            tableOptions = options;
            return chain;
        } : chain,
        apply: () => chain
    });
    const jquery = Object.assign(function () { return chain; }, {
        fn: {extend() {}, bootstrapTable: {defaults: {extend: {}}}},
        ajaxSetup() {}, extend: Object.assign, each() {}
    });
    const table = {
        api: {
            init() {}, bindevent() {},
            formatter: {search: value => value, datetime: value => value}
        }
    };
    const context = vm.createContext({
        window: {location: {href: 'http://example.test/index/user/attachment'}}, document: {}, console,
        define(dependencies, factory) {
            context.module = factory(jquery, undefined, {}, {config() {}}, {});
        }
    });
    const jsRoot = path.resolve(process.argv[2] || path.join(__dirname, '../public/assets/js'));
    vm.runInContext(fs.readFileSync(path.join(jsRoot, 'fast.js'), 'utf8'), context);
    context.Fast = context.module;
    context.__ = context.module.lang;
    context.Fast.api.fixurl = value => '/index/' + value;
    context.require = (dependencies, callback) => {
        callback(dependencies[0] === 'upload' ? {api: {upload() {}}} : table);
    };
    context.define = (dependencies, factory) => {
        context.module = factory(jquery, undefined, {}, {api: {bindevent() {}}}, {});
    };
    vm.runInContext(fs.readFileSync(path.join(jsRoot, 'frontend/user.js'), 'utf8'), context);
    context.module.attachment();
    return {
        formatters: context.module.api ? context.module.api.formatter : {},
        columns: tableOptions.columns[0]
    };
}

const {formatters, columns} = loadBackendFormatters();
const {formatters: frontendFormatters, columns: frontendColumns} = loadFrontendFormatters();
function attachment(overrides) {
    return Object.assign({
        fullurl: '/uploads/example.png', url: '/uploads/example.png',
        thumb_style: '', mimetype: 'image/png', imagetype: 'png'
    }, overrides);
}

test('image preview encodes URL attributes and thumbnail styles', () => {
    const row = attachment({
        fullurl: '/uploads/picture.png"><span data-audit="url">',
        thumb_style: '"><span data-audit="style">'
    });
    const html = formatters.thumb('', row, 0);
    assert.equal((html.match(/<a\b/g) || []).length, 1);
    assert.equal((html.match(/<img\b/g) || []).length, 1);
    assert.ok(!html.includes('<span'));
    assert.ok(html.includes('&quot;&gt;&lt;span'));
});

test('attachment URL label is text and cannot create HTML', () => {
    const row = attachment({url: '<span data-audit="label">A&B</span>'});
    const html = formatters.url('', row, 0);
    assert.ok(!html.includes('<span'));
    assert.ok(html.includes('&lt;span'));
    assert.ok(html.includes('A&amp;B'));
});

test('icon suffix is encoded as one query parameter', () => {
    const row = attachment({mimetype: 'application/pdf', imagetype: 'pdf" data-audit="icon&other=1'});
    const html = formatters.thumb('', row, 0);
    assert.ok(!html.includes('data-audit="icon'));
    assert.ok(html.includes('suffix=pdf%22%20data-audit%3D%22icon%26other%3D1'));
});

test('active URL schemes and control-character variants cannot be links', () => {
    for (const fullurl of ['javascript:void(0)', 'JaVaScRiPt:void(0)', '\tjavascript:void(0)',
        'java\nscript:void(0)', 'data:text/html,example', 'vbscript:example']) {
        const row = attachment({fullurl});
        for (const name of ['url', 'thumb']) {
            const html = formatters[name]('', row, 0);
            assert.ok(!html.includes(fullurl), name + ' rejects ' + JSON.stringify(fullurl));
            assert.ok(html.includes('href=""'));
        }
    }
});

test('local and cloud attachment URLs keep their meaning', () => {
    for (const fullurl of ['/uploads/image.png', 'uploads/image.png',
        'https://cdn.example.test/image.png?size=100&mode=fit', '//cdn.example.test/image.png']) {
        const html = formatters.thumb('', attachment({fullurl}), 0);
        assert.ok(html.includes('href="' + fullurl.replace(/&/g, '&amp;') + '"'));
    }
});

test('editable metadata is escaped before display or search formatting', () => {
    const value = '<span data-audit="metadata">A&B</span>';
    for (const name of ['text', 'search', 'filename', 'mimetype']) {
        const html = formatters[name].call({field: 'filename'}, value, attachment(), 0);
        assert.ok(!html.includes('<span'));
        assert.ok(html.includes('&lt;span'));
        assert.ok(html.includes('A&amp;B'));
    }
    assert.equal(formatters.text(640), '640');
    assert.equal(formatters.text(null), '');
    for (const field of ['imagewidth', 'imageheight', 'imagetype', 'storage', 'filename', 'mimetype']) {
        const column = columns.find(column => column.field === field);
        assert.equal(typeof column.formatter, 'function', field + ' has a safe formatter');
        assert.ok(!column.formatter.call(column, value, attachment(), 0).includes('<span'));
    }
});

test('legacy encoded filenames display once while remaining HTML-safe', () => {
    const row = attachment({filename: 'audit&amp;A.png'});
    const filename = formatters.filename.call({field: 'filename'}, 'audit&amp;amp;A.png', row, 0);
    assert.ok(filename.includes('audit&amp;A.png'));
    assert.ok(!filename.includes('audit&amp;amp;A.png'));

    const encodedMarkup = formatters.filename.call(
        {field: 'filename'},
        '&lt;img src=x onerror=alert(1)&gt;',
        attachment({filename: '&lt;img src=x onerror=alert(1)&gt;'}),
        0
    );
    assert.ok(encodedMarkup.includes('&lt;img'));
    assert.ok(!encodedMarkup.includes('<img'));
});

test('frontend user attachment preview encodes the same stored values', () => {
    const row = attachment({
        fullurl: '/missing.png"><span data-audit="frontend">',
        thumb_style: '" onerror="alert(1)'
    });
    const html = frontendFormatters.thumb('', row, 0);
    assert.equal((html.match(/<a\b/g) || []).length, 1);
    assert.equal((html.match(/<img\b/g) || []).length, 1);
    assert.ok(!html.includes('<span'));
    assert.ok(!html.includes('onerror="'));
    assert.ok(html.includes('&quot;&gt;&lt;span'));
});

test('frontend user attachment links reject active schemes', () => {
    for (const fullurl of ['javascript:void(0)', 'data:text/html,example', 'java\nscript:void(0)']) {
        const html = frontendFormatters.thumb('', attachment({fullurl}), 0);
        assert.ok(html.includes('href=""'));
        assert.ok(!html.includes(fullurl));
    }
});

test('frontend metadata is escaped without double-encoding upload filenames', () => {
    const filename = frontendFormatters.filename.call(
        {field: 'filename'}, 'audit&amp;amp;A.png', attachment({filename: 'audit&amp;A.png'}), 0
    );
    assert.ok(filename.includes('audit&amp;A.png'));
    assert.ok(!filename.includes('audit&amp;amp;A.png'));

    const value = '<span data-audit="frontend-metadata">A&B</span>';
    const mimetype = frontendFormatters.mimetype.call(
        {field: 'mimetype'}, value, attachment({mimetype: value}), 0
    );
    assert.ok(!mimetype.includes('<span'));
    assert.ok(mimetype.includes('&lt;span'));
    for (const field of ['imagewidth', 'imageheight', 'filename', 'mimetype']) {
        const column = frontendColumns.find(column => column.field === field);
        assert.equal(typeof column.formatter, 'function', field + ' has a frontend formatter');
    }
});
