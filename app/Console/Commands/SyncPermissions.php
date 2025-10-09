<?php


namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use App\Models\Permission;
use Illuminate\Support\Str;

class SyncPermissions extends Command
{
    protected $signature = 'sync:permissions';
    protected $description = 'Sync all API routes into the permissions table';

    public function handle()
    {
        $routes = collect(Route::getRoutes())->filter(function ($route) {
            $middleware = $route->middleware();
            return Str::startsWith($route->uri(), 'api/') &&
                   in_array('auth:api', $middleware) &&
                   in_array('permission', $middleware);
        });

        $uniqueUris = [];

        foreach ($routes as $route) {
            $uri = str_replace('api/', '', $route->uri());

            $uri = preg_replace('/\{.*?\}/', '', $uri);

            $uri = rtrim($uri, '/');

            $uniqueUris[$uri] = [
                'display_name' => Str::title(str_replace('-', ' ', $uri)),
                'description' => "Auto-generated permission for resource: {$uri}"
            ];
        }

        $uniqueUris['roles-assign'] = [
            'display_name' => 'Assign Role',
            'description' => 'Permission to assign roles to users'
        ];
        $uniqueUris['roles-remove'] = [
            'display_name' => 'Remove Role',
            'description' => 'Permission to remove roles from users'
        ];

        foreach ($uniqueUris as $name => $details) {
            Permission::firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $details['display_name'],
                    'description' => $details['description']
                ]
            );
        }

        $this->info('Permissions synced successfully!');
    }
}
