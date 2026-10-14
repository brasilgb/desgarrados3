<?php

namespace App\Console\Commands;

use App\Services\EditorialImport\EditorialImporter;
use Illuminate\Console\Command;

class ImportEditorialArchive extends Command
{
    protected $signature = 'desgarrados:editorial-import
        {--path= : Pasta com os arquivos JSON do acervo (padrão: database/data/editorial)}
        {--images : Baixa do Wikimedia Commons as imagens com licença livre verificada}
        {--update : Atualiza itens importados antes que ainda não tenham sido editados por pessoas}
        {--dry-run : Valida e simula a carga sem gravar nada}
        {--report= : Grava o relatório da carga em JSON neste caminho}';

    protected $description = 'Importa o acervo editorial pesquisado (publicações, guias de produção, coleções e imagens) sem duplicar conteúdo.';

    public function handle(EditorialImporter $importer): int
    {
        $path = $this->option('path') ?: database_path('data/editorial');
        if (! is_dir($path)) {
            $this->error("Pasta não encontrada: {$path}");

            return self::FAILURE;
        }

        $report = $importer->import($path, (bool) $this->option('images'), (bool) $this->option('update'), (bool) $this->option('dry-run'));

        $rows = [];
        foreach ($report['sections'] as $type => $row) {
            $rows[] = [$type, ...array_values($row)];
        }
        $rows[] = ['guias de produção', ...array_values($report['guides'])];
        $this->table(['Seção', 'Criados', 'Atualizados', 'Ignorados', 'Publicados', 'Rascunhos'], $rows);
        $this->line("Imagens: {$report['images']['added']} adicionadas, {$report['images']['reused']} reaproveitadas, ".count($report['images']['refused']).' recusadas.');
        foreach ($report['images']['refused'] as $refused) {
            $this->warn("Imagem recusada ({$refused['publication']}): {$refused['file']} — {$refused['reason']}");
        }
        foreach ($report['warnings'] as $warning) {
            $this->warn($warning);
        }
        if ($this->option('report')) {
            file_put_contents((string) $this->option('report'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $this->info($this->option('dry-run') ? 'Simulação concluída; nada foi gravado.' : 'Carga editorial concluída.');

        return self::SUCCESS;
    }
}
