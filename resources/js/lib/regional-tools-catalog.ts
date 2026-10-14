/** Public names of the Central Campeira tools, shared by the Nossa Terra page, the tool pages and the global search. */
export type ToolId = 'area' | 'seed' | 'arroba' | 'wool' | 'stocking' | 'grapes' | 'poultry' | 'swine' | 'milk' | 'feed' | 'orchard' | 'honey' | 'weight' | 'volume' | 'fuel' | 'travel' | 'moving';

/** `slug` is the public address and must match RegionalHubController::TOOL_SLUGS; `about` is the explanatory text of the tool page. */
export type ToolInfo = { id: ToolId; slug: string; title: string; category: string; description: string; about: string[] };

export const toolCatalog: Record<ToolId, ToolInfo> = {
    area: { id: 'area', slug: 'area-rural', title: 'Área rural', category: 'Agricultura', description: 'Converta hectares, metros quadrados e diferentes medidas de alqueire.', about: [
        'Escrituras, contratos de arrendamento e conversas de vizinho nem sempre usam a mesma medida. Esta calculadora converte uma área entre hectares, metros quadrados, quilômetros quadrados, acres e os alqueires mais comuns no Brasil.',
        'Um hectare tem 10.000 m². O alqueire paulista tem 2,42 ha, o alqueire de 75 × 75 braças tem 2,72 ha, o mineiro ou geométrico tem 4,84 ha e o baiano tem 9,68 ha. Como o alqueire muda conforme a região e o costume, confirme a convenção local e a unidade que aparece no documento do imóvel.',
    ] },
    seed: { id: 'seed', slug: 'sementes-por-area', title: 'Sementes por área', category: 'Agricultura', description: 'Estime quantidade e custo a partir da dose e do preço informados.', about: [
        'Multiplica a área a semear pela dose de sementes em kg/ha para estimar quantos quilos comprar e, com o preço por quilo, quanto vai custar.',
        'A dose certa depende da cultura, da cultivar, da qualidade do lote e da região. Use a recomendação técnica do seu agrônomo ou da empresa de sementes: a calculadora só faz a conta, não recomenda dose.',
    ] },
    arroba: { id: 'arroba', slug: 'peso-bovino-e-arrobas', title: 'Peso bovino e arrobas', category: 'Pecuária', description: 'Converta peso vivo em arrobas de carcaça ou informe diretamente o peso de carcaça em kg/@.', about: [
        'Na venda de bovinos, o preço costuma ser dado por arroba de carcaça, e uma arroba equivale a 15 kg de carcaça. A partir do peso vivo, a calculadora aplica o rendimento de carcaça que você informar (50% é um ponto de partida comum) e chega às arrobas por animal e do lote.',
        'Se você já tem o peso de carcaça, em quilos ou em arrobas, escolha essa opção e a conversão é direta. Informando o preço da arroba, aparece também o valor estimado por animal e do lote inteiro.',
    ] },
    wool: { id: 'wool', slug: 'producao-de-la', title: 'Lã ovina', category: 'Pecuária', description: 'Produção total estimada e receita bruta pela lã.', about: [
        'Estima quantos quilos de lã o rebanho tosquiado vai render, multiplicando o número de ovinos pelo peso médio do velo, e calcula a receita bruta com o preço por quilo informado.',
        'O resultado considera a lã suja, como sai da tosquia. Não desconta rendimento de lavagem, classificação por finura nem custos da tosquia.',
    ] },
    stocking: { id: 'stocking', slug: 'lotacao-por-area', title: 'Lotação por área', category: 'Pecuária', description: 'Converta lotação informada em unidades animais e equivalentes do rebanho.', about: [
        'Converte a lotação de um campo, em unidades animais por hectare (UA/ha), no total de UA da área e no número equivalente de animais com o peso médio do seu rebanho.',
        'Por convenção, uma unidade animal (UA) corresponde a 450 kg de peso vivo, referência usada pela Embrapa. A capacidade real do campo depende da pastagem, da estação e do manejo.',
    ] },
    grapes: { id: 'grapes', slug: 'producao-de-uvas', title: 'Produção de uvas', category: 'Produção colonial', description: 'Produção por parreira, total do vinhedo e valor bruto informado.', about: [
        'Estima a colheita do parreiral multiplicando o número de parreiras produtivas pela produção média de cada uma, em quilos e toneladas, e o valor bruto com o preço por quilo informado.',
        'A produção de cada safra muda com a cultivar, a idade das plantas, o clima e o manejo. Use números de safras anteriores do próprio vinhedo sempre que puder.',
    ] },
    poultry: { id: 'poultry', slug: 'indicadores-de-aves', title: 'Indicadores de aves', category: 'Produção colonial', description: 'Conversão alimentar, custo da ração e mortalidade informada.', about: [
        'Calcula os principais indicadores de um lote de aves: a conversão alimentar (quilos de ração para cada quilo de peso vivo produzido), o custo da ração consumida e a mortalidade do lote.',
        'Os indicadores saem só dos números que você informar, então servem para comparar lotes da própria granja ao longo do tempo.',
    ] },
    swine: { id: 'swine', slug: 'indicadores-de-suinos', title: 'Indicadores de suínos', category: 'Produção colonial', description: 'Ganho diário, ração, receita bruta e margem parcial do lote.', about: [
        'A partir do peso de entrada e de saída, dos dias de alojamento e da conversão alimentar, estima o ganho médio diário, a ração consumida e o custo dela.',
        'Com o custo de compra dos animais e o preço de venda por quilo vivo, mostra a receita bruta e uma margem parcial. Essa margem não inclui mortalidade, medicamentos, mão de obra, energia nem outros custos do lote.',
    ] },
    milk: { id: 'milk', slug: 'producao-leiteira', title: 'Produção leiteira', category: 'Pecuária', description: 'Volume e receita bruta do período com produção, dias e preço informados.', about: [
        'Soma o leite produzido no período multiplicando os animais em lactação pela média de litros por dia e pelo número de dias, e calcula a receita bruta com o preço pago por litro.',
        'Informando os custos variáveis, como ração e insumos, aparece uma margem parcial. Ela não é o lucro líquido, porque não inclui custos que você não lançou, perdas ou bonificações por qualidade.',
    ] },
    feed: { id: 'feed', slug: 'consumo-de-racao', title: 'Consumo de ração por lote', category: 'Produção colonial', description: 'Estime consumo e custo a partir do número de animais, dose diária e duração.', about: [
        'Ajuda a planejar a compra de ração: multiplica o número de animais pela quantidade diária por animal e pelos dias do período, em quilos e toneladas, e calcula o custo com o preço por quilo.',
        'Serve para qualquer criação, de aves a bovinos, desde que a dose diária seja a usada no seu manejo.',
    ] },
    orchard: { id: 'orchard', slug: 'producao-de-maca-e-pessego', title: 'Produção de maçã e pêssego', category: 'Produção colonial', description: 'Produção e receita bruta estimadas com número de plantas e rendimento informados.', about: [
        'Estima a colheita do pomar de maçã ou pêssego pelo número de plantas produtivas e pela produção média por planta, e calcula a receita bruta com o preço por quilo.',
        'Informando os custos variáveis, aparece uma margem parcial. A produção depende da cultivar, da idade do pomar, do frio acumulado no inverno e do manejo.',
    ] },
    honey: { id: 'honey', slug: 'producao-de-mel', title: 'Produção de mel', category: 'Produção colonial', description: 'Produção e receita bruta por colmeia e apiário com rendimento e preço informados.', about: [
        'Estima o mel colhido no apiário multiplicando as colmeias produtivas pela produção média de cada uma, e calcula a receita bruta com o preço por quilo.',
        'Informando os custos variáveis, aparece uma margem parcial. A produção muda conforme a florada, o clima e a força das colmeias.',
    ] },
    weight: { id: 'weight', slug: 'conversor-de-peso', title: 'Peso', category: 'Utilidades', description: 'Converta quilogramas, gramas, toneladas e libras.', about: [
        'Converte um peso entre quilogramas, gramas, toneladas e libras. É útil para ler embalagens importadas, fichas técnicas e cotações em outras unidades.',
        'A libra usada é a internacional (avoirdupois), igual a 0,45359237 kg.',
    ] },
    volume: { id: 'volume', slug: 'conversor-de-volume', title: 'Volume', category: 'Utilidades', description: 'Converta litros, mililitros, metros cúbicos e galões americanos.', about: [
        'Converte um volume entre litros, mililitros, metros cúbicos e galões americanos, como os de caixas d’água, tanques, defensivos e combustível.',
        'O galão usado é o galão líquido americano, igual a 3,785411784 litros.',
    ] },
    fuel: { id: 'fuel', slug: 'custo-de-combustivel', title: 'Combustível', category: 'Utilidades', description: 'Litros e custo aproximado com os valores do seu veículo.', about: [
        'Calcula quantos litros de combustível um trajeto vai gastar, dividindo a distância pelo consumo médio do veículo em km/L, e o custo com o preço do litro que você informar.',
        'Marque ida e volta para dobrar o trajeto. O consumo real muda com a estrada, a carga e o jeito de dirigir.',
    ] },
    travel: { id: 'travel', slug: 'custo-de-viagem', title: 'Custo de viagem', category: 'Utilidades', description: 'Some combustível e pedágios por trecho ou ida e volta.', about: [
        'Planeja o gasto de uma viagem de carro somando o combustível e os pedágios de cada trecho, só ida ou ida e volta.',
        'Bom para visitar a família no interior, ir a uma festa campeira ou planejar uma viagem de negócios. Os preços são os que você informar; a calculadora não consulta tarifas atuais.',
    ] },
    moving: { id: 'moving', slug: 'estimativa-de-mudanca', title: 'Estimativa de mudança', category: 'Utilidades', description: 'Simule um orçamento por distância, volume e tarifa inserida.', about: [
        'Simula o custo de uma mudança pela distância, pelo volume em metros cúbicos e por uma tarifa por m³ e por quilômetro, mais custos adicionais como embalagem e montagem.',
        'É uma conta para comparar orçamentos de transportadoras, não um preço de mercado. Peça cotações e confira o que cada uma inclui.',
    ] },
};

export function toolPath(id: ToolId): string {
    return `/nossa-terra/ferramentas/${toolCatalog[id].slug}`;
}
