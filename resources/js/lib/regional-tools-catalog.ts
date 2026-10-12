/** Public names of the Central Campeira tools, shared by the Nossa Terra page and the global search. */
export type ToolId = 'area' | 'seed' | 'arroba' | 'wool' | 'stocking' | 'grapes' | 'poultry' | 'swine' | 'milk' | 'feed' | 'orchard' | 'honey' | 'weight' | 'volume' | 'fuel' | 'travel' | 'moving';

export type ToolInfo = { id: ToolId; title: string; category: string; description: string };

export const toolCatalog: Record<ToolId, ToolInfo> = {
    area: { id: 'area', title: 'Área rural', category: 'Agricultura', description: 'Converta hectares, metros quadrados e diferentes medidas de alqueire.' },
    seed: { id: 'seed', title: 'Sementes por área', category: 'Agricultura', description: 'Estime quantidade e custo a partir da dose e do preço informados.' },
    arroba: { id: 'arroba', title: 'Peso bovino e arrobas', category: 'Pecuária', description: 'Converta peso vivo em arrobas de carcaça ou informe diretamente o peso de carcaça em kg/@.' },
    wool: { id: 'wool', title: 'Lã ovina', category: 'Pecuária', description: 'Produção total estimada e receita bruta pela lã.' },
    stocking: { id: 'stocking', title: 'Lotação por área', category: 'Pecuária', description: 'Converta lotação informada em unidades animais e equivalentes do rebanho.' },
    grapes: { id: 'grapes', title: 'Produção de uvas', category: 'Produção colonial', description: 'Produção por parreira, total do vinhedo e valor bruto informado.' },
    poultry: { id: 'poultry', title: 'Indicadores de aves', category: 'Produção colonial', description: 'Conversão alimentar, custo da ração e mortalidade informada.' },
    swine: { id: 'swine', title: 'Indicadores de suínos', category: 'Produção colonial', description: 'Ganho diário, ração, receita bruta e margem parcial do lote.' },
    milk: { id: 'milk', title: 'Produção leiteira', category: 'Pecuária', description: 'Volume e receita bruta do período com produção, dias e preço informados.' },
    feed: { id: 'feed', title: 'Consumo de ração por lote', category: 'Produção colonial', description: 'Estime consumo e custo a partir do número de animais, dose diária e duração.' },
    orchard: { id: 'orchard', title: 'Produção de maçã e pêssego', category: 'Produção colonial', description: 'Produção e receita bruta estimadas com número de plantas e rendimento informados.' },
    honey: { id: 'honey', title: 'Produção de mel', category: 'Produção colonial', description: 'Produção e receita bruta por colmeia e apiário com rendimento e preço informados.' },
    weight: { id: 'weight', title: 'Peso', category: 'Utilidades', description: 'Converta quilogramas, gramas, toneladas e libras.' },
    volume: { id: 'volume', title: 'Volume', category: 'Utilidades', description: 'Converta litros, mililitros, metros cúbicos e galões americanos.' },
    fuel: { id: 'fuel', title: 'Combustível', category: 'Utilidades', description: 'Litros e custo aproximado com os valores do seu veículo.' },
    travel: { id: 'travel', title: 'Custo de viagem', category: 'Utilidades', description: 'Some combustível e pedágios por trecho ou ida e volta.' },
    moving: { id: 'moving', title: 'Estimativa de mudança', category: 'Utilidades', description: 'Simule um orçamento por distância, volume e tarifa inserida.' },
};
