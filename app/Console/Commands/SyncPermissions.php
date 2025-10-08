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
            return Str::startsWith($route->uri(), 'api/') && $route->getActionName() !== 'Closure';
        });

        $uniqueUris = [];

        foreach ($routes as $route) {
            // Remove 'api/' prefix
            $uri = str_replace('api/', '', $route->uri());

            // Remove any parameters like {id}
            $uri = preg_replace('/\{.*?\}/', '', $uri);

            // Trim slashes and make it clean
            $uri = trim($uri, '/');

            // If route is resource, ensure we store only once per base URI
            $baseUri = explode('/', $uri)[0]; // e.g. 'entities'

            // Store only unique base URIs
            $uniqueUris[$baseUri] = [
                'display_name' => Str::title(str_replace('-', ' ', $baseUri)),
                'description' => "Auto-generated permission for resource: {$baseUri}"
            ];
        }

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
