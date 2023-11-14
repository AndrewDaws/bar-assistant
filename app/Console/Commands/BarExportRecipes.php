<?php

declare(strict_types=1);

namespace Kami\Cocktail\Console\Commands;

use Throwable;
use Kami\Cocktail\Models\Bar;
use Illuminate\Console\Command;
use Kami\Cocktail\Export\Recipes;

class BarExportRecipes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bar:export-recipes {barId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all recipe data (ingredients, cocktails) from a single bar';

    public function __construct(private readonly Recipes $exporter)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $barId = (int) $this->argument('barId');

        try {
            $bar = Bar::findOrFail($barId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->output->info(sprintf('Starting recipe export from bar: %s - "%s"', $bar->id, $bar->name));

        $filename = $this->exporter->process($barId);

        $this->output->success('Data exported to file: ' . $filename);

        return Command::SUCCESS;
    }
}