import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateRegionalTool, regionalToolResultKind } from '../../resources/js/lib/regional-calculations.js';

test('separates unit conversions from estimates using visitor inputs', () => {
    for (const id of ['area', 'weight', 'volume']) assert.equal(regionalToolResultKind(id), 'conversion');
    for (const id of ['arroba', 'fuel', 'travel', 'wool', 'poultry', 'swine', 'grapes', 'seed', 'stocking', 'milk', 'feed', 'orchard', 'honey', 'moving']) assert.equal(regionalToolResultKind(id), 'estimate');
});

test('converts hectares to square metres and square kilometres', () => {
    const result = calculateRegionalTool('area', { hectares: 1 });
    assert.deepEqual(result.lines.slice(0, 3), ['1 ha', '10.000 m²', '0,01 km²']);
});

test('converts live cattle weight to arrobas using the entered carcass yield', () => {
    const result = calculateRegionalTool('arroba', { basis: 'live', weight_kg: 600, yield_percent: 50, heads: 10, price_per_arroba: 300 });
    assert.equal(result.lines[0], '300 kg de carcaça');
    assert.equal(result.lines[1], '20 arrobas de carcaça');
    assert.match(result.lines[3], /200 arrobas no lote/);
    assert.match(result.note, /B3.*15 kg/);
    assert.match(result.note, /não é cotação/);
});

test('doubles distance and fuel costs for a round trip', () => {
    const result = calculateRegionalTool('travel', { distance_km: 100, km_per_liter: 10, fuel_price: 6, tolls: 5, round_trip: 'yes' });
    assert.deepEqual(result.lines.slice(0, 3), ['200 km percorridos', '20 L de combustível', 'R$ 120,00']);
    assert.equal(result.lines[4], 'Total estimado: R$ 130,00');
});

test('rejects zero consumption and negative inputs', () => {
    assert.throws(() => calculateRegionalTool('fuel', { distance_km: 20, km_per_liter: 0, fuel_price: 6 }), /maior que zero/);
    assert.throws(() => calculateRegionalTool('area', { hectares: -1 }), /igual ou maior que zero/);
});

test('validates biological limits and unknown units', () => {
    assert.throws(() => calculateRegionalTool('arroba', { basis: 'live', weight_kg: 300, yield_percent: 110, heads: 1, price_per_arroba: 250 }), /até 100%/);
    assert.throws(() => calculateRegionalTool('weight', { value: 5, unit: 'stone' }), /unidade de peso válida/);
    assert.throws(() => calculateRegionalTool('poultry', { birds: 10, deaths: 11, feed_kg: 2, live_kg: 1, feed_price: 5 }), /não podem exceder/);
});

test('calculates moving estimate from explicit user-provided parameters', () => {
    const result = calculateRegionalTool('moving', { distance_km: 100, volume_m3: 20, rate_per_m3_km: 0.5, fixed_cost: 100 });
    assert.equal(result.lines[0], 'R$ 1.100,00');
});

test('converts US gallons to litres without confusing metric units', () => {
    const result = calculateRegionalTool('volume', { value: 1, unit: 'gal' });
    assert.equal(result.lines[0], '3,79 L');
});

test('converts hectares to commonly used alqueire variants with explicit labels', () => {
    const result = calculateRegionalTool('area', { hectares: 4.84 });
    assert.equal(result.lines[4], '2 alqueires paulistas');
    assert.equal(result.lines[6], '1 alqueire mineiro/geométrico');
});

test('accepts area input in square metres and either alqueire convention', () => {
    const squareMetres = calculateRegionalTool('area', { hectares: 10_000, unit: 'm2' });
    const paulista = calculateRegionalTool('area', { hectares: 1, unit: 'alqueire_paulista' });
    const mineiro = calculateRegionalTool('area', { hectares: 1, unit: 'alqueire_mineiro' });
    const north = calculateRegionalTool('area', { hectares: 1, unit: 'alqueire_272' });
    const baiano = calculateRegionalTool('area', { hectares: 1, unit: 'alqueire_baiano' });
    assert.equal(squareMetres.lines[1], '10.000 m²');
    assert.equal(paulista.lines[4], '1 alqueire paulista');
    assert.equal(north.lines[5], '1 alqueire (75 × 75 braças)');
    assert.equal(mineiro.lines[6], '1 alqueire mineiro/geométrico');
    assert.equal(baiano.lines[7], '1 alqueire baiano');
    assert.throws(() => calculateRegionalTool('area', { hectares: 1, unit: 'regional' }), /unidade de área válida/);
});

