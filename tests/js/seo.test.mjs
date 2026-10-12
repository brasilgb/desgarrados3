import test from 'node:test';
import assert from 'node:assert/strict';
import { absoluteUrl, canonicalPath, metaDescription } from '../../resources/js/lib/seo.ts';

test('canonical drops filters and keeps only pages beyond the first', () => {
    assert.deepEqual(canonicalPath('/cadernos/historias'), { path: '/cadernos/historias', filtered: false });
    assert.deepEqual(canonicalPath('/cadernos/historias?page=1'), { path: '/cadernos/historias', filtered: false });
    assert.deepEqual(canonicalPath('/cadernos/historias?page=3'), { path: '/cadernos/historias?page=3', filtered: false });
    assert.deepEqual(canonicalPath('/cadernos/historias?q=causo&page=2'), { path: '/cadernos/historias?page=2', filtered: true });
    assert.deepEqual(canonicalPath('/minha-regiao?state=RS&municipality_code=4314902'), { path: '/minha-regiao', filtered: true });
    assert.deepEqual(canonicalPath('/nossa-terra?ferramenta=arroba'), { path: '/nossa-terra', filtered: true });
    assert.deepEqual(canonicalPath('/?q=chimarrão'), { path: '/', filtered: true });
});

test('absolute URLs use the application address', () => {
    assert.equal(absoluteUrl('https://desgarrados.com.br', '/sobre'), 'https://desgarrados.com.br/sobre');
    assert.equal(absoluteUrl('https://desgarrados.com.br', 'images/x.jpg'), 'https://desgarrados.com.br/images/x.jpg');
    assert.equal(absoluteUrl('https://desgarrados.com.br', 'https://cdn.example/x.jpg'), 'https://cdn.example/x.jpg');
});

test('meta descriptions are plain text and at most 160 characters', () => {
    assert.equal(metaDescription('<p>Um  causo <strong>bom</strong>.</p>'), 'Um causo bom.');
    assert.equal(metaDescription(''), null);
    assert.equal(metaDescription(null), null);
    const long = metaDescription('palavra '.repeat(40));
    assert.ok(long.length <= 160 && long.endsWith('…'));
});
