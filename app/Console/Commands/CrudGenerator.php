<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CrudGenerator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'code:generator';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create CRUD operations';

    protected $config = null;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $config = json_decode(file_get_contents(resource_path("stubs/config/module.json")));
        $this->config = collect($config);

        $name = $this->config['name'];

        $this->migration($name);
        $this->model($name);
        $this->resource($name);
        $this->request($name);
        $this->controller($name);

        File::append(base_path('routes/api.php'), 'Route::resource(\'' . Str::plural(strtolower($name)) . "', \App\Http\Controllers\\" . $name ."Controller::class);");
        return CommandAlias::SUCCESS;
    }

    protected function getColumns($config): string
    {
        return '"'. implode('", "', collect($config['columns'])->keys()->unique()->toArray()) . '"';
    }

    protected function getStub($type): bool|string
    {
        return file_get_contents(resource_path("stubs/$type.stub"));
    }

    protected function getMigrationColumns(): string
    {
        $columns = '';
        $columnsArray = (array)$this->config['columns'];
        if (count($columnsArray)) {
            foreach ($columnsArray as $key => $column) {
                switch ($column->type) {
                    case 'string':
                        $modelTemplate = str_replace(
                            ['{{name}}'],
                            [$key],
                            file_get_contents(resource_path("stubs/child/migration/String.stub"))
                        );
                        $columns = $columns . $modelTemplate;
                        break;
                    case 'text':
                        $modelTemplate = str_replace(
                            ['{{name}}'],
                            [$key],
                            file_get_contents(resource_path("stubs/child/migration/Text.stub"))
                        );
                        $columns = $columns . $modelTemplate;
                        break;
                    case 'tiny_int':
                        $modelTemplate = str_replace(
                            ['{{name}}'],
                            [$key],
                            file_get_contents(resource_path("stubs/child/migration/TinyInt.stub"))
                        );
                        $columns = $columns . $modelTemplate;
                        break;
                    default:
                        break;
                }
            }
        }
        return $columns;
    }

    protected function migration($name)
    {
        $columns = $this->getMigrationColumns();
        $modelTemplate = str_replace(
            ['{{table}}', '{{fields}}'],
            [Str::plural(strtolower($name)), $columns],
            $this->getStub('Migration')
        );

        file_put_contents($this->laravel->basePath() . '/database/migrations/'. date('Y_m_d_His'). '_create_' . strtolower($name) . '_table.php', $modelTemplate);
    }

    protected function resource($name)
    {
        $modelTemplate = str_replace(
            ['{{modelName}}'],
            [$name],
            $this->getStub('Resource')
        );

        file_put_contents(app_path("/Http/Resources/{$name}Resource.php"), $modelTemplate);
    }

    protected function model($name)
    {
        $modelTemplate = str_replace(
            ['{{modelName}}', '{{columnName}}' , '{{table}}'],
            [$name, $this->getColumns($this->config), Str::plural(strtolower($name))],
            $this->getStub('Model')
        );

        file_put_contents(app_path("/Models/{$name}.php"), $modelTemplate);
    }

    public function request($name)
    {
        $requestTemplate = str_replace(
            [
                '{{modelName}}',
                //'{{modelRules}}',
            ],
            [
                $name,
                //strtolower(Str::plural($name))
            ],
            $this->getStub('Request')
        );

        file_put_contents(app_path("/Http/Requests/{$name}Request.php"), $requestTemplate);

    }

    protected function controller($name)
    {
        $controllerTemplate = str_replace(
            [
                '{{modelName}}',
                '{{modelNamePluralLowerCase}}',
                '{{modelNameSingularLowerCase}}'
            ],
            [
                $name,
                strtolower(Str::plural($name)),
                strtolower($name)
            ],
            $this->getStub('Controller')
        );

        file_put_contents(app_path("/Http/Controllers/{$name}Controller.php"), $controllerTemplate);
    }
}
