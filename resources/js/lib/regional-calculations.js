const number = (values, key, label) => {
    if (values[key] === '' || values[key] === null || values[key] === undefined) throw new Error(`Informe ${label.toLocaleLowerCase('pt-BR')}.`);
    const value = Number(values[key]);
    if (!Number.isFinite(value) || value < 0) throw new Error(`${label} precisa ser um número igual ou maior que zero.`);
    return value;
};

const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
const quantity = (value, unit, digits = 2) => `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: digits }).format(value)} ${unit}`;
const quantityWithNoun = (value, singular, plural) => `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(value)} ${Math.abs(value - 1) < 0.00001 ? singular : plural}`;

export function regionalToolResultKind(id) {
    return ['area', 'weight', 'volume'].includes(id) ? 'conversion' : 'estimate';
}

export function calculateRegionalTool(id, values) {
    if (id === 'area') {
        const amount = number(values, 'hectares', 'A área');
        const toHectares = { ha: 1, m2: 0.0001, alqueire_paulista: 2.42, alqueire_272: 2.72, alqueire_mineiro: 4.84, alqueire_baiano: 9.68 };
        const unit = values.unit ?? 'ha';
        if (!(unit in toHectares)) throw new Error('Selecione uma unidade de área válida.');
        const hectares = amount * toHectares[unit];
        return { title: 'Conversão de área', lines: [quantity(hectares, 'ha'), quantity(hectares * 10_000, 'm²'), quantity(hectares / 100, 'km²'), quantity(hectares * 2.4710538147, 'acres'), quantityWithNoun(hectares / 2.42, 'alqueire paulista', 'alqueires paulistas'), quantityWithNoun(hectares / 2.72, 'alqueire (75 × 75 braças)', 'alqueires (75 × 75 braças)'), quantityWithNoun(hectares / 4.84, 'alqueire mineiro/geométrico', 'alqueires mineiros/geométricos'), quantityWithNoun(hectares / 9.68, 'alqueire baiano', 'alqueires baianos')], note: 'Conversões matemáticas: 1 ha = 10.000 m². Alqueire paulista = 2,42 ha; alqueire de 75 × 75 braças = 2,72 ha; alqueire mineiro/geométrico = 4,84 ha; alqueire baiano = 9,68 ha. As medidas variam por região e costume: confirme a convenção local e a unidade indicada no documento do imóvel.' };
    }
    if (id === 'weight') {
        const value = number(values, 'value', 'O peso');
        const toKg = { kg: 1, g: 0.001, t: 1000, lb: 0.45359237 };
        if (!(values.unit in toKg)) throw new Error('Selecione uma unidade de peso válida.');
        const kg = value * toKg[values.unit];
        return { title: 'Conversão de peso', lines: [quantity(kg, 'kg'), quantity(kg / 1000, 't'), quantity(kg * 1000, 'g'), quantity(kg / 0.45359237, 'lb')], note: 'Libra internacional (avoirdupois); valores arredondados para facilitar a leitura.' };
    }
    if (id === 'volume') {
        const value = number(values, 'value', 'O volume');
        const toLiters = { l: 1, ml: 0.001, m3: 1000, gal: 3.785411784 };
        if (!(values.unit in toLiters)) throw new Error('Selecione uma unidade de volume válida.');
        const liters = value * toLiters[values.unit];
        return { title: 'Conversão de volume', lines: [quantity(liters, 'L'), quantity(liters * 1000, 'mL'), quantity(liters / 1000, 'm³'), quantity(liters / 3.785411784, 'galões americanos')], note: 'Galão líquido americano (US liquid gallon). Medidas de capacidade podem variar conforme o uso e a região.' };
    }
    if (id === 'arroba') {
        const weight = number(values, 'weight_kg', 'O peso');
        const heads = number(values, 'heads', 'O número de animais');
        const yieldPercent = number(values, 'yield_percent', 'O rendimento');
        const weightUnit = values.weight_unit ?? 'kg';
        if (values.basis === 'live' && weightUnit === 'arroba') throw new Error('Arroba é unidade de carcaça; informe o peso vivo em kg.');
        if (!['kg', 'arroba'].includes(weightUnit)) throw new Error('Selecione kg ou arrobas de carcaça.');
        if (values.basis === 'live' && (yieldPercent === 0 || yieldPercent > 100)) throw new Error('O rendimento deve ser maior que zero e até 100%.');
        const carcassKg = values.basis === 'live' ? weight * yieldPercent / 100 : weight * (weightUnit === 'arroba' ? 15 : 1);
        const arrobas = carcassKg / 15;
        const price = number(values, 'price_per_arroba', 'O preço por arroba');
        return { title: 'Estimativa de peso e valor', lines: [quantity(carcassKg, 'kg de carcaça'), quantity(arrobas, 'arrobas de carcaça'), money(arrobas * price), `${quantity(arrobas * heads, 'arrobas no lote')} · ${money(arrobas * price * heads)} no lote de ${quantity(heads, 'cabeças', 0)}`], note: `${values.basis === 'live' ? `Estimativa usando rendimento informado de ${yieldPercent}%. ` : ''}Referência: especificação do contrato Boi Gordo da B3, que define arroba líquida como 15 kg. Preço inserido por você, não é cotação. Confirme o padrão de pagamento combinado na sua região.` };
    }
    if (id === 'travel' || id === 'fuel') {
        const distance = number(values, 'distance_km', 'A distância');
        const consumption = number(values, 'km_per_liter', 'O consumo');
        const price = number(values, 'fuel_price', 'O preço do combustível');
        const legs = values.round_trip === 'yes' ? 2 : 1;
        if (consumption === 0) throw new Error('O consumo precisa ser maior que zero.');
        const totalDistance = distance * legs;
        const liters = totalDistance / consumption;
        const tolls = number(values, 'tolls', 'Os pedágios');
        return { title: id === 'travel' ? 'Estimativa da viagem' : 'Custo de combustível', lines: [quantity(totalDistance, 'km percorridos'), quantity(liters, 'L de combustível'), money(liters * price), ...(id === 'travel' ? [`Pedágios informados: ${money(tolls)}`, `Total estimado: ${money(liters * price + tolls * legs)}`] : [])], note: 'Estimativa calculada com distância, consumo e preço informados. Não considera trânsito, relevo ou variação no preço.' };
    }
    if (id === 'moving') {
        const distance = number(values, 'distance_km', 'A distância');
        const volume = number(values, 'volume_m3', 'O volume');
        const rate = number(values, 'rate_per_m3_km', 'A tarifa');
        const fixed = number(values, 'fixed_cost', 'O custo adicional');
        return { title: 'Estimativa de mudança', lines: [money(distance * volume * rate + fixed), `${quantity(volume, 'm³')} × ${quantity(distance, 'km')} × ${money(rate)} por m³/km + ${money(fixed)} de custos adicionais`], note: 'Simulação matemática com tarifas inseridas por você. Peça orçamento a transportadoras; não inclui seguro, escadas, embalagem ou taxas não informadas.' };
    }
    if (id === 'wool') {
        const sheep = number(values, 'sheep', 'O número de ovinos');
        const fleece = number(values, 'fleece_kg', 'A lã por animal');
        const price = number(values, 'price_per_kg', 'O preço por kg');
        return { title: 'Estimativa de produção de lã', lines: [quantity(sheep * fleece, 'kg de lã no lote'), money(sheep * fleece * price)], note: 'Estimativa bruta baseada na produção e no preço por kg informados. Não considera rendimento de lavagem, classificação, perdas ou custos.' };
    }
    if (id === 'stocking') {
        const area = number(values, 'area_ha', 'A área');
        const capacity = number(values, 'au_per_ha', 'A lotação');
        const animalWeight = number(values, 'animal_weight_kg', 'O peso médio');
        if (animalWeight === 0) throw new Error('O peso médio precisa ser maior que zero.');
        const animalUnits = area * capacity;
        return { title: 'Estimativa de lotação', lines: [quantity(animalUnits, 'UA'), quantity(animalUnits * 450 / animalWeight, 'animais equivalentes', 0)], note: 'Referência de cálculo: 1 unidade animal (UA) = 450 kg de peso vivo. Lotação por hectare foi informada por você; o resultado não é recomendação de manejo.' };
    }
    if (id === 'poultry') {
        const feed = number(values, 'feed_kg', 'A ração consumida');
        const live = number(values, 'live_kg', 'O peso vivo produzido');
        const feedPrice = number(values, 'feed_price', 'O preço da ração');
        const birds = number(values, 'birds', 'O número de aves alojadas');
        const deaths = number(values, 'deaths', 'As perdas');
        if (live === 0 || birds === 0) throw new Error('Informe peso produzido e aves alojadas maiores que zero.');
        if (deaths > birds) throw new Error('As perdas não podem exceder as aves alojadas.');
        return { title: 'Indicadores do lote de aves', lines: [quantity(feed / live, 'kg ração/kg produzido'), quantity(feed, 'kg de ração consumida'), money(feed * feedPrice), `${quantity(deaths / birds * 100, '% de mortalidade')} · ${quantity(birds - deaths, 'ave restante', 0)}`], note: 'Indicadores calculados com dados informados.' };
    }
    if (id === 'swine') {
        const animals = number(values, 'animals', 'O número de suínos');
        const initialWeight = number(values, 'initial_weight_kg', 'O peso inicial');
        const finalWeight = number(values, 'final_weight_kg', 'O peso final');
        const days = number(values, 'days', 'Os dias de alojamento');
        const fcr = number(values, 'fcr', 'A conversão alimentar');
        const feedPrice = number(values, 'feed_price', 'O preço da ração');
        const pigletCost = number(values, 'piglet_cost', 'O custo inicial por animal');
        const salePrice = number(values, 'sale_price_per_kg', 'O preço de venda por kg');
        if (animals === 0 || days === 0 || finalWeight <= initialWeight) throw new Error('Informe animais e dias maiores que zero e peso final acima do inicial.');
        const gain = finalWeight - initialWeight;
        const feedKg = gain * fcr * animals;
        const feedCost = feedKg * feedPrice;
        const revenue = finalWeight * animals * salePrice;
        const initialCosts = animals * pigletCost;
        return { title: 'Indicadores do lote de suínos', lines: [quantity(gain / days, 'kg/animal/dia de ganho médio'), quantity(feedKg, 'kg de ração estimada'), money(feedCost), `Receita bruta: ${money(revenue)}`, `Margem parcial após ração e compra dos animais: ${money(revenue - feedCost - initialCosts)}`], note: 'Simulação parcial: não inclui mortalidade, medicamentos, mão de obra, energia, transporte, custos financeiros ou rendimento de carcaça. Preços inseridos pelo visitante.' };
    }
    if (id === 'grapes') {
        const vines = number(values, 'vines', 'O número de parreiras');
        const kilos = number(values, 'kg_per_vine', 'A produção por parreira');
        const price = number(values, 'price_per_kg', 'O preço por kg');
        const production = vines * kilos;
        return { title: 'Estimativa da produção de uvas', lines: [quantity(production, 'kg'), quantity(production / 1000, 't'), money(production * price)], note: 'Estimativa bruta baseada na produção por parreira e no preço inseridos. A produção real varia por cultivar, idade, clima e manejo.' };
    }
    if (id === 'seed') {
        const hectares = number(values, 'hectares', 'A área');
        const dose = number(values, 'dose_kg_ha', 'A dose de sementes');
        const price = number(values, 'price_per_kg', 'O preço por kg');
        return { title: 'Estimativa de sementes', lines: [quantity(hectares * dose, 'kg de sementes'), money(hectares * dose * price)], note: 'Use a dose técnica recomendada para a cultura, cultivar, lote e região. Este simulador não define recomendação agronômica.' };
    }
    if (id === 'milk') {
        const animals = number(values, 'animals', 'Os animais em lactação');
        const litersPerAnimalDay = number(values, 'liters_per_animal_day', 'A produção diária por animal');
        const days = number(values, 'days', 'Os dias do período');
        const price = number(values, 'price_per_liter', 'O preço por litro');
        const variableCost = number(values, 'variable_cost', 'Os custos variáveis');
        if (animals === 0 || days === 0) throw new Error('Informe animais e dias maiores que zero.');
        const liters = animals * litersPerAnimalDay * days;
        const revenue = liters * price;
        return { title: 'Produção leiteira estimada', lines: [quantity(liters, 'L no período'), `Receita bruta: ${money(revenue)}`, `Margem parcial após custos variáveis informados: ${money(revenue - variableCost)}`], note: 'A margem desconta somente os custos variáveis informados. Não representa lucro líquido e não inclui custos não lançados, perdas ou mudanças de qualidade.' };
    }
    if (id === 'feed') {
        const animals = number(values, 'animals', 'Os animais no lote');
        const daily = number(values, 'feed_per_animal_day', 'A ração diária por animal');
        const days = number(values, 'days', 'Os dias do período');
        const price = number(values, 'price_per_kg', 'O preço por kg');
        if (animals === 0 || days === 0) throw new Error('Informe animais e dias maiores que zero.');
        const feedKg = animals * daily * days;
        return { title: 'Consumo de ração estimado', lines: [quantity(feedKg, 'kg no lote'), quantity(feedKg / 1000, 't no lote'), `Custo estimado: ${money(feedKg * price)}`], note: 'Consumo e custo calculados com a quantidade diária e o preço informados. A dose não é recomendação nutricional.' };
    }
    if (id === 'orchard') {
        const trees = number(values, 'trees', 'As plantas produtivas');
        const perTree = number(values, 'kg_per_tree', 'A produção por planta');
        const price = number(values, 'price_per_kg', 'O preço por kg');
        const variableCost = number(values, 'variable_cost', 'Os custos variáveis');
        const production = trees * perTree;
        const revenue = production * price;
        const fruit = values.fruit === 'maçã' ? 'maçã' : values.fruit === 'pêssego' ? 'pêssego' : null;
        if (!fruit) throw new Error('Selecione maçã ou pêssego.');
        return { title: `Estimativa de ${fruit}`, lines: [quantity(production, 'kg'), `Receita bruta: ${money(revenue)}`, `Margem parcial após custos variáveis informados: ${money(revenue - variableCost)}`], note: 'Produção depende de cultivar, idade, clima, manejo e classificação. A margem desconta apenas custos variáveis informados; não é lucro líquido.' };
    }
    if (id === 'honey') {
        const hives = number(values, 'hives', 'As colmeias produtivas');
        const perHive = number(values, 'kg_per_hive', 'A produção por colmeia');
        const price = number(values, 'price_per_kg', 'O preço por kg');
        const variableCost = number(values, 'variable_cost', 'Os custos variáveis');
        const production = hives * perHive;
        const revenue = production * price;
        return { title: 'Estimativa de produção de mel', lines: [quantity(production, 'kg no apiário'), `Receita bruta: ${money(revenue)}`, `Margem parcial após custos variáveis informados: ${money(revenue - variableCost)}`], note: 'Produção varia por região, florada, clima e manejo. A margem desconta somente custos variáveis informados; não representa lucro líquido.' };
    }
    throw new Error('Ferramenta desconhecida.');
}