test('converts entered carcass arrobas to kilograms and values the lot', () => {
    const result = calculateRegionalTool('arroba', { basis: 'carcass', weight_kg: 20, weight_unit: 'arroba', yield_percent: 50, heads: 3, price_per_arroba: 300 });
    assert.equal(result.lines[0], '300 kg de carcaça');
    assert.equal(result.lines[1], '20 arrobas de carcaça');
    assert.match(result.lines[3], /60 arrobas no lote/);
    assert.throws(() => calculateRegionalTool('arroba', { basis: 'live', weight_kg: 20, weight_unit: 'arroba', yield_percent: 50, heads: 1, price_per_arroba: 300 }), /Arroba é unidade de carcaça/);
});

test('estimates stocking capacity using the declared 450 kg animal-unit convention', () => {
    const result = calculateRegionalTool('stocking', { area_ha: 20, au_per_ha: 1.5, animal_weight_kg: 300 });
    assert.equal(result.lines[0], '30 UA');
    assert.equal(result.lines[1], '45 animais equivalentes');
});

test('calculates swine daily gain, feed use, revenue, and partial margin', () => {
    const result = calculateRegionalTool('swine', { animals: 10, initial_weight_kg: 25, final_weight_kg: 125, days: 100, fcr: 2.5, feed_price: 2, piglet_cost: 100, sale_price_per_kg: 8 });
    assert.equal(result.lines[0], '1 kg/animal/dia de ganho médio');
    assert.equal(result.lines[1], '2.500 kg de ração estimada');
    assert.match(result.lines[4], /Margem parcial após ração e compra dos animais: R\$ 4\.000,00/);
    assert.throws(() => calculateRegionalTool('swine', { animals: 1, initial_weight_kg: 30, final_weight_kg: 30, days: 100, fcr: 2.5, feed_price: 2, piglet_cost: 100, sale_price_per_kg: 8 }), /peso final acima/);
});

test('estimates milk volume, gross revenue, and partial margin separately', () => {
    const result = calculateRegionalTool('milk', { animals: 5, liters_per_animal_day: 12, days: 30, price_per_liter: 2.5, variable_cost: 600 });
    assert.equal(result.lines[0], '1.800 L no período');
    assert.equal(result.lines[1], 'Receita bruta: R$ 4.500,00');
    assert.equal(result.lines[2], 'Margem parcial após custos variáveis informados: R$ 3.900,00');
});

test('estimates feed for a batch from entered daily ration and duration', () => {
    const result = calculateRegionalTool('feed', { animals: 100, feed_per_animal_day: 0.2, days: 30, price_per_kg: 1.5 });
    assert.deepEqual(result.lines, ['600 kg no lote', '0,6 t no lote', 'Custo estimado: R$ 900,00']);
});

test('calculates orchard production for apples and peaches without implying net profit', () => {
    const result = calculateRegionalTool('orchard', { fruit: 'maçã', trees: 100, kg_per_tree: 20, price_per_kg: 3, variable_cost: 2000 });
    assert.equal(result.lines[0], '2.000 kg');
    assert.equal(result.lines[1], 'Receita bruta: R$ 6.000,00');
    assert.match(result.note, /não é lucro líquido/);
});

test('estimates honey production and validates the selected fruit', () => {
    const result = calculateRegionalTool('honey', { hives: 20, kg_per_hive: 15, price_per_kg: 25, variable_cost: 1000 });
    assert.equal(result.lines[0], '300 kg no apiário');
    assert.equal(result.lines[1], 'Receita bruta: R$ 7.500,00');
    assert.throws(() => calculateRegionalTool('orchard', { fruit: 'uva', trees: 1, kg_per_tree: 1, price_per_kg: 1, variable_cost: 0 }), /Selecione maçã ou pêssego/);
});
